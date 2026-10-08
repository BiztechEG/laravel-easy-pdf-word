<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use Closure;
use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rendered (or about to be rendered) file. Rendering happens once, on the
 * first call that needs the bytes.
 */
abstract class RenderedFile implements Attachable, Responsable
{
    private ?string $content = null;

    private ?string $engine = null;

    /**
     * @param  Closure(): array{0: string, 1: string}  $renderer  returns [bytes, engine name]
     * @param  GeneratedDocument|null  $fake  set under Doc::fake(): saves and responses are recorded there
     */
    public function __construct(
        private Closure $renderer,
        private string $filename = 'document',
        private ?GeneratedDocument $fake = null,
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
        if ($this->fake !== null) {
            $this->content();
            $this->fake->recordSave($path, $disk);

            return $path;
        }

        if ($disk === null && $this->isAbsolute($path)) {
            // Another worker may create the folder at the same moment.
            if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
                throw new RuntimeException('Could not create the folder ['.dirname($path).'].');
            }

            if (@file_put_contents($path, $this->content()) === false) {
                throw new RuntimeException("Could not write [{$path}].");
            }

            return $path;
        }

        // Disks do not throw by default; a failed write must not look saved
        // (a queued save would otherwise finish without a file).
        if (! Storage::disk($disk)->put($path, $this->content())) {
            throw new RuntimeException("Could not write [{$path}] to the [".($disk ?? config('filesystems.default')).'] disk.');
        }

        return $path;
    }

    public function toResponse($request): Response
    {
        return $this->stream();
    }

    /**
     * Attach the file to a mail: return it from a Mailable's attachments(),
     * or pass it to ->attach() on a notification's MailMessage.
     */
    public function toMailAttachment(): Attachment
    {
        return Attachment::fromData(fn () => $this->content(), $this->filename())->withMime($this->mimeType());
    }

    /** The file name used for downloads and mail attachments, with its extension. */
    public function filename(): string
    {
        return $this->cleanFilename($this->filename);
    }

    private function response(?string $filename, string $disposition): Response
    {
        $filename = $this->cleanFilename($filename ?? $this->filename);
        $this->fake?->recordResponse($filename, $disposition);
        // Browsers use the UTF-8 name; old clients get it in Latin letters, like
        // Laravel's own downloads ("%" is not allowed in this fallback name).
        $fallback = preg_replace('/[^\x20-\x24\x26-\x7E]/', '_', Str::ascii($filename));

        if (trim(pathinfo($fallback, PATHINFO_FILENAME), ' _-.') === '') {
            $fallback = 'document'.(pathinfo($filename, PATHINFO_EXTENSION) !== '' ? '.'.pathinfo($filename, PATHINFO_EXTENSION) : '');
        }

        return new Response($this->content(), 200, [
            'Content-Type' => $this->mimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
        ]);
    }

    protected function cleanFilename(string $filename): string
    {
        // Slashes are not allowed in a file name.
        $filename = str_replace(['/', '\\'], '-', $filename);
        $extension = '.'.$this->extension();

        return str_ends_with(strtolower($filename), $extension) ? $filename : $filename.$extension;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
