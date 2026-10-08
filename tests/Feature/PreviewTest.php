<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class PreviewTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('easy-pdf-word.preview.enabled', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewDocPreview', fn ($user = null) => true);
    }

    public function test_outside_local_the_page_needs_the_view_doc_preview_gate(): void
    {
        Gate::define('viewDocPreview', fn ($user = null) => false);

        $this->get('/doc-preview')->assertForbidden();
        $this->get('/doc-preview/receipt')->assertForbidden();
    }

    public function test_without_a_gate_the_page_is_closed_outside_local(): void
    {
        $this->app->forgetInstance(\Illuminate\Contracts\Auth\Access\Gate::class);
        Gate::clearResolvedInstances();

        $this->get('/doc-preview')->assertForbidden();
    }

    public function test_in_local_the_page_is_open_without_a_gate(): void
    {
        Gate::define('viewDocPreview', fn ($user = null) => false);
        $this->app['env'] = 'local';

        $this->get('/doc-preview')->assertOk();
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

    public function test_unexpected_query_values_fall_back_to_the_defaults(): void
    {
        $html = $this->get('/doc-preview/letter?format=html&locale=../../../../tests/fixtures/probe&numerals[]=x&engine=evil')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html->getContent());
        $this->get('/doc-preview/letter?format=html&locale[]=ar&numerals=klingon')->assertOk();
        $this->get('/doc-preview/letter?format=html&locale=fr')->assertOk()->assertSee('<html lang="ar"', false);
    }

    public function test_unknown_templates_are_not_found(): void
    {
        $this->get('/doc-preview/nope')->assertNotFound();
        $this->get('/doc-preview/..')->assertNotFound();
    }
}
