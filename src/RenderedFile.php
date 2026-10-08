<?php

namespace BiztechEG\EasyPdfWord;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rendered (or about to be rendered) file. Rendering happens once, on the
 * first call that needs the bytes.
 */
abstract class RenderedFile implements Responsable
{
    private ?string $content = null;

    private ?string $engine = null;

    /**
     * @param  Closure(): array{0: string, 1: string}  $renderer  returns [bytes, engine name]
     */
    public function __construct(
        private Closure $renderer,
        private string $filename = 'document',
    ) {}

    abstract public function mimeType(): string;

    abstract public function extension(): string;

    public function content(): string
    {
        if ($this->content === null) {
            [$this->content, $this->engine] = ($this->renderer)();
        }

        return $this->content;
    }

    public function toString(): string
    {
        return $this->content();
    }

    public function base64(): string
    {
        return base64_encode($this->content());
    }

    /** The engine that produced the file, after a fallback too. */
    public function engine(): string
    {
        $this->content();

        return $this->engine;
    }

    public function download(?string $filename = null): Response
    {
        return $this->response($filename, 'attachment');
    }

    /** Show the file in the browser (or let it hand the file to an app). */
    public function stream(?string $filename = null): Response
    {
        return $this->response($filename, 'inline');
    }

    public function inline(?string $filename = null): Response
    {
        return $this->stream($filename);
    }

    /**
     * Save to a path on a filesystem disk, or to an absolute local path when
     * no disk is given and the path is absolute.
     */
    public function save(string $path, ?string $disk = null): string
    {
        if ($disk === null && $this->isAbsolute($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }

            file_put_contents($path, $this->content());

            return $path;
        }

        Storage::disk($disk)->put($path, $this->content());

        return $path;
    }

    public function toResponse($request): Response
    {
        return $this->stream();
    }

    private function response(?string $filename, string $disposition): Response
    {
        $filename ??= $this->filename;
        $extension = '.'.$this->extension();

        if (! str_ends_with(strtolower($filename), $extension)) {
            $filename .= $extension;
        }

        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $filename);

        return new Response($this->content(), 200, [
            'Content-Type' => $this->mimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
        ]);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
