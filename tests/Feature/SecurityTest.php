<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;

class SecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['easy_pdf_word_lang_probe']);

        parent::tearDown();
    }

    public function test_translations_are_only_read_for_language_codes(): void
    {
        $template = Doc::templates()->get('letter');

        $this->assertSame([], $template->translations('../../../../tests/fixtures/probe'));
        $this->assertArrayNotHasKey('easy_pdf_word_lang_probe', $GLOBALS);
        $this->assertNotSame([], $template->translations('ar_EG'));
    }
}
