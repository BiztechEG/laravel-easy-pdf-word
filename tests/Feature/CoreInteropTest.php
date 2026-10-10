<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Support\Html;
use BiztechEG\EasyPdfWord\Support\HtmlString;
use BiztechEG\EasyPdfWord\Tests\TestCase;

/**
 * The core package (biztecheg/easy-pdf-word) inside a Laravel app: its
 * escaping matches Laravel's and its HTML stays raw in Blade.
 */
class CoreInteropTest extends TestCase
{
    public function test_escape_matches_laravel(): void
    {
        foreach (['<b>"Tom" & \'Jerry\'</b>', 'شركة &amp; أخرى', '', null, 12.5, "bad \xC3("] as $value) {
            $this->assertSame(e($value), Html::escape($value), var_export($value, true));
        }
    }

    public function test_html_string_is_raw_in_blade(): void
    {
        $html = new HtmlString('<bdo dir="ltr">+20 100</bdo>');

        $this->assertInstanceOf(\Illuminate\Contracts\Support\Htmlable::class, $html);
        $this->assertInstanceOf(\Illuminate\Support\HtmlString::class, $html);
        $this->assertSame('<bdo dir="ltr">+20 100</bdo>', e($html));
    }

    public function test_the_new_template_starter_works_without_laravel(): void
    {
        $docs = EasyPdfWord::create([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
            'templates' => ['paths' => [__DIR__.'/../../resources/stubs']],
        ]);
        $document = $docs->template('template', ['title' => 'قائمة <التعبئة>'])->locale('ar');

        $html = $document->toHtml();
        $this->assertStringContainsString('<h1>قائمة &lt;التعبئة&gt;</h1>', $html);
        $this->assertStringContainsString('<title>قائمة &lt;التعبئة&gt;</title>', $html);
        $this->assertStringContainsString('{page} / {pages}', $document->options()->footer);
        $this->assertStringStartsWith('%PDF', $document->pdf()->content());
        $this->assertStringStartsWith('PK', $document->word()->content());
    }
}
