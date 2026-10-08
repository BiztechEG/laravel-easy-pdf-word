<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Tests\TestCase;
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
