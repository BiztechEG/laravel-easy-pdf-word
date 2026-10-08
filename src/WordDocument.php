<?php

namespace BiztechEG\EasyPdfWord;

/**
 * A Word (.docx) file made by ->word().
 */
class WordDocument extends RenderedFile
{
    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extension(): string
    {
        return 'docx';
    }
}
