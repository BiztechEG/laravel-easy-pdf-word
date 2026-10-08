<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class SecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['easy_pdf_word_lang_probe']);

        parent::tearDown();
    }

    public static function badLocales(): array
    {
        return [
            'parent folders' => ['../../../../tests/fixtures/probe'],
            'absolute path' => ['/etc/passwd'],
            'null byte' => ["ar\0"],
            'markup' => ['ar"><script>'],
            'empty' => [''],
        ];
    }

    #[DataProvider('badLocales')]
    public function test_locales_that_are_not_language_codes_are_rejected(string $locale): void
    {
        $this->expectException(InvalidArgumentException::class);

        Doc::template('letter')->locale($locale);
    }

    public function test_good_locales_are_accepted(): void
    {
        foreach (['ar', 'en', 'ar-EG', 'ar_SA', 'zh-Hant-TW', 'fil'] as $locale) {
            $this->assertStringContainsString('<html lang="'.$locale.'"', Doc::html('<p>x</p>')->locale($locale)->toHtml());
        }
    }

    public function test_font_names_cannot_carry_markup_or_css(): void
    {
        $this->assertStringContainsString("font-family: 'naskh'", Doc::html('<p>x</p>')->font('naskh')->toHtml());

        $this->expectException(InvalidArgumentException::class);

        Doc::html('<p>x</p>')->font('cairo;"><script>alert(1)</script>');
    }

    public function test_translations_are_only_read_for_language_codes(): void
    {
        $template = Doc::templates()->get('letter');

        $this->assertSame([], $template->translations('../../../../tests/fixtures/probe'));
        $this->assertArrayNotHasKey('easy_pdf_word_lang_probe', $GLOBALS);
        $this->assertNotSame([], $template->translations('ar_EG'));
    }

    public static function unreadableImageSources(): array
    {
        return [
            'ftp' => ['ftp://example.com/logo.png'],
            'phar' => ['phar:///tmp/archive.phar/logo.png'],
            'file scheme' => ['file:///etc/passwd'],
            'php filter' => ['php://filter/resource=/etc/passwd'],
            'network share' => ['\\\\server\\share\\logo.png'],
            'network share with slashes' => ['//server/share/logo.png'],
            'null byte' => ["logo.png\0.php"],
        ];
    }

    #[DataProvider('unreadableImageSources')]
    public function test_images_are_never_read_through_other_schemes_or_network_paths(string $source): void
    {
        $this->assertNull($this->context(imagePaths: null)->image($source));
    }

    public static function svgsThatReachOutside(): array
    {
        return [
            'image link' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://127.0.0.1/x.png" width="9" height="9"/></svg>'],
            'local file' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="/etc/hosts"/></svg>'],
            'css url' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill: url(http://127.0.0.1/a)"/></svg>'],
            'use another file' => ['<svg xmlns="http://www.w3.org/2000/svg"><use href="other.svg#a"/></svg>'],
            'entity' => ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>'],
        ];
    }

    #[DataProvider('svgsThatReachOutside')]
    public function test_svgs_that_point_outside_themselves_are_refused(string $svg): void
    {
        $doc = $this->context(imagePaths: [sys_get_temp_dir()]);
        $file = sys_get_temp_dir().'/easy-pdf-word-test.svg';
        file_put_contents($file, $svg);

        try {
            $this->assertNull($doc->image('data:image/svg+xml;base64,'.base64_encode($svg)));
            $this->assertNull($doc->image('data:image/svg+xml;utf8,'.$svg));
            $this->assertNull($doc->image('data:image/svg+xml,'.rawurlencode($svg)));
            $this->assertNull($doc->image($file));
        } finally {
            @unlink($file);
        }
    }

    public function test_self_contained_svgs_are_kept(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><linearGradient id="g"/></defs>'
            .'<rect id="r" width="9" height="9" fill="url(#g)"/><use xlink:href="#r" x="10"/></svg>';

        $this->assertNotNull($this->context()->image('data:image/svg+xml;base64,'.base64_encode($svg)));
    }

    public function test_image_urls_are_ignored_unless_allowed(): void
    {
        $this->assertNull($this->context()->image('https://example.com/logo.png'));
        $this->assertStringNotContainsString('169.254.169.254', Doc::make()->image('http://169.254.169.254/x.png')->toHtml());

        $this->assertSame('https://example.com/logo.png', $this->context(remoteImages: true)->image('https://example.com/logo.png'));
    }

    public function test_image_urls_can_be_allowed_for_some_hosts(): void
    {
        $doc = $this->context(remoteImages: ['cdn.example.com', '*.example.org']);

        $this->assertNotNull($doc->image('https://cdn.example.com/logo.png'));
        $this->assertNotNull($doc->image('https://images.example.org/logo.png'));
        $this->assertNotNull($doc->image('HTTPS://CDN.EXAMPLE.COM/logo.png'));
        $this->assertNull($doc->image('https://example.org/logo.png'));
        $this->assertNull($doc->image('https://cdn.example.com.evil.test/logo.png'));
        $this->assertNull($doc->image('https://cdn.example.com@evil.test/logo.png'));
        $this->assertNull($doc->image('http://localhost/logo.png'));
    }

    public function test_image_hosts_can_come_from_the_environment(): void
    {
        $this->app['config']->set('easy-pdf-word.images.remote', 'cdn.example.com, *.example.org');

        $html = Doc::make()->image('https://cdn.example.com/a.png')->image('https://evil.test/b.png')->toHtml();

        $this->assertStringContainsString('https://cdn.example.com/a.png', $html);
        $this->assertStringNotContainsString('evil.test', $html);
    }

    public function test_images_that_cannot_be_used_are_left_out_of_table_cells(): void
    {
        $html = Doc::make()->table([[['lines' => ['x', ['image' => '/etc/passwd.png']]], ['image' => 'https://evil.test/a.png']]])->toHtml();

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_builder_colours_cannot_add_css(): void
    {
        $html = Doc::make()
            ->paragraph('x', ['color' => 'red; background-image: url(http://evil.test/a.png)', 'background' => '#FFF;position:fixed'])
            ->table([[['text' => 'y', 'background' => 'url(http://evil.test/b.png)', 'border' => 'red;background:url(http://evil.test/e)']]], ['border_color' => 'red; background: url(http://evil.test/c)'])
            ->line('#000; background: url(http://evil.test/d)')
            ->toHtml();

        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringNotContainsString('position:fixed', $html);
        $this->assertStringContainsString('color: #0F766E', Doc::make()->paragraph('x', ['color' => '#0F766E'])->toHtml());
        $this->assertStringContainsString('color: rgb(10, 20, 30)', Doc::make()->paragraph('x', ['color' => 'rgb(10, 20, 30)'])->toHtml());
    }

    public function test_theme_colours_cannot_add_css(): void
    {
        $html = Doc::template('letter', Doc::templates()->get('letter')->sample())
            ->theme(['text' => 'red; } body { background: url(http://evil.test/) } p {', 'primary' => 'blue'])
            ->toHtml();

        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringContainsString('color: #1F2937', $html);
        $this->assertStringContainsString('blue', $html);
    }

    public function test_report_decimals_from_data_cannot_build_huge_numbers(): void
    {
        $data = [
            'title' => 'x',
            'columns' => [['key' => 'amount', 'label' => 'Amount', 'format' => 'number', 'decimals' => 100000000]],
            'rows' => [['amount' => 1.5]],
        ];

        $this->assertLessThan(20000, strlen(Doc::template('report', $data)->locale('en')->toHtml()));
        $this->assertSame('1.5000000000', $this->context()->numberText(1.5, 1000));
        $this->assertSame('2', $this->context()->numberText(1.5, -3));
    }

    private function context(?array $imagePaths = [], bool|array $remoteImages = false): DocContext
    {
        return new DocContext('en', 'ltr', 'cairo', [], 'latin', '', imagePaths: $imagePaths, remoteImages: $remoteImages);
    }
}
