<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class TemplatesTest extends TestCase
{
    public static function bundled(): array
    {
        return [
            'invoice ar' => ['invoice', 'ar'],
            'invoice en' => ['invoice', 'en'],
            'letter ar' => ['letter', 'ar'],
            'letter en' => ['letter', 'en'],
            'report ar' => ['report', 'ar'],
            'report en' => ['report', 'en'],
            'quotation ar' => ['quotation', 'ar'],
            'quotation en' => ['quotation', 'en'],
            'receipt ar' => ['receipt', 'ar'],
            'receipt en' => ['receipt', 'en'],
            'eg-invoice ar' => ['eg-invoice', 'ar'],
            'eg-invoice en' => ['eg-invoice', 'en'],
        ];
    }

    #[DataProvider('bundled')]
    public function test_bundled_templates_render_with_mpdf(string $name, string $locale): void
    {
        $sample = Doc::templates()->get($name)->sample();

        $pdf = Doc::template($name, $sample)->locale($locale)->pdf();

        $this->assertStringStartsWith('%PDF', $pdf->content());
        $this->assertSame('mpdf', $pdf->engine());
        $this->keep("{$name}-{$locale}", $pdf->content());
    }

    public function test_bundled_templates_are_listed(): void
    {
        $this->assertSame(['eg-invoice', 'invoice', 'letter', 'quotation', 'receipt', 'report'], array_keys(Doc::templates()->all()));
    }

    public function test_invoice_totals_and_zatca_qr_are_prepared(): void
    {
        $data = Doc::templates()->get('invoice')->sample();
        $data['invoice']['currency'] = 'SAR';
        $data['invoice']['tax_rate'] = 15;
        $data['qr'] = 'zatca';

        $html = Doc::template('invoice', $data)->locale('ar')->toHtml();

        // 25,000 + 12 x 450 - 400 + 3 x 1,500 = 34,500; VAT 15% = 5,175
        $this->assertStringContainsString('39,675.00', $html);
        $this->assertStringContainsString('فقط تسعة وثلاثون ألفاً وستمائة وخمسة وسبعون ريالاً لا غير', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_zatca_qr_uses_the_company_from_the_theme(): void
    {
        $this->app['config']->set('easy-pdf-word.theme.company', ['name' => 'شركة المثال', 'tax_number' => '300000000000003']);
        $data = Doc::templates()->get('invoice')->sample();
        unset($data['seller']);
        $data['qr'] = 'zatca';

        $prepared = Doc::templates()->get('invoice')->prepare($data, config('easy-pdf-word.theme'));
        $fields = \BiztechEG\EasyPdfWord\Zatca\ZatcaQr::decode($prepared['qr']);

        $this->assertSame('شركة المثال', $fields[1]);
        $this->assertSame('300000000000003', $fields[2]);
        $this->assertStringContainsString('شركة المثال', Doc::template('invoice', $data)->locale('ar')->toHtml());
    }

    public function test_invoices_in_unknown_currencies_render_in_arabic(): void
    {
        $data = Doc::templates()->get('invoice')->sample();
        $data['invoice']['currency'] = 'GBP';

        $html = Doc::template('invoice', $data)->locale('ar')->toHtml();

        $this->assertStringContainsString('فقط تسعة وثلاثون ألفاً وثلاثمائة وثلاثون GBP لا غير', $html);
        $this->assertStringStartsWith('PK', Doc::template('invoice', $data)->locale('ar')->word()->content());
    }

    public function test_report_cells_with_arrays_and_dates(): void
    {
        $data = [
            'title' => 'تقرير',
            'columns' => ['name' => 'الاسم', 'tags' => 'الوسوم', 'joined' => 'التاريخ'],
            'rows' => [['name' => 'سارة', 'tags' => ['أ', 'ب'], 'joined' => now()->setDate(2026, 10, 8)]],
        ];

        $html = Doc::template('report', $data)->locale('ar')->toHtml();

        $this->assertStringContainsString('2026/10/08', $html);
        $this->assertStringStartsWith('PK', Doc::template('report', $data)->locale('ar')->word()->content());
    }

    public function test_collections_are_accepted_as_template_data(): void
    {
        $data = Doc::templates()->get('invoice')->sample();
        $data['items'] = collect($data['items']);

        $this->assertStringContainsString('39,330.00', Doc::template('invoice', $data)->locale('ar')->toHtml());
    }

    public function test_egyptian_e_invoice_taxes(): void
    {
        $data = Doc::templates()->get('eg-invoice')->sample();
        $data['lines'][] = [
            'description' => 'سلعة عليها ضريبة جدول',
            'quantity' => 2, 'unit_price' => 100,
            'taxes' => [['type' => 'T2', 'rate' => 10], ['type' => 'T1', 'rate' => 14]],
        ];

        $prepared = Doc::templates()->get('eg-invoice')->prepare($data);

        // 25,000 + 3,500 VAT - 750 WHT; 5,000 + 700 VAT; 200 + 20 table tax + 30.80 VAT on 220.
        $this->assertSame(27750.0, $prepared['lines'][0]['total']);
        $this->assertSame(250.8, $prepared['lines'][2]['total']);
        $this->assertSame(['T1' => 4230.8, 'T2' => 20.0, 'T4' => 750.0], $prepared['totals']['taxes']);
        $this->assertSame(33700.8, $prepared['totals']['total']);
        $this->assertStringStartsWith('https://invoicing.eta.gov.eg/documents/R6ZQ4SB1ZWP2XKCV2G0AYXHG10/share/', $prepared['qr']);
    }

    public function test_egyptian_e_invoice_tax_bases_follow_eta(): void
    {
        $line = fn (array $taxes, array $extra = []) => ['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'taxes' => $taxes] + $extra;
        $prepare = fn (array ...$lines) => Doc::templates()->get('eg-invoice')->prepare(
            ['document' => ['type' => 'I', 'currency' => 'EGP'], 'lines' => $lines, 'extra_discount' => 0]
        );

        // Table tax (T2) on net + fixed table tax (T3); VAT on net + T2 + T3.
        $taxes = $prepare($line([['type' => 'T1', 'rate' => 14], ['type' => 'T2', 'rate' => 10], ['type' => 'T3', 'amount' => 50]]))['lines'][0]['taxes'];
        $this->assertSame(['T1' => 161.7, 'T2' => 105.0, 'T3' => 50.0], array_column($taxes, 'amount', 'type'));

        // Taxable fees (T5-T12) join the VAT base; non-taxable fees (T13-T20) only the total.
        $prepared = $prepare($line([['type' => 'T1', 'rate' => 14], ['type' => 'T8', 'rate' => 2], ['type' => 'T13', 'rate' => 1]]));
        $this->assertSame(['T1' => 142.8, 'T8' => 20.0, 'T13' => 10.0], array_column($prepared['lines'][0]['taxes'], 'amount', 'type'));
        $this->assertSame(1172.8, $prepared['lines'][0]['total']);

        // Computed amounts win over keys passed with the line.
        $prepared = $prepare($line([['type' => 'T1', 'rate' => 14]], ['total' => 1, 'net' => 2, 'sales' => 3]));
        $this->assertSame([1000.0, 1000.0, 1140.0], [$prepared['lines'][0]['sales'], $prepared['lines'][0]['net'], $prepared['lines'][0]['total']]);
    }

    public function test_three_decimal_currencies_keep_their_fils(): void
    {
        $receipt = Doc::templates()->get('receipt')->sample();
        $receipt = ['amount' => 1.125, 'currency' => 'KWD'] + $receipt;

        $html = Doc::template('receipt', $receipt)->locale('ar')->toHtml();
        $this->assertStringContainsString('1.125', $html);
        $this->assertStringContainsString('فقط دينار واحد ومائة وخمسة وعشرون فلساً لا غير', $html);
        $this->assertStringContainsString('one KWD and 125/1000 only', Doc::template('receipt', $receipt)->locale('en')->toHtml());

        $invoice = Doc::templates()->get('invoice')->sample();
        $invoice['invoice'] = ['currency' => 'KWD', 'tax_rate' => 0] + $invoice['invoice'];
        $invoice['items'] = [['description' => 'x', 'quantity' => 1, 'unit_price' => 1.125]];
        $prepared = Doc::templates()->get('invoice')->prepare($invoice);
        $this->assertSame(1.125, $prepared['totals']['total']);
        $this->assertStringContainsString('1.125', Doc::template('invoice', $invoice)->locale('ar')->toHtml());

        $context = new \BiztechEG\EasyPdfWord\Support\DocContext('ar', 'rtl', 'cairo', [], 'latin', '');
        $this->assertStringContainsString('BHD و125/1000', $context->tafqeet('1.125', 'BHD'));
    }

    public function test_line_totals_add_up_to_the_subtotal(): void
    {
        foreach (['invoice', 'quotation'] as $name) {
            $data = Doc::templates()->get($name)->sample();
            $data['items'] = array_fill(0, 3, ['description' => 'x', 'quantity' => 1.5, 'unit_price' => 3.33]);
            $prepared = Doc::templates()->get($name)->prepare($data);

            $this->assertSame(5.0, $prepared['items'][0]['total']);
            $this->assertSame(15.0, $prepared['totals']['subtotal'], $name);
        }
    }

    public function test_receipt_and_quotation_amounts_in_words(): void
    {
        $receipt = Doc::template('receipt', Doc::templates()->get('receipt')->sample());
        $this->assertStringContainsString('فقط خمسة عشر ألفاً وسبعمائة وخمسون جنيهاً وخمسون قرشاً لا غير', $receipt->locale('ar')->toHtml());
        $this->assertStringContainsString('fifteen thousand seven hundred fifty EGP and 50/100 only', $receipt->locale('en')->toHtml());

        $quote = Doc::template('quotation', Doc::templates()->get('quotation')->sample())->locale('ar')->numerals('arabic')->toHtml();
        $this->assertStringContainsString('<bdo dir="ltr">QT-٢٠٢٦-٠٠٨٨</bdo>', $quote);
        $this->assertStringContainsString('٧١,٢٥٠.٠٠', $quote);
    }

    public function test_footers_keep_codes_and_contacts_left_to_right(): void
    {
        $footer = fn (string $name) => Doc::template($name, Doc::templates()->get($name)->sample())
            ->locale('ar')->numerals('arabic')
            ->theme(['company' => ['phone' => '+20 100 000 0000', 'email' => 'info@example.com']])
            ->options()->footer;

        $this->assertStringContainsString('<bdo dir="ltr">QT-٢٠٢٦-٠٠٨٨</bdo>', $footer('quotation'));
        $this->assertStringContainsString('<bdo dir="ltr">+٢٠ ١٠٠ ٠٠٠ ٠٠٠٠</bdo>', $footer('letter'));
        $this->assertStringContainsString('<bdo dir="ltr">info@example.com</bdo>', $footer('letter'));
        $this->assertMatchesRegularExpression('/<bdo dir="ltr">[^<]+<\/bdo>/', $footer('eg-invoice'));

        $word = Doc::template('quotation', Doc::templates()->get('quotation')->sample())->locale('ar')->numerals('arabic')->word()->content();
        $this->assertStringContainsString("\u{202D}QT-٢٠٢٦-٠٠٨٨\u{202C}", $this->zipEntry($word, 'word/footer1.xml'));
    }

    public function test_template_data_is_validated(): void
    {
        $this->expectException(ValidationException::class);

        Doc::template('invoice', ['items' => []])->pdf();
    }

    public function test_letter_body_is_split_into_paragraphs(): void
    {
        $data = Doc::templates()->get('letter')->sample();
        $data['body'] = "الفقرة الأولى\n\nالفقرة الثانية";

        $html = Doc::template('letter', $data)->locale('ar')->toHtml();

        $this->assertStringContainsString('<p>الفقرة الأولى</p>', $html);
        $this->assertStringContainsString('<p>الفقرة الثانية</p>', $html);
    }

    public function test_report_accepts_simple_columns_and_sums(): void
    {
        $html = Doc::template('report', [
            'title' => 'تقرير',
            'columns' => ['name' => 'الاسم', 'amount' => ['label' => 'المبلغ', 'format' => 'number']],
            'rows' => [['name' => 'أ', 'amount' => 1000], ['name' => 'ب', 'amount' => -250.5]],
            'sum' => ['amount'],
        ])->locale('ar')->toHtml();

        $this->assertStringContainsString('749.50', $html);
        $this->assertStringContainsString('<bdo dir="ltr">-250.50</bdo>', $html);
    }

    private function zipEntry(string $docx, string $entry): string
    {
        $file = tempnam(sys_get_temp_dir(), 'docx-test');
        file_put_contents($file, $docx);
        $zip = new \ZipArchive;
        $zip->open($file);
        $content = (string) $zip->getFromName($entry);
        $zip->close();
        @unlink($file);

        return $content;
    }
}
