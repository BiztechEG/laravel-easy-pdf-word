<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Testing\DocFake;
use BiztechEG\EasyPdfWord\Testing\FakePdfDriver;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * A document being configured in Laravel. Every setter returns $this;
 * ->pdf() and ->word() turn it into a file that can be downloaded, saved to
 * a disk or attached to mail, and ->queue() renders it on a worker.
 *
 *   Doc::template('invoice')->data($data)->locale('ar')->pdf()->download('invoice.pdf');
 *   Doc::make()->heading('تقرير')->table($rows)->locale('ar')->word()->download('report.docx');
 */
class PendingDocument extends Document
{
    /** Properties a queued job does not carry: services, the source and caches. */
    private const NOT_QUEUED = ['services', 'template', 'view', 'html', 'builder', 'prepared', 'fake'];

    /** Set by Doc::fake(): files are recorded there instead of rendered. */
    private ?DocFake $fake = null;

    /** @internal Used by Doc::fake(). */
    public function recordTo(DocFake $fake): static
    {
        $this->fake = $fake;

        return $this;
    }

    public function pdf(?string $filename = null): PdfDocument
    {
        return parent::pdf($filename);
    }

    /**
     * A Word (.docx) file, from the template's word.docx or word.php, or from
     * the blocks added with Doc::make().
     */
    public function word(?string $filename = null): WordDocument
    {
        return parent::word($filename);
    }

    protected function pdfFile(string $filename, PdfOptions $options): PdfDocument
    {
        if ($this->fake !== null) {
            $generated = $this->generated('pdf', $options);

            $file = new PdfDocument(fn () => [$generated->content(), 'fake'], $filename, $generated);
            $this->fake->record($generated->for($file));

            return $file;
        }

        return new PdfDocument(fn () => $this->renderPdf($options), $filename);
    }

    protected function wordFile(string $filename): WordDocument
    {
        if ($this->fake !== null) {
            $generated = $this->generated('word', $this->options());

            $file = new WordDocument(fn () => [$generated->content(), 'fake'], $filename, $generated);
            $this->fake->record($generated->for($file));

            return $file;
        }

        return new WordDocument(fn () => $this->renderWord(), $filename);
    }

    /**
     * Render and save the file on a queue worker instead of now. The format
     * comes from the extension: ".pdf" or ".docx". Returns Laravel's
     * PendingDispatch, so ->onQueue(), ->delay() and ->chain() work.
     *
     *   Doc::template('invoice', $data)->locale('ar')->queue('invoices/1024.pdf', 's3')->onQueue('documents');
     */
    public function queue(string $path, ?string $disk = null): PendingDispatch
    {
        $format = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'docx' => 'word',
            default => throw new InvalidArgumentException('Cannot tell the format of ['.mb_substr($path, -60).']: end the path with ".pdf" or ".docx".'),
        };

        // Invalid data, or a template without a Word layout, fails now rather than on the worker.
        if ($format === 'word') {
            $this->ensureWordSupported();
        }

        $this->templateData();

        return new PendingDispatch(new SaveDocument($this->toQueue(), $format, $path, $disk));
    }

    /**
     * @internal What SaveDocument needs to build this document again on a worker.
     */
    public function toQueue(): array
    {
        $settings = array_diff_key(get_object_vars($this), array_flip(self::NOT_QUEUED));

        // The locale a middleware set for this request is not the worker's.
        $settings['locale'] = $this->resolvedLocale();
        $settings['numerals'] = $this->resolvedNumerals();

        // Template data ends up as arrays anyway; models and collections are not stored whole.
        if ($this->template !== null) {
            $settings['data'] = $this->toArrays($this->data);
        }

        return [
            'template' => $this->template?->name,
            'view' => $this->view,
            'html' => $this->html,
            'builder' => $this->builder ? clone $this->builder : null,
            'settings' => $settings,
        ];
    }

    /**
     * @internal Build a document from toQueue(). Through the factory, so a
     * worker running under Doc::fake() records the file.
     */
    public static function fromQueue(array $queued, DocFactory $factory): self
    {
        $document = match (true) {
            isset($queued['template']) => $factory->template($queued['template']),
            isset($queued['view']) => $factory->view($queued['view']),
            isset($queued['builder']) => $factory->make(),
            default => $factory->html((string) ($queued['html'] ?? '')),
        };

        if (isset($queued['builder'])) {
            $document->builder = $queued['builder'];
        }

        // Settings this version does not know (queued by another version) are skipped.
        foreach ($queued['settings'] ?? [] as $key => $value) {
            if (property_exists($document, $key) && ! in_array($key, self::NOT_QUEUED, true)) {
                $document->{$key} = $value;
            }
        }

        return $document;
    }

    protected function prepareData(array $data): array
    {
        try {
            return parent::prepareData($data);
        } catch (ValidationFailed $e) {
            // Templates throw the framework-free exception; Laravel apps get their usual one.
            throw ValidationException::withMessages($e->errors());
        }
    }

    /** Collections and models become arrays too. */
    protected function toArrays(array $data): array
    {
        return array_map(fn ($value) => match (true) {
            $value instanceof Arrayable => $this->toArrays($value->toArray()),
            is_array($value) => $this->toArrays($value),
            default => $value,
        }, $data);
    }

    /** What Doc::fake() records for a ->pdf() or ->word() file. */
    private function generated(string $format, PdfOptions $options): GeneratedDocument
    {
        return new GeneratedDocument(
            format: $format,
            template: $this->template?->name,
            view: $this->view,
            locale: $options->locale,
            direction: $options->direction,
            numerals: $options->numerals,
            driver: $this->driver,
            data: fn () => $this->templateData(),
            html: fn () => $this->toHtml(new FakePdfDriver, $options),
            watermark: $format === 'pdf' ? $options->watermark['text'] ?? null : null,
            protected: $format === 'pdf' && $options->protection !== null,
        );
    }
}
