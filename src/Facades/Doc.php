<?php

namespace BiztechEG\EasyPdfWord\Facades;

use BiztechEG\EasyPdfWord\DocFactory;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BiztechEG\EasyPdfWord\PendingDocument template(string $name, array $data = [])
 * @method static \BiztechEG\EasyPdfWord\PendingDocument view(string $view, array $data = [])
 * @method static \BiztechEG\EasyPdfWord\PendingDocument html(string $html)
 * @method static \BiztechEG\EasyPdfWord\PendingDocument make()
 * @method static \BiztechEG\EasyPdfWord\Templates\TemplateRegistry templates()
 * @method static \BiztechEG\EasyPdfWord\Fonts\FontRegistry fonts()
 * @method static \BiztechEG\EasyPdfWord\Pdf\PdfManager pdfManager()
 * @method static \BiztechEG\EasyPdfWord\DocFactory extend(string $driver, \Closure $callback)
 *
 * @see DocFactory
 */
class Doc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocFactory::class;
    }
}
