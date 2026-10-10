<?php

namespace BiztechEG\EasyPdfWord;

/**
 * The object behind the Doc facade: documents are PendingDocument, with
 * queues, fakes, downloads and mail attachments.
 */
class DocFactory extends DocumentFactory
{
    /** Start from a ready-made or project template. */
    public function template(string $name, array $data = []): PendingDocument
    {
        return parent::template($name, $data);
    }

    /** Start from any Blade view in the app. */
    public function view(string $view, array $data = []): PendingDocument
    {
        return parent::view($view, $data);
    }

    /** Start from an HTML string or body fragment. */
    public function html(string $html): PendingDocument
    {
        return parent::html($html);
    }

    /**
     * Build a document in code, block by block; it renders to both PDF and
     * Word: Doc::make()->heading('...')->table($rows)->word().
     */
    public function make(): PendingDocument
    {
        return parent::make();
    }

    /**
     * Several files in one .zip: Doc::zip([$invoice->pdf(), $invoice->word()])
     * or Doc::zip(['فاتورة.pdf' => $pdf]), then ->download() or ->save().
     *
     * @param  array<int|string, Output\File>  $files
     */
    public function zip(array $files, string $filename = 'documents.zip'): ZipFile
    {
        return ZipFile::make($files, $filename);
    }

    protected function documentClass(): string
    {
        return PendingDocument::class;
    }
}
