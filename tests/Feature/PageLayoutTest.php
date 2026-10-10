<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use BiztechEG\EasyPdfWord\View\PageLayout;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The plain PHP page layout must print exactly what the <x-doc::layout>
 * Blade component prints, so templates look the same in and out of Laravel.
 */
class PageLayoutTest extends TestCase
{
    public static function contexts(): array
    {
        return [
            'arabic, mPDF' => [new DocContext('ar', 'rtl', 'cairo', ['text' => '#111111', 'muted' => '#222222'], 'latin', 'mpdf')],
            'english, Chromium fonts' => [new DocContext('en', 'ltr', 'naskh', [], 'latin', 'chromium', "@font-face{font-family:'naskh';src:url(data:font/ttf;base64,AAAA)}")],
            'odd theme values' => [new DocContext('ar_EG', 'rtl', 'tajawal', ['text' => '"red"<', 'muted' => "a & b"], 'arabic', 'x')],
        ];
    }

    #[DataProvider('contexts')]
    public function test_matches_the_blade_layout(DocContext $doc): void
    {
        $body = "  \n <p>مرحبا & أهلاً</p>\n<table><tr><td>1</td></tr></table> \n\n";

        $this->assertSame(
            view()->file($this->bladeFile("<x-doc::layout :doc=\"\$doc\">\n{!! \$body !!}\n</x-doc::layout>\n"), ['doc' => $doc, 'body' => $body])->render(),
            PageLayout::render($doc, $body),
        );
    }

    #[DataProvider('contexts')]
    public function test_matches_the_blade_layout_with_a_title_and_styles(DocContext $doc): void
    {
        $file = $this->bladeFile(<<<'BLADE'
            <x-doc::layout :doc="$doc" :title="$title">
                <x-slot:styles>
                    <style>.total { font-weight: bold; }</style>
                </x-slot:styles>
                {!! $body !!}
            </x-doc::layout>
            BLADE);

        $data = ['doc' => $doc, 'title' => 'فاتورة "1" & <2>', 'body' => "<p>نص</p>\n"];

        $this->assertSame(
            view()->file($file, $data)->render(),
            PageLayout::render($doc, $data['body'], $data['title'], "\n        <style>.total { font-weight: bold; }</style>\n    "),
        );
    }

    private function bladeFile(string $source): string
    {
        $file = sys_get_temp_dir().'/easy-pdf-word-tests/layout-'.md5($source).'.blade.php';
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, $source);

        return $file;
    }
}
