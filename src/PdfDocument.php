<?php

namespace BiztechEG\EasyPdfWord;

/**
 * A PDF made by ->pdf(). Returning it from a controller shows it in the browser.
 */
class PdfDocument extends RenderedFile
{
    public function mimeType(): string
    {
        return 'application/pdf';
    }

    public function extension(): string
    {
        return 'pdf';
    }
}
