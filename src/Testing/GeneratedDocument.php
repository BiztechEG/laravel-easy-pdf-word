<?php

namespace BiztechEG\EasyPdfWord\Testing;

use BiztechEG\EasyPdfWord\RenderedFile;
use Closure;
use LogicException;

/**
 * A file asked for while Doc::fake() is on: what it was made from and what
 * was done with it. Nothing is rendered, sent or written.
 */
class GeneratedDocument
{
    private RenderedFile $file;

    private ?array $resolvedData = null;

    private ?string $resolvedHtml = null;

    /** @var list<array{path: string, disk: ?string}> */
    private array $saves = [];

    /** @var list<array{filename: string, disposition: string}> */
    private array $responses = [];

    /**
     * @param  string  $format  "pdf" or "word"
     * @param  Closure(): array  $data
     * @param  Closure(): string  $html
     */
    public function __construct(
        public readonly string $format,
        public readonly ?string $template,
        public readonly ?string $view,
        public readonly string $locale,
        public readonly string $direction,
        public readonly string $numerals,
        public readonly ?string $driver,
        private Closure $data,
        private Closure $html,
    ) {}

    /** @internal */
    public function for(RenderedFile $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function isPdf(): bool
    {
        return $this->format === 'pdf';
    }

    public function isWord(): bool
    {
        return $this->format === 'word';
    }

    /** The file name with its extension, as a download or attachment would get it. */
    public function filename(): string
    {
        return $this->file->filename();
    }

    /**
     * The data the document was made from: for a template, after its defaults
     * and prepare() (so totals are there). A dot-notation key picks one value.
     */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        $this->resolvedData ??= ($this->data)();

        return $key === null ? $this->resolvedData : data_get($this->resolvedData, $key, $default);
    }

    /** The HTML a PDF engine would get. */
    public function html(): string
    {
        if (! $this->isPdf()) {
            throw new LogicException('Word files have no HTML; check data() instead.');
        }

        return $this->resolvedHtml ??= ($this->html)();
    }

    /** Whether the PDF's HTML contains the text, escaped as a template would print it. */
    public function contains(string $text): bool
    {
        return str_contains($this->html(), e($text));
    }

    /** @return list<array{path: string, disk: ?string}> */
    public function saves(): array
    {
        return $this->saves;
    }

    public function wasSaved(?string $path = null, ?string $disk = null): bool
    {
        foreach ($this->saves as $save) {
            if (($path === null || $save['path'] === $path) && ($disk === null || $save['disk'] === $disk)) {
                return true;
            }
        }

        return false;
    }

    /** Sent as a download (attachment), as opposed to shown in the browser. */
    public function wasDownloaded(?string $filename = null): bool
    {
        return $this->responded('attachment', $filename);
    }

    /** Shown in the browser: ->stream(), or the file returned from a controller. */
    public function wasStreamed(?string $filename = null): bool
    {
        return $this->responded('inline', $filename);
    }

    /** @internal Placeholder bytes, after the data is checked as rendering would. */
    public function content(): string
    {
        $this->data();

        return $this->isPdf()
            ? "%PDF-1.4\n% Doc::fake() {$this->filename()}\n%%EOF\n"
            : "PK\x03\x04 Doc::fake() {$this->filename()}";
    }

    /** @internal */
    public function recordSave(string $path, ?string $disk): void
    {
        $this->saves[] = ['path' => $path, 'disk' => $disk];
    }

    /** @internal */
    public function recordResponse(string $filename, string $disposition): void
    {
        $this->responses[] = ['filename' => $filename, 'disposition' => $disposition];
    }

    private function responded(string $disposition, ?string $filename): bool
    {
        foreach ($this->responses as $response) {
            if ($response['disposition'] === $disposition && ($filename === null || $response['filename'] === $filename)) {
                return true;
            }
        }

        return false;
    }
}
