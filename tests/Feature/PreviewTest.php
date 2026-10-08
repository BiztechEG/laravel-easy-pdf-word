<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Tests\TestCase;

class PreviewTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('easy-pdf-word.preview.enabled', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    public function test_the_preview_page_lists_the_templates(): void
    {
        $this->get('/doc-preview')
            ->assertOk()
            ->assertSee('Document templates')
            ->assertSee('eg-invoice')
            ->assertSee('quotation')
            ->assertSee('receipt');
    }

    public function test_a_template_previews_as_pdf_html_and_word(): void
    {
        $pdf = $this->get('/doc-preview/receipt?locale=ar');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get('/doc-preview/quotation?locale=en&format=html')->assertOk()->assertSee('Price Quotation');

        $word = $this->get('/doc-preview/eg-invoice?locale=ar&numerals=arabic&format=docx');
        $word->assertOk();
        $this->assertStringContainsString('attachment', $word->headers->get('Content-Disposition'));
    }

    public function test_unknown_templates_are_not_found(): void
    {
        $this->get('/doc-preview/nope')->assertNotFound();
        $this->get('/doc-preview/..')->assertNotFound();
    }
}
