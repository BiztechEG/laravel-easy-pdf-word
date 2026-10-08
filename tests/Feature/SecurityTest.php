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

    private function context(?array $imagePaths = [], bool|array $remoteImages = false): DocContext
    {
        return new DocContext('en', 'ltr', 'cairo', [], 'latin', '', imagePaths: $imagePaths, remoteImages: $remoteImages);
    }
}
