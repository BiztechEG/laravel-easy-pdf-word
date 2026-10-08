<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class DocumentTest extends TestCase
{
    public function test_arabic_locale_makes_the_document_rtl(): void
    {
        $html = Doc::html('<p>مرحبا</p>')->locale('ar')->toHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString("font-family: 'cairo'", $html);
    }

    public function test_english_locale_is_ltr_and_direction_can_be_forced(): void
    {
        $this->assertStringContainsString('dir="ltr"', Doc::html('<p>Hello</p>')->locale('en')->toHtml());
        $this->assertStringContainsString('dir="rtl"', Doc::html('<p>Hello</p>')->locale('en')->rtl()->toHtml());
    }

    public function test_arabic_numerals_change_text_but_not_css(): void
    {
        $html = Doc::html('<p style="font-size: 12pt">فاتورة 1024</p>')->locale('ar')->numerals('arabic')->toHtml();

        $this->assertStringContainsString('فاتورة ١٠٢٤', $html);
        $this->assertStringContainsString('font-size: 12pt', $html);
    }

    public function test_arabic_separators_only_with_fonts_that_have_them(): void
    {
        $html = fn (string $font) => Doc::html('<p>1,250.50</p>')->locale('ar')->numerals('arabic')->font($font)->toHtml();

        $this->assertStringContainsString('١٬٢٥٠٫٥٠', $html('naskh'));
        $this->assertStringContainsString('١,٢٥٠.٥٠', $html('cairo'));
        $this->assertStringContainsString('١,٢٥٠.٥٠', $html('tajawal'));
    }

    public function test_formatted_numbers_are_read_whole(): void
    {
        $doc = new \BiztechEG\EasyPdfWord\Support\DocContext('en', 'ltr', 'cairo', [], 'latin', '');

        $this->assertSame('1,250.50', $doc->numberText('1,250.5'));
        $this->assertSame('1,250.50', (string) $doc->number('١٬٢٥٠٫٥'));
        $this->assertSame('one thousand two hundred fifty EGP and 50/100 only', $doc->inWords('1,250.50', 'EGP'));
    }

    public function test_a_data_key_named_doc_does_not_replace_the_context(): void
    {
        $html = Doc::template('letter', ['doc' => ['x' => 1]] + Doc::templates()->get('letter')->sample())->locale('ar')->toHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
    }

    public function test_full_html_documents_are_not_wrapped(): void
    {
        $html = Doc::html('<html><body>raw</body></html>')->toHtml();

        $this->assertSame('<html><body>raw</body></html>', $html);
    }

    public function test_blade_views_from_the_app_can_be_rendered(): void
    {
        $this->app['view']->addNamespace('tests', __DIR__.'/../fixtures');

        $pdf = Doc::view('tests::greeting', ['name' => 'سارة'])->locale('ar')->pdf();

        $this->assertStringStartsWith('%PDF', $pdf->content());
    }

    public function test_download_and_stream_responses(): void
    {
        $pdf = Doc::html('<p>مرحبا</p>')->locale('ar')->pdf();

        $download = $pdf->download('فاتورة-1024.pdf');
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''", $download->headers->get('Content-Disposition'));

        $this->assertStringContainsString('inline', $pdf->stream('invoice')->headers->get('Content-Disposition'));
    }

    public function test_save_to_a_disk(): void
    {
        Storage::fake('local');

        Doc::html('<p>مرحبا</p>')->locale('ar')->pdf()->save('docs/hello.pdf', 'local');

        Storage::disk('local')->assertExists('docs/hello.pdf');
    }

    public function test_blade_directives_and_helpers(): void
    {
        $this->assertSame('ألف ومائتان وخمسون جنيهاً وخمسون قرشاً', tafqeet(1250.5, 'EGP'));

        $compiled = $this->app['blade.compiler']->compileString("@tafqeet(5, 'EGP')");
        $this->assertStringContainsString('Arabic::tafqeet(5, \'EGP\')', $compiled);
    }

    public function test_download_names_with_slashes_and_percent_signs(): void
    {
        $pdf = Doc::html('<p>x</p>')->pdf('فاتورة/1');

        $this->assertStringContainsString('reports-2026.pdf', $pdf->download('reports/2026.pdf')->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', $pdf->download('100% done')->headers->get('Content-Disposition'));
        $this->assertStringContainsString('inline', $pdf->stream()->headers->get('Content-Disposition'));
    }

    public function test_images_are_only_read_from_allowed_folders(): void
    {
        $this->app['view']->addNamespace('tests', __DIR__.'/../fixtures');
        $this->app['config']->set('easy-pdf-word.images.paths', [__DIR__.'/../../resources']);
        $png = imagecreatetruecolor(2, 2);
        $outside = tempnam(sys_get_temp_dir(), 'img').'.png';
        imagepng($png, $outside);

        $html = Doc::view('tests::image', ['src' => $outside])->toHtml();
        $this->assertStringNotContainsString('data:image/png', $html);

        $html = Doc::view('tests::image', ['src' => __FILE__])->toHtml();
        $this->assertStringNotContainsString('data:', $html);

        $this->app['config']->set('easy-pdf-word.images.paths', [dirname($outside)]);
        $html = Doc::view('tests::image', ['src' => $outside])->toHtml();
        $this->assertStringContainsString('data:image/png;base64,', $html);

        @unlink($outside);
    }
}
