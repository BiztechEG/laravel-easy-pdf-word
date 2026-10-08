<?php

namespace BiztechEG\EasyPdfWord\Facades;

use BiztechEG\EasyPdfWord\DocFactory;
use BiztechEG\EasyPdfWord\Testing\DocFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BiztechEG\EasyPdfWord\PendingDocument template(string $name, array $data = [])
 * @method static \BiztechEG\EasyPdfWord\PendingDocument view(string $view, array $data = [])
 * @method static \BiztechEG\EasyPdfWord\PendingDocument html(string $html)
 * @method static \BiztechEG\EasyPdfWord\PendingDocument make()
 * @method static \BiztechEG\EasyPdfWord\ZipFile zip(array $files, string $filename = 'documents.zip')
 * @method static \BiztechEG\EasyPdfWord\Templates\TemplateRegistry templates()
 * @method static \BiztechEG\EasyPdfWord\Fonts\FontRegistry fonts()
 * @method static \BiztechEG\EasyPdfWord\Pdf\PdfManager pdfManager()
 * @method static \BiztechEG\EasyPdfWord\DocFactory extend(string $driver, \Closure $callback)
 * @method static array generated(\Closure|null $callback = null)
 * @method static void assertGenerated(\Closure|null $callback = null)
 * @method static void assertNotGenerated(\Closure $callback)
 * @method static void assertGeneratedCount(int $count)
 * @method static void assertNothingGenerated()
 * @method static void assertSaved(string|\Closure $path, string|null $disk = null)
 * @method static void assertDownloaded(string|\Closure|null $filename = null)
 * @method static void assertStreamed(string|\Closure|null $filename = null)
 *
 * @see DocFactory
 * @see DocFake
 */
class Doc extends Facade
{
    /**
     * Record documents instead of rendering them, for tests:
     * Doc::fake(); ...; Doc::assertGenerated(fn ($doc) => $doc->template === 'invoice');
     */
    public static function fake(): DocFake
    {
        $fake = static::getFacadeApplication()->make(DocFake::class);
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return DocFactory::class;
    }
}
