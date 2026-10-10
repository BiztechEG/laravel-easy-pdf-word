<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Output\File;
use InvalidArgumentException;

/**
 * Several files in one .zip, from Doc::zip(). Each file is rendered when the
 * archive is built, on the first call that needs its bytes.
 */
class ZipFile extends RenderedFile
{
    /**
     * @param  array<int|string, File>  $files  names as keys, or a list where each file keeps its own name
     */
    public static function make(array $files, string $filename = 'documents.zip'): self
    {
        if ($files === []) {
            throw new InvalidArgumentException('A ZIP file needs at least one file.');
        }

        foreach ($files as $file) {
            if (! $file instanceof File) {
                throw new InvalidArgumentException('Doc::zip() takes files made by ->pdf(), ->word() or Doc::zip(), got '.get_debug_type($file).'.');
            }
        }

        return new self(fn () => [File::archive($files), 'zip'], $filename);
    }

    public function mimeType(): string
    {
        return 'application/zip';
    }

    public function extension(): string
    {
        return 'zip';
    }
}
