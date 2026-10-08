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
        $this->assertSame(['invoice', 'letter', 'report'], array_keys(Doc::templates()->all()));
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
}
