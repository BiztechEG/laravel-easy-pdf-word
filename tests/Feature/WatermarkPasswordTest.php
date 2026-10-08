<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use InvalidArgumentException;
use LogicException;
use Mpdf\Mpdf;

class WatermarkPasswordTest extends TestCase
{
    public function test_mpdf_draws_the_watermark_on_every_page(): void
    {
        $html = '<p>صفحة ١</p><pagebreak /><p>صفحة ٢</p>';
        $plain = Doc::html($html)->locale('ar')->pdf()->content();
        $marked = Doc::html($html)->locale('ar')->watermark('مسودة', opacity: 0.15, color: '#B91C1C')->pdf()->content();

        $this->keep('watermark-ar', $marked);
        $this->assertStringNotContainsString('/ca 0.15', $plain);
        $this->assertStringContainsString('/ca 0.15', $marked);
        $this->assertMatchesRegularExpression('/Pages:\s+2\n/', $this->info($marked));
    }

    public function test_watermark_values_are_checked(): void
    {
        $options = Doc::html('<p>x</p>')->watermark(' نسخة 2026 ', opacity: 5, color: 'red;background:url(x)')->numerals('arabic')->options();

        $this->assertSame(['text' => 'نسخة ٢٠٢٦', 'opacity' => 1.0, 'color' => '#000000'], $options->watermark);

        $this->expectException(InvalidArgumentException::class);
        Doc::html('<p>x</p>')->watermark('  ');
    }

    public function test_chromium_watermark_is_a_fixed_escaped_element(): void
    {
        $options = new PdfOptions(direction: 'rtl', font: 'cairo', watermark: ['text' => '<b>مسودة</b>', 'opacity' => 0.2, 'color' => '#B91C1C']);
        $html = Watermark::inject('<html><body><p>نص</p></body></html>', $options);

        $this->assertMatchesRegularExpression('/<p>نص<\/p><div aria-hidden="true" style="position: fixed;.*<\/div><\/div><\/body><\/html>$/u', $html);
        $this->assertStringContainsString('&lt;b&gt;مسودة&lt;/b&gt;', $html);
        $this->assertStringContainsString('rotate(-45deg)', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('color: #B91C1C; opacity: 0.2;', $html);
        $this->assertSame('<p>x</p>', Watermark::inject('<p>x</p>', new PdfOptions));
    }

    public function test_mpdf_encrypts_with_the_password(): void
    {
        $pdf = Doc::html('<p>سري</p><p>secret text</p>')->locale('ar')->password('1234')->pdf()->content();

        $this->keep('password-1234', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringContainsString('secret text', $this->text($pdf, '1234'));
        $this->assertSame('', $this->text($pdf));
    }

    public function test_an_owner_password_can_unlock_a_file_that_opens_without_one(): void
    {
        $pdf = Doc::html('<p>open</p>')->password('', owner: 'owner-secret', allow: ['print'])->pdf()->content();

        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringContainsString('open', $this->text($pdf));
    }

    public function test_other_engines_get_the_password_afterwards(): void
    {
        Doc::extend('plain', fn () => new PlainPdfDriver);

        $pdf = Doc::html('<p>from another engine</p>')->driver('plain')->landscape()->password('1234')->pdf();

        $this->assertSame('plain', $pdf->engine());
        $this->assertStringContainsString('/Encrypt', $pdf->content());
        $this->assertStringContainsString('from another engine', $this->text($pdf->content(), '1234'));
        $this->assertSame('', $this->text($pdf->content()));
        $this->assertMatchesRegularExpression('/Page size:\s+841.89 x 595.28 pts \(A4\)/', $this->info($pdf->content(), '1234'));
    }

    public function test_unknown_permissions_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown PDF permission [everything]');

        Doc::html('<p>x</p>')->password('1234', allow: ['print', 'everything']);
    }

    public function test_word_files_cannot_take_a_password(): void
    {
        $this->expectException(LogicException::class);

        Doc::make()->paragraph('سري')->password('1234')->word();
    }

    public function test_word_files_are_made_without_the_watermark(): void
    {
        $word = Doc::make()->paragraph('نص')->locale('ar')->watermark('مسودة')->word()->content();

        $this->assertStringStartsWith('PK', $word);
    }

    private function text(string $pdf, ?string $password = null): string
    {
        return $this->poppler('pdftotext', $pdf, $password, '-');
    }

    private function info(string $pdf, ?string $password = null): string
    {
        return $this->poppler('pdfinfo', $pdf, $password);
    }

    private function poppler(string $tool, string $pdf, ?string $password, string ...$after): string
    {
        if (! is_executable("/usr/bin/{$tool}")) {
            $this->markTestSkipped("Needs {$tool}.");
        }

        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, $pdf);
        $command = array_merge([$tool], $password === null ? [] : ['-upw', $password], [$file], $after);
        $output = shell_exec(implode(' ', array_map('escapeshellarg', $command)).' 2>/dev/null');
        unlink($file);

        return (string) $output;
    }
}

/** An engine that knows nothing about passwords. */
class PlainPdfDriver implements PdfDriver
{
    public function render(string $html, PdfOptions $options): string
    {
        $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf', 'orientation' => $options->isLandscape() ? 'L' : 'P']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function usesCssFonts(): bool
    {
        return false;
    }
}
