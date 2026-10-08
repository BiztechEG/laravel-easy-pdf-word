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

    public function test_short_margins_from_the_config_are_expanded(): void
    {
        config(['easy-pdf-word.pdf.margins' => [10, 20]]);

        $this->assertSame([10.0, 20.0, 10.0, 20.0], Doc::html('<p>x</p>')->options()->margins);
        $this->assertStringStartsWith('%PDF', Doc::html('<p>x</p>')->pdf()->content());
        $this->assertStringStartsWith('PK', Doc::make()->paragraph('x')->word()->content());
    }

    public function test_paper_sizes(): void
    {
        $this->assertSame([176, 250], Doc::html('<p>x</p>')->paper('B5')->options()->paperSize());
        $this->assertSame([297, 210], Doc::html('<p>x</p>')->paper('A4-L')->options()->paperSize());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown paper size [B6]');
        Doc::html('<p>x</p>')->paper('B6');
    }

    public function test_paper_sizes_in_mm_and_with_a_suffix_work_everywhere(): void
    {
        $this->assertSame([100.0, 150.0], Doc::html('<p>x</p>')->paper([100, 150])->options()->paperSize());

        config(['easy-pdf-word.pdf.paper' => 'A5-L']);
        $this->assertSame([210, 148], Doc::html('<p>x</p>')->options()->paperSize());

        $dir = sys_get_temp_dir().'/easy-pdf-word-tests/templates/label';
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/template.php', "<?php return ['title' => 'Label', 'paper' => [80, 200], 'fields' => []];");
        file_put_contents($dir.'/pdf.blade.php', '<p>label</p>');

        try {
            $document = Doc::template('label', []);

            $this->assertSame([80.0, 200.0], $document->options()->paperSize());
            $this->assertSame([200.0, 80.0], $document->landscape()->options()->paperSize());
            $this->assertStringStartsWith('%PDF', $document->pdf()->content());
        } finally {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        }
    }

    public function test_mpdf_keeps_the_right_and_left_margins_in_arabic_documents(): void
    {
        if (! is_executable('/usr/bin/pdftotext')) {
            $this->markTestSkipped('pdftotext is not installed.');
        }

        foreach (['ar' => 'كلمة عربية ', 'en' => 'word text '] as $locale => $words) {
            $file = tempnam(sys_get_temp_dir(), 'pdf');
            file_put_contents($file, Doc::html('<p>'.str_repeat($words, 80).'</p>')->locale($locale)->margins(10, 10, 10, 50)->pdf()->content());
            preg_match_all('/xMin="([\d.]+)" yMin="[\d.]+" xMax="([\d.]+)"/', (string) shell_exec('/usr/bin/pdftotext -bbox '.escapeshellarg($file).' -'), $x);
            @unlink($file);

            // Lines start at the margin of their side: 10 mm from the right of
            // an A4 page (566.9 pt) in Arabic, 50 mm from the left (141.7 pt) in English.
            $locale === 'ar'
                ? $this->assertEqualsWithDelta(566.9, max(array_map('floatval', $x[2])), 2)
                : $this->assertEqualsWithDelta(141.7, min(array_map('floatval', $x[1])), 2);
        }
    }

    public function test_a_misspelt_engine_name_is_an_error_not_a_fallback(): void
    {
        Log::shouldReceive('warning')->never();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown PDF engine [chromuim]');

        Doc::html('<p>x</p>')->driver('chromuim')->pdf()->content();
    }

    public function test_mpdf_reads_a_font_again_when_its_file_changes(): void
    {
        $font = sys_get_temp_dir().'/easy-pdf-word-tests/MyFont.ttf';
        @mkdir(dirname($font), 0775, true);
        copy(dirname(__DIR__, 2).'/resources/fonts/Cairo-Regular.ttf', $font);

        $fonts = new \BiztechEG\EasyPdfWord\Fonts\FontRegistry(['my-font' => ['regular' => $font]]);
        $before = $fonts->signature();
        touch($font, time() + 60);
        clearstatcache();

        $this->assertNotSame($before, $fonts->signature());
        @unlink($font);
    }

    public function test_the_word_allah_can_be_copied_from_a_cairo_pdf(): void
    {
        if (! is_executable('/usr/bin/pdftotext')) {
            $this->markTestSkipped('pdftotext is not installed.');
        }

        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, Doc::html('<p>عبد الله</p>')->locale('ar')->pdf()->content());
        $text = (string) shell_exec('/usr/bin/pdftotext '.escapeshellarg($file).' -');
        @unlink($file);

        // The ligature is copied as ﷲ, which search and Normalizer read as الله.
        $this->assertStringContainsString('الله', \Normalizer::normalize($text, \Normalizer::FORM_KC));
    }

    public function test_chromium_gets_the_fonts_named_in_the_css(): void
    {
        $driver = new FakeDriver(cssFonts: true);
        Doc::extend('css', fn () => $driver);

        Doc::html('<p style="font-family: \'naskh\', serif">نص</p>')->locale('ar')->driver('css')->pdf()->content();

        $this->assertStringContainsString("@font-face{font-family:'cairo'", $driver->html);
        $this->assertStringContainsString("@font-face{font-family:'naskh'", $driver->html);
        $this->assertStringNotContainsString("@font-face{font-family:'tajawal'", $driver->html);
    }

    public function test_a_paper_size_in_mm_needs_two_positive_numbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[width, height] in mm');

        Doc::html('<p>x</p>')->paper([100]);
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

    public function test_gotenberg_footers_carry_the_document_font(): void
    {
        $this->fakeGotenberg();

        Doc::html('<p>مرحبا</p>')->locale('ar')->footer('<div>صفحة {page}</div>')->driver('gotenberg')->pdf()->content();

        Http::assertSent(function (Request $request) {
            $footer = collect($request->data())->firstWhere('filename', 'footer.html')['contents'];

            return str_contains($footer, "@font-face{font-family:'cairo'") && str_contains($footer, "font-family:'cairo',sans-serif");
        });
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

    public function test_custom_font_files_with_the_same_name_are_refused(): void
    {
        $dir = sys_get_temp_dir().'/easy-pdf-word-tests/fonts';
        @mkdir($dir.'/fa', 0775, true);
        @mkdir($dir.'/fb', 0775, true);
        copy(__DIR__.'/../../resources/fonts/Cairo-Regular.ttf', $dir.'/fa/Regular.ttf');
        copy(__DIR__.'/../../resources/fonts/Tajawal-Regular.ttf', $dir.'/fb/Regular.ttf');

        $fonts = app(\BiztechEG\EasyPdfWord\Fonts\FontRegistry::class)
            ->register('fa', ['regular' => $dir.'/fa/Regular.ttf'])
            ->register('fb', ['regular' => $dir.'/fb/Regular.ttf']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('have the same name');

        $fonts->forMpdf();
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
