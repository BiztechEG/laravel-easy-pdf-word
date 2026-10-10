<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The same document made with EasyPdfWord::create() and with the Doc facade
 * must give the same HTML, header, footer and settings.
 */
class PlainPhpParityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('easy-pdf-word.templates.paths', [__DIR__.'/../fixtures/plain-templates']);
    }

    public static function documents(): array
    {
        return [
            'code' => [fn ($docs) => $docs->make()->heading('تقرير')->paragraph('نص')->table([['أ', 'ب'], ['1', '2']])],
            'html' => [fn ($docs) => $docs->html('<p>مرحبا <b>1</b></p>')->title('عنوان')],
            'plain template' => [fn ($docs) => $docs->template('note', ['to' => 'أحمد', 'lines' => ['سطر 1']])],
            'code layout template' => [fn ($docs) => $docs->template('receipt', $docs->templates()->get('receipt')->sample())],
        ];
    }

    #[DataProvider('documents')]
    public function test_plain_php_matches_laravel(Closure $make): void
    {
        $plain = EasyPdfWord::create(config('easy-pdf-word'));

        foreach (['ar', 'en'] as $locale) {
            foreach (['latin', 'arabic'] as $numerals) {
                $laravel = $make(Doc::getFacadeRoot())->locale($locale)->numerals($numerals);
                $core = $make($plain)->locale($locale)->numerals($numerals);

                $this->assertNotInstanceOf(PendingDocument::class, $core);
                $this->assertEquals($laravel->options(), $core->options(), "{$locale} {$numerals}");
                $this->assertSame($laravel->toHtml(), $core->toHtml(), "{$locale} {$numerals}");
            }
        }
    }

    public function test_plain_php_defaults_follow_the_config_file(): void
    {
        if ($set = preg_grep('/^DOC_/', array_keys(getenv() + $_ENV))) {
            $this->markTestSkipped('The config file reads '.implode(', ', $set).' from the environment.');
        }

        $file = require __DIR__.'/../../config/easy-pdf-word.php';
        $defaults = EasyPdfWord::defaults();

        // Laravel folders and .env values, the preview page, and the views folders of plain PHP.
        foreach ([&$file, &$defaults] as &$config) {
            unset($config['images']['paths'], $config['templates']['paths'], $config['theme']['company']['name'], $config['preview'], $config['views']);
        }

        $this->assertSame($file, $defaults);
    }
}
