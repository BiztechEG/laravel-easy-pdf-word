<?php

namespace BiztechEG\EasyPdfWord\Testing;

use BiztechEG\EasyPdfWord\DocFactory;
use BiztechEG\EasyPdfWord\PendingDocument;
use Closure;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * What the Doc facade becomes after Doc::fake(): documents are built and
 * their data checked as usual, but no PDF or Word file is rendered, saved
 * or sent. The assertions below check what the code asked for.
 */
class DocFake extends DocFactory
{
    /** @var list<GeneratedDocument> */
    private array $documents = [];

    public function template(string $name, array $data = []): PendingDocument
    {
        return parent::template($name, $data)->recordTo($this);
    }

    public function view(string $view, array $data = []): PendingDocument
    {
        return parent::view($view, $data)->recordTo($this);
    }

    public function html(string $html): PendingDocument
    {
        return parent::html($html)->recordTo($this);
    }

    public function make(): PendingDocument
    {
        return parent::make()->recordTo($this);
    }

    /** @internal */
    public function record(GeneratedDocument $document): void
    {
        $this->documents[] = $document;
    }

    /**
     * Every ->pdf() and ->word() file asked for, or those the callback accepts.
     *
     * @param  (Closure(GeneratedDocument): bool)|null  $callback
     * @return list<GeneratedDocument>
     */
    public function generated(?Closure $callback = null): array
    {
        return $callback === null ? $this->documents : array_values(array_filter($this->documents, $callback));
    }

    /** @param  (Closure(GeneratedDocument): bool)|null  $callback */
    public function assertGenerated(?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->generated($callback),
            $callback ? 'No generated document matches the callback.' : 'No document was generated.',
        );
    }

    /** @param  Closure(GeneratedDocument): bool  $callback */
    public function assertNotGenerated(Closure $callback): void
    {
        PHPUnit::assertEmpty($this->generated($callback), 'A generated document matches the callback.');
    }

    public function assertGeneratedCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->documents, "Expected {$count} generated documents, got ".count($this->documents).'.');
    }

    public function assertNothingGenerated(): void
    {
        $this->assertGeneratedCount(0);
    }

    /**
     * A file was saved to the path (on the disk, when given), or a saved file
     * matches the callback.
     *
     * @param  string|(Closure(GeneratedDocument): bool)  $path
     */
    public function assertSaved(string|Closure $path, ?string $disk = null): void
    {
        $saved = $path instanceof Closure
            ? $this->generated(fn (GeneratedDocument $document) => $document->wasSaved() && $path($document))
            : $this->generated(fn (GeneratedDocument $document) => $document->wasSaved($path, $disk));

        PHPUnit::assertNotEmpty($saved, $path instanceof Closure
            ? 'No saved document matches the callback.'
            : "No document was saved to [{$path}]".($disk === null ? '' : " on disk [{$disk}]").'.');
    }

    /**
     * A file was sent as a download with this name, or a downloaded file
     * matches the callback.
     *
     * @param  string|(Closure(GeneratedDocument): bool)|null  $filename
     */
    public function assertDownloaded(string|Closure|null $filename = null): void
    {
        $downloaded = $filename instanceof Closure
            ? $this->generated(fn (GeneratedDocument $document) => $document->wasDownloaded() && $filename($document))
            : $this->generated(fn (GeneratedDocument $document) => $document->wasDownloaded($filename));

        PHPUnit::assertNotEmpty($downloaded, is_string($filename)
            ? "No document was downloaded as [{$filename}]."
            : 'No downloaded document matches.');
    }

    /**
     * A file was shown in the browser (->stream(), or returned from a
     * controller), or a streamed file matches the callback.
     *
     * @param  string|(Closure(GeneratedDocument): bool)|null  $filename
     */
    public function assertStreamed(string|Closure|null $filename = null): void
    {
        $streamed = $filename instanceof Closure
            ? $this->generated(fn (GeneratedDocument $document) => $document->wasStreamed() && $filename($document))
            : $this->generated(fn (GeneratedDocument $document) => $document->wasStreamed($filename));

        PHPUnit::assertNotEmpty($streamed, is_string($filename)
            ? "No document was streamed as [{$filename}]."
            : 'No streamed document matches.');
    }
}
