<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;

/**
 * GeneratedDocument::text(), blocks() and hasWatermark(): what a Word file
 * says, checked under Doc::fake() without rendering it.
 */
class FakeTextTest extends TestCase
{
    public function test_a_word_file_text_and_blocks(): void
    {
        Doc::fake();

        Doc::make()->locale('ar')
            ->header(fn (DocumentBuilder $h) => $h->table([['شركة النور', 'عرض سعر']]))
            ->heading('عرض سعر رقم QT-1')
            ->richText('<p>يسري العرض <b>30</b> يوماً.</p><script>x</script>')
            ->word('عرض.docx');

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->isWord()
            && $doc->contains('عرض سعر رقم QT-1')
            && $doc->contains('يسري العرض 30 يوماً.')
            && ! $doc->contains('<script>')
            && $doc->blocks()[0]['type'] === 'heading');

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->text() === "شركة النور\tعرض سعر\nعرض سعر رقم QT-1\nيسري العرض 30 يوماً.");
    }

    public function test_a_template_text_comes_from_its_layout(): void
    {
        Doc::fake();

        Doc::template('quotation', Doc::templates()->get('quotation')->sample())->locale('ar')->word();

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->blocks() !== [] && $doc->text() !== '');
    }

    public function test_html_documents_have_no_blocks(): void
    {
        Doc::fake();

        Doc::html('<p>مرحبا</p>')->pdf();

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->text() === '' && $doc->blocks() === [] && $doc->contains('مرحبا'));
    }

    public function test_pdf_contains_still_reads_the_html(): void
    {
        Doc::fake();

        Doc::make()->paragraph('<b>')->pdf();

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->contains('<b>') && str_contains($doc->html(), '&lt;b&gt;') && $doc->text() === '<b>');
    }

    public function test_watermarks_text_and_image(): void
    {
        Doc::fake();

        Doc::make()->paragraph('عرض')->watermark('مسودة')->pdf('text.pdf');
        Doc::make()->paragraph('عرض')->watermarkImage('stamps/logo.png', ['opacity' => 0.1])->word('image.docx');
        Doc::make()->paragraph('عرض')->watermark('مسودة')->word('none.docx');

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->filename() === 'text.pdf' && $doc->hasWatermark() && $doc->hasWatermark('مسودة') && ! $doc->hasWatermark('نهائي'));
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->filename() === 'image.docx' && $doc->hasWatermark() && $doc->watermarkImage === 'stamps/logo.png');
        // Word files have no text watermark.
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->filename() === 'none.docx' && ! $doc->hasWatermark());
    }
}
