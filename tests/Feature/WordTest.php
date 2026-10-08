<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Exceptions\WordNotSupported;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use ZipArchive;

class WordTest extends TestCase
{
    private string $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templates = sys_get_temp_dir().'/easy-pdf-word-tests/templates';
        (new Filesystem)->deleteDirectory($this->templates);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->templates);

        parent::tearDown();
    }

    public static function bundled(): array
    {
        return TemplatesTest::bundled();
    }

    #[DataProvider('bundled')]
    public function test_bundled_templates_render_to_word(string $name, string $locale): void
    {
        $sample = Doc::templates()->get($name)->sample();

        $word = Doc::template($name, $sample)->locale($locale)->word();
        $xml = $this->documentXml($word->content());

        $this->assertSame('phpword', $word->engine());
        $this->assertSame($locale === 'ar', str_contains($xml, '<w:bidi/>'));
        $this->keep("word-{$name}-{$locale}", $word->content(), 'docx');
    }

    public function test_arabic_documents_are_right_to_left(): void
    {
        $xml = $this->documentXml(Doc::make()
            ->heading('تقرير المبيعات')
            ->paragraph('الإجمالي: 1,250 جنيه')
            ->table([['الفرع', 'المبيعات'], ['القاهرة', '1,000']], ['header' => true])
            ->locale('ar')
            ->word()
            ->content());

        $this->assertStringContainsString('<w:bidi/>', $xml);
        $this->assertStringContainsString('<w:rtl/>', $xml);
        $this->assertStringContainsString('<w:bidiVisual w:val="1"/>', $xml);
        $this->assertStringContainsString('w:bidi="ar-SA"', $xml);
        $this->assertStringContainsString('<w:tblHeader w:val="1"/>', $xml);
        $this->assertStringContainsString('تقرير المبيعات', $xml);
    }

    public function test_english_documents_stay_left_to_right(): void
    {
        $xml = $this->documentXml(Doc::make()->heading('Sales')->table([['A', 'B']])->locale('en')->word()->content());

        $this->assertStringNotContainsString('<w:bidi/>', $xml);
        $this->assertStringNotContainsString('<w:bidiVisual', $xml);
    }

    public function test_arabic_numerals_and_left_to_right_values(): void
    {
        $xml = $this->documentXml(Doc::make()
            ->paragraph(['فاتورة 1024 ', ['text' => '+20 100', 'ltr' => true], ' -2.5'])
            ->locale('ar')
            ->numerals('arabic')
            ->word()
            ->content());

        $this->assertStringContainsString('فاتورة ١٠٢٤', $xml);
        $this->assertStringContainsString("\u{202A}+٢٠ ١٠٠\u{202C}", $xml);
        $this->assertStringContainsString("\u{202A}-٢.٥\u{202C}", $xml);
    }

    public function test_special_characters_are_escaped(): void
    {
        $content = Doc::make()
            ->heading('Smith & Co <Ltd>')
            ->table([['A & B', ['lines' => ['<x>']]]])
            ->footer('<p>R&amp;D</p>')
            ->word()
            ->content();

        $xml = $this->documentXml($content);

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertNotFalse(simplexml_load_string($this->zipEntry($content, 'word/footer1.xml')));
        $this->assertStringContainsString('Smith &amp; Co &lt;Ltd&gt;', $xml);
        $this->assertStringContainsString('R&amp;D', $this->zipEntry($content, 'word/footer1.xml'));
    }

    public function test_colours_are_written_as_hex_values(): void
    {
        $xml = $this->documentXml(Doc::make()
            ->paragraph('short', ['color' => '#abc'])
            ->paragraph('named', ['color' => 'red'])
            ->paragraph('broken', ['color' => '"><x'])
            ->word()
            ->content());

        $this->assertStringContainsString('<w:color w:val="AABBCC"/>', $xml);
        $this->assertSame(1, substr_count($xml, '<w:color '));
    }

    public function test_page_settings_and_footer_page_numbers(): void
    {
        $content = Doc::make()
            ->paragraph('مرحبا')
            ->locale('ar')
            ->landscape()
            ->margins(20)
            ->footer('<div>صفحة {page} من {pages}</div>')
            ->word()
            ->content();

        $xml = $this->documentXml($content);
        $footer = $this->zipEntry($content, 'word/footer1.xml');

        $this->assertMatchesRegularExpression('/<w:pgSz w:orient="landscape" w:w="16838" w:h="11906"\/>/', $xml);
        $this->assertStringContainsString('w:top="1134"', $xml);
        $this->assertStringContainsString(' PAGE ', $footer);
        $this->assertStringContainsString(' NUMPAGES ', $footer);
    }

    public function test_the_same_builder_document_renders_to_pdf(): void
    {
        $document = Doc::make()
            ->heading('تقرير')
            ->paragraph([['text' => 'الإجمالي: ', 'bold' => true], '-2.5'])
            ->table([['الفرع', 'المبيعات'], ['القاهرة', ['text' => '1,000', 'align' => 'end']]], ['header' => true])
            ->qr('https://example.com')
            ->locale('ar');

        $html = $document->toHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('<bdo dir="ltr">-2.5</bdo>', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringStartsWith('%PDF', $document->pdf()->content());
    }

    public function test_word_download_response(): void
    {
        $response = Doc::make()->paragraph('مرحبا')->locale('ar')->word()->download('تقرير');

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString(".docx", $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_word_docx_templates_are_filled(): void
    {
        $this->makeDocxTemplate('quote');

        $content = Doc::template('quote', [
            'customer' => ['name' => 'مؤسسة النور'],
            'date' => '2026-10-08',
            'items' => [
                ['description' => 'تطوير', 'price' => 25000.5],
                ['description' => 'استضافة <سنوية>', 'price' => 450],
            ],
        ])->locale('ar')->numerals('arabic')->word()->content();

        $xml = $this->documentXml($content);

        $this->assertStringContainsString('مؤسسة النور', $xml);
        $this->assertStringContainsString('٢٥,٠٠٠.٥٠', $xml);
        $this->assertStringContainsString('استضافة &lt;سنوية&gt;', $xml);
        $this->assertStringContainsString('عرض سعر', $xml);
        $this->assertStringContainsString('هـ', $xml);
        $this->assertStringContainsString('<w:t xml:space="preserve">١</w:t>', $xml);
        $this->assertStringContainsString('<w:t xml:space="preserve">٢</w:t>', $xml);
        $this->assertStringNotContainsString('${', $xml);
    }

    public function test_templates_without_a_word_layout_explain_what_is_missing(): void
    {
        mkdir($this->templates.'/pdf-only', 0775, true);
        file_put_contents($this->templates.'/pdf-only/pdf.blade.php', '<p>pdf</p>');

        $this->expectException(WordNotSupported::class);

        Doc::template('pdf-only')->word();
    }

    public function test_views_and_html_cannot_make_word_files(): void
    {
        $this->expectException(WordNotSupported::class);

        Doc::html('<p>مرحبا</p>')->word();
    }

    public function test_a_template_with_only_word_php_also_makes_pdfs(): void
    {
        mkdir($this->templates.'/note', 0775, true);
        file_put_contents($this->templates.'/note/word.php', <<<'PHP'
            <?php
            return function ($word, array $data, $doc) {
                $word->heading($data['title'])->paragraph($data['body']);
            };
            PHP);

        $document = Doc::template('note', ['title' => 'مذكرة داخلية', 'body' => 'نص المذكرة'])->locale('ar');

        $this->assertStringContainsString('مذكرة داخلية', $document->toHtml());
        $this->assertStringStartsWith('%PDF', $document->pdf()->content());
        $this->assertStringContainsString('نص المذكرة', $this->documentXml($document->word()->content()));
    }

    public function test_layout_php_serves_both_formats_and_word_php_wins_for_word(): void
    {
        mkdir($this->templates.'/memo', 0775, true);
        file_put_contents($this->templates.'/memo/layout.php', '<?php return fn ($b, $data) => $b->paragraph("shared layout");');

        $memo = Doc::template('memo')->locale('ar');
        $this->assertStringContainsString('shared layout', $memo->toHtml());
        $this->assertStringContainsString('shared layout', $this->documentXml($memo->word()->content()));

        file_put_contents($this->templates.'/memo/word.php', '<?php return fn ($b, $data) => $b->paragraph("word layout");');

        $this->assertStringContainsString('shared layout', Doc::template('memo')->toHtml());
        $this->assertStringContainsString('word layout', $this->documentXml(Doc::template('memo')->word()->content()));
    }

    public function test_builder_methods_are_only_available_on_make(): void
    {
        $this->expectException(\BadMethodCallException::class);

        Doc::html('<p>x</p>')->heading('x');
    }

    private function makeDocxTemplate(string $name): void
    {
        $dir = $this->templates.'/'.$name;
        mkdir($dir.'/lang', 0775, true);
        file_put_contents($dir.'/lang/ar.php', "<?php return ['title' => 'عرض سعر'];");

        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('${t.title}');
        $section->addText('${customer.name} - ${doc.hijri_date}');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(800)->addText('${items.row_number}');
        $table->addCell(4000)->addText('${items.description}');
        $table->addCell(2000)->addText('${items.price}');
        IOFactory::createWriter($word, 'Word2007')->save($dir.'/word.docx');
    }

    private function documentXml(string $docx): string
    {
        return $this->zipEntry($docx, 'word/document.xml');
    }

    private function zipEntry(string $docx, string $entry): string
    {
        $file = tempnam(sys_get_temp_dir(), 'docx-test');
        file_put_contents($file, $docx);
        $zip = new ZipArchive;
        $zip->open($file);
        $content = (string) $zip->getFromName($entry);
        $zip->close();
        @unlink($file);

        return $content;
    }
}
