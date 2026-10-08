<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Spatie\Browsershot\Browsershot;

/**
 * Runs only when Chrome is available: set DOC_CHROME_PATH to its binary
 * and install puppeteer (npm install puppeteer).
 */
class ChromiumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Browsershot::class) || ! getenv('DOC_CHROME_PATH')) {
            $this->markTestSkipped('Set DOC_CHROME_PATH to run the Chromium tests.');
        }

        config([
            'easy-pdf-word.pdf.fallback' => null,
            'easy-pdf-word.pdf.drivers.browsershot.chrome_path' => getenv('DOC_CHROME_PATH'),
            'easy-pdf-word.pdf.drivers.browsershot.no_sandbox' => true,
        ]);
    }

    public function test_bundled_templates_render_with_chromium(): void
    {
        foreach (['invoice', 'letter', 'report'] as $name) {
            $pdf = Doc::template($name, Doc::templates()->get($name)->sample())->locale('ar')->driver('chromium')->pdf();

            $this->assertSame('browsershot', $pdf->engine());
            $this->assertStringStartsWith('%PDF', $pdf->content());
            $this->keep("{$name}-ar-chromium", $pdf->content());
        }
    }

    public function test_scripts_do_not_run_unless_enabled(): void
    {
        if (! is_executable('/usr/bin/pdftotext')) {
            $this->markTestSkipped('Needs pdftotext.');
        }

        $html = '<p id="out">static</p><script>document.getElementById("out").textContent = "scripted"</script>';

        $this->assertStringContainsString('static', $this->text(Doc::html($html)->driver('chromium')->pdf()->content()));

        config(['easy-pdf-word.pdf.drivers.browsershot.javascript' => true]);
        app(PdfManager::class)->forgetDrivers();
        $this->assertStringContainsString('scripted', $this->text(Doc::html($html)->driver('chromium')->pdf()->content()));
    }

    public function test_footers_use_the_document_font(): void
    {
        if (! is_executable('/usr/bin/pdffonts')) {
            $this->markTestSkipped('Needs pdffonts.');
        }

        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, Doc::html('<p>مرحبا</p>')->locale('ar')->footer('<div>صفحة {page} من {pages}</div>')->driver('chromium')->pdf()->content());
        $fonts = (string) shell_exec('/usr/bin/pdffonts '.escapeshellarg($file));
        @unlink($file);

        $this->assertStringContainsString('Cairo', $fonts);
        $this->assertStringNotContainsString('DejaVu', $fonts);
    }

    public function test_watermark_and_password_with_chromium(): void
    {
        $pdf = Doc::template('invoice', Doc::templates()->get('invoice')->sample())->locale('ar')->driver('chromium')
            ->watermark('مسودة', color: '#B91C1C')->password('1234')->pdf();

        $this->assertSame('browsershot', $pdf->engine());
        $this->assertStringContainsString('/Encrypt', $pdf->content());
        $this->keep('invoice-ar-chromium-watermark-password', $pdf->content());
    }

    private function text(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, $pdf);
        $text = (string) shell_exec('/usr/bin/pdftotext '.escapeshellarg($file).' -');
        @unlink($file);

        return $text;
    }
}
