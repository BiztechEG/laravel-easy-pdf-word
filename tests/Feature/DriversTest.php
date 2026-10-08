<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DriversTest extends TestCase
{
    public function test_default_driver_comes_from_config(): void
    {
        config(['easy-pdf-word.pdf.driver' => 'fake']);
        Doc::extend('fake', fn () => new FakeDriver);

        $this->assertSame('fake', Doc::html('<p>x</p>')->pdf()->engine());
    }

    public function test_driver_can_be_switched_per_document(): void
    {
        Doc::extend('fake', fn () => new FakeDriver);

        $this->assertSame('fake', Doc::html('<p>x</p>')->driver('fake')->pdf()->engine());
        $this->assertSame('mpdf', Doc::html('<p>x</p>')->pdf()->engine());
    }

    public function test_engine_names_ignore_case(): void
    {
        Doc::extend('MyEngine', fn () => new FakeDriver);

        $this->assertSame('myengine', Doc::html('<p>x</p>')->driver('MyEngine')->pdf()->engine());
    }

    public function test_chromium_is_an_alias_of_browsershot(): void
    {
        $this->assertSame('browsershot', Doc::pdfManager()->normalize('chromium'));
    }

    public function test_falls_back_when_the_engine_fails(): void
    {
        Doc::extend('broken', fn () => new FakeDriver(fail: true));

        $pdf = Doc::html('<p>مرحبا</p>')->locale('ar')->driver('broken')->pdf();

        $this->assertSame('mpdf', $pdf->engine());
        $this->assertStringStartsWith('%PDF', $pdf->content());
    }

    public function test_falls_back_when_the_engine_is_not_installed(): void
    {
        Doc::extend('missing', fn () => new FakeDriver(available: false));

        $this->assertSame('mpdf', Doc::html('<p>x</p>')->driver('missing')->pdf()->engine());
    }

    public function test_no_fallback_rethrows(): void
    {
        config(['easy-pdf-word.pdf.fallback' => null]);
        Doc::extend('broken', fn () => new FakeDriver(fail: true));

        $this->expectException(RuntimeException::class);

        Doc::html('<p>x</p>')->driver('broken')->pdf()->content();
    }

    public function test_a_missing_fallback_engine_does_not_hide_the_real_error(): void
    {
        config(['easy-pdf-word.pdf.fallback' => 'missing']);
        Doc::extend('broken', fn () => new FakeDriver(fail: true));
        Doc::extend('missing', fn () => new FakeDriver(available: false));

        $this->expectExceptionMessage('Engine crashed');

        Doc::html('<p>x</p>')->driver('broken')->pdf()->content();
    }

    public function test_a_missing_engine_says_what_to_install(): void
    {
        config(['easy-pdf-word.pdf.fallback' => null, 'easy-pdf-word.pdf.drivers.gotenberg.url' => '']);

        $this->expectExceptionMessage('DOC_GOTENBERG_URL');

        Doc::html('<p>x</p>')->driver('gotenberg')->pdf()->content();
    }

    public function test_css_font_engines_get_embedded_fonts(): void
    {
        $driver = new FakeDriver(cssFonts: true);
        Doc::extend('fake', fn () => $driver);

        Doc::html('<p>مرحبا</p>')->locale('ar')->driver('fake')->pdf()->content();

        $this->assertStringContainsString("@font-face{font-family:'cairo'", $driver->html);
        $this->assertSame('rtl', $driver->options->direction);
    }

    public function test_page_options_reach_the_engine(): void
    {
        $driver = new FakeDriver;
        Doc::extend('fake', fn () => $driver);

        Doc::html('<p>x</p>')->driver('fake')->paper('A5')->landscape()->margins(10, 5)
            ->footer('{page}/{pages}')->pdf()->content();

        $this->assertSame([210, 148], $driver->options->paperSize());
        $this->assertSame([10.0, 5.0, 10.0, 5.0], $driver->options->margins);
        $this->assertSame('{page}/{pages}', $driver->options->footer);
    }

    public function test_template_errors_are_not_hidden_by_the_fallback(): void
    {
        Doc::extend('fake', fn () => new FakeDriver);
        $this->app['view']->addNamespace('tests', __DIR__.'/../fixtures');

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/missing/i');

        Log::shouldReceive('warning')->never();

        Doc::view('tests::broken')->driver('fake')->pdf()->content();
    }

    public function test_a_given_footer_follows_the_digits(): void
    {
        $options = Doc::html('<p>x</p>')->locale('ar')->numerals('arabic')->footer('<div>صفحة {page} من {pages} - نسخة 2</div>')->options();

        $this->assertSame('<div>صفحة {page} من {pages} - نسخة ٢</div>', $options->footer);
    }

    public function test_mpdf_page_numbers_follow_the_digits(): void
    {
        $document = Doc::html('<p>مرحبا</p>')->locale('ar')->numerals('arabic')->footer('<div>صفحة {page} من {pages}</div>');
        $options = $document->options();
        $config = (new \ReflectionMethod(MpdfDriver::class, 'mpdfConfig'))->invoke(app(PdfManager::class)->driver('mpdf'), $options);

        $this->assertSame('arabic', $options->numerals);
        $this->assertSame('arabic-indic', $config['defaultPageNumStyle']);

        if (is_executable('/usr/bin/pdftotext')) {
            $file = tempnam(sys_get_temp_dir(), 'pdf');
            file_put_contents($file, $document->pdf()->content());
            $text = (string) shell_exec('/usr/bin/pdftotext '.escapeshellarg($file).' -');
            @unlink($file);

            $this->assertStringContainsString('١', $text);
            $this->assertStringNotContainsString('1', $text);
        }
    }

    public function test_gotenberg_pages_run_no_scripts(): void
    {
        $this->fakeGotenberg();

        $this->assertSame('gotenberg', Doc::html('<p>x</p>')->driver('gotenberg')->pdf()->engine());
        Http::assertSent(fn (Request $request) => preg_match('/<head[^>]*><meta http-equiv="Content-Security-Policy" content="script-src \'none\'">/', $this->gotenbergPage($request)) === 1);
    }

    public function test_gotenberg_pages_can_run_scripts_when_allowed(): void
    {
        $this->fakeGotenberg();
        config(['easy-pdf-word.pdf.drivers.gotenberg.javascript' => true]);

        Doc::html('<p>x</p>')->driver('gotenberg')->pdf()->content();
        Http::assertSent(fn (Request $request) => ! str_contains($this->gotenbergPage($request), 'Content-Security-Policy'));
    }

    public function test_a_gotenberg_answer_that_is_not_a_pdf_is_a_failure(): void
    {
        $this->fakeGotenberg('<html><body>Please sign in</body></html>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a PDF');

        Doc::html('<p>x</p>')->driver('gotenberg')->pdf()->content();
    }

    public function test_mpdf_renders_html_longer_than_the_pcre_backtrack_limit(): void
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '100000');

        try {
            $pdf = Doc::html('<!-- '.str_repeat('x', 150_000).' --><p>x</p>')->pdf()->content();
        } finally {
            $this->assertSame('100000', ini_get('pcre.backtrack_limit'));
            ini_set('pcre.backtrack_limit', $limit);
        }

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_mpdf_works_in_a_private_folder_per_system_user(): void
    {
        $dir = MpdfDriver::tempDir();

        $this->assertStringStartsWith(sys_get_temp_dir().'/easy-pdf-word-', $dir);
        $this->assertSame(0700, fileperms($dir) & 0777);
        $this->assertSame(sys_get_temp_dir().'/easy-pdf-word-tests/mpdf', MpdfDriver::tempDir(sys_get_temp_dir().'/easy-pdf-word-tests/mpdf'));
    }

    public function test_an_unusable_mpdf_folder_names_the_setting(): void
    {
        config(['easy-pdf-word.pdf.fallback' => null, 'easy-pdf-word.pdf.drivers.mpdf.temp_dir' => '/dev/null/mpdf']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('easy-pdf-word.pdf.drivers.mpdf.temp_dir');

        Doc::html('<p>x</p>')->pdf()->content();
    }

    public function test_a_failed_chromium_render_leaves_no_page_in_the_temp_folder(): void
    {
        config(['easy-pdf-word.pdf.fallback' => null, 'easy-pdf-word.pdf.drivers.browsershot.chrome_path' => '/nonexistent/chrome', 'easy-pdf-word.pdf.drivers.browsershot.timeout' => 5]);
        $marker = 'secret-'.bin2hex(random_bytes(6));

        try {
            Doc::html("<p>{$marker}</p>")->driver('chromium')->pdf()->content();
        } catch (\Throwable) {
            // Expected: there is no Chrome at that path.
        }

        $pages = array_merge(glob(sys_get_temp_dir().'/*/index.html'), glob(sys_get_temp_dir().'/*/*/index.html'));
        $this->assertSame([], array_filter($pages, fn ($page) => str_contains((string) @file_get_contents($page), $marker)));
    }

    public function test_mpdf_renders_landscape_pages(): void
    {
        $pdf = Doc::html('<p>x</p>')->landscape()->pdf()->content();

        // A4 landscape is 842 x 595 points.
        $this->assertMatchesRegularExpression('/\/MediaBox \[0 0 841\.8\d* 595\.2\d*\]/', $pdf);
    }

    private function fakeGotenberg(string $body = '%PDF-1.7 fake'): void
    {
        config(['easy-pdf-word.pdf.fallback' => null, 'easy-pdf-word.pdf.drivers.gotenberg.url' => 'http://gotenberg.test']);
        Http::fake(['gotenberg.test/*' => Http::response($body)]);
    }

    private function gotenbergPage(Request $request): string
    {
        return collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }
}

class FakeDriver implements PdfDriver
{
    public ?string $html = null;

    public ?PdfOptions $options = null;

    public function __construct(
        private bool $fail = false,
        private bool $available = true,
        private bool $cssFonts = false,
    ) {}

    public function render(string $html, PdfOptions $options): string
    {
        if ($this->fail) {
            throw new RuntimeException('Engine crashed');
        }

        $this->html = $html;
        $this->options = $options;

        return '%PDF-fake';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function usesCssFonts(): bool
    {
        return $this->cssFonts;
    }
}
