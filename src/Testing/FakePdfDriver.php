<?php

namespace BiztechEG\EasyPdfWord\Testing;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;

/**
 * Stands in for the PDF engine while Doc::fake() is on, so a document's HTML
 * can be built without mPDF or Chromium.
 */
class FakePdfDriver implements PdfDriver
{
    public function render(string $html, PdfOptions $options): string
    {
        return "%PDF-1.4\n% Doc::fake()\n%%EOF\n";
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function usesCssFonts(): bool
    {
        return false;
    }
}
