<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Laravel\BladeViewRenderer;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use BiztechEG\EasyPdfWord\View\PhpViewRenderer;

class ViewRendererTest extends TestCase
{
    public function test_laravel_renders_plain_php_views_as_plain_php_does(): void
    {
        $blade = new BladeViewRenderer(fn () => $this->app['view']);
        $file = __DIR__.'/../fixtures/plain/invoices/show.html.php';
        $data = ['doc' => 'rtl', 'name' => 'شركة & أخرى'];

        $this->assertSame((new PhpViewRenderer)->file($file, $data), $blade->file($file, $data));
    }

    public function test_laravel_renders_blade_views(): void
    {
        $blade = new BladeViewRenderer(fn () => $this->app['view']);

        $file = sys_get_temp_dir().'/easy-pdf-word-tests/hello.blade.php';
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, '<p>{{ $name }}</p>');

        $this->assertSame('<p>&lt;b&gt;</p>', $blade->file($file, ['name' => '<b>']));
    }
}
