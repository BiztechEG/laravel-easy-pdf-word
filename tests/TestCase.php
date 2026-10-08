<?php

namespace BiztechEG\EasyPdfWord\Tests;

use BiztechEG\EasyPdfWord\EasyPdfWordServiceProvider;
use BiztechEG\EasyPdfWord\Facades\Doc;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EasyPdfWordServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Doc' => Doc::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('easy-pdf-word.templates.paths', [sys_get_temp_dir().'/easy-pdf-word-tests/templates']);
        $app['config']->set('easy-pdf-word.pdf.drivers.mpdf.temp_dir', sys_get_temp_dir().'/easy-pdf-word-tests/mpdf');
    }

    /** Write a rendered file to tests/output for manual inspection. */
    protected function keep(string $name, string $content, string $extension = 'pdf'): string
    {
        $dir = dirname(__DIR__).'/tests/output';

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents("{$dir}/{$name}.{$extension}", $content);

        return "{$dir}/{$name}.{$extension}";
    }
}
