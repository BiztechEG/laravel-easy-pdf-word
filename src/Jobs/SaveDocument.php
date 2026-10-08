<?php

namespace BiztechEG\EasyPdfWord\Jobs;

use BiztechEG\EasyPdfWord\DocFactory;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Renders a document on a queue worker and saves it. This is what
 * Doc::template(...)->queue('invoices/1024.pdf') dispatches.
 *
 * The payload carries the document's data (and a PDF password, when one is
 * set), so Laravel encrypts it with the app key.
 */
class SaveDocument implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  array  $document  from PendingDocument::toQueue()
     * @param  'pdf'|'word'  $format
     */
    public function __construct(
        public array $document,
        public string $format,
        public string $path,
        public ?string $disk = null,
    ) {}

    public function handle(DocFactory $factory): void
    {
        $document = PendingDocument::fromQueue($this->document, $factory);
        $filename = basename(str_replace('\\', '/', $this->path));
        $file = $this->format === 'word' ? $document->word($filename) : $document->pdf($filename);

        $file->save($this->path, $this->disk);
    }
}
