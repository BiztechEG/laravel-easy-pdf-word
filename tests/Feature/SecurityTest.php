<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
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
}
