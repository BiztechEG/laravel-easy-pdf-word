<?php

namespace BiztechEG\EasyPdfWord;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Several files in one .zip, from Doc::zip(). Each file is rendered when the
 * archive is built, on the first call that needs its bytes.
 */
class ZipFile extends RenderedFile
{
    /**
     * @param  array<int|string, RenderedFile>  $files  names as keys, or a list where each file keeps its own name
     */
    public static function make(array $files, string $filename = 'documents.zip'): self
    {
        if ($files === []) {
            throw new InvalidArgumentException('A ZIP file needs at least one file.');
        }

        foreach ($files as $file) {
            if (! $file instanceof RenderedFile) {
                throw new InvalidArgumentException('Doc::zip() takes files made by ->pdf(), ->word() or Doc::zip(), got '.get_debug_type($file).'.');
            }
        }

        return new self(fn () => [self::archive($files), 'zip'], $filename);
    }

    public function mimeType(): string
    {
        return 'application/zip';
    }

    public function extension(): string
    {
        return 'zip';
    }

    /** @param  array<int|string, RenderedFile>  $files */
    private static function archive(array $files): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP files need the PHP zip extension (ext-zip).');
        }

        $path = tempnam(sys_get_temp_dir(), 'easy-pdf-word-zip');
        $zip = new ZipArchive;

        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create a ZIP file in '.sys_get_temp_dir().'.');
            }

            $names = [];

            foreach ($files as $key => $file) {
                // Names never hold folders, so the archive cannot unpack outside its folder.
                $name = ltrim($file->cleanFilename(is_string($key) ? $key : $file->filename()), '. ');

                if ($name === '' || $name === $file->extension()) {
                    $name = 'document.'.$file->extension();
                }

                $zip->addFromString(self::unique($name, $names), $file->content());
            }

            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /** "invoice.pdf", then "invoice (2).pdf", "invoice (3).pdf" ... */
    private static function unique(string $name, array &$names): string
    {
        $unique = $name;
        $info = pathinfo($name);
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        for ($i = 2; isset($names[mb_strtolower($unique)]); $i++) {
            $unique = $info['filename']." ({$i}){$extension}";
        }

        $names[mb_strtolower($unique)] = true;

        return $unique;
    }
}
