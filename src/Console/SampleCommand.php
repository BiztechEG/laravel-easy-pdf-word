<?php

namespace BiztechEG\EasyPdfWord\Console;

use BiztechEG\EasyPdfWord\DocFactory;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Render a template with its sample data to a file, to see it without
 * writing any code.
 */
class SampleCommand extends Command
{
    protected $signature = 'doc:sample
        {name : The template, e.g. invoice}
        {--locale=ar : Language of the document}
        {--format= : pdf, docx or html; by default the --output extension, or pdf}
        {--numerals=latin : latin (123) or arabic (١٢٣)}
        {--driver= : PDF engine: mpdf, chromium or gotenberg}
        {--output= : File to write, by default in storage/app/doc-samples}';

    protected $description = 'Render a template with its sample data to a PDF, Word or HTML file';

    public function handle(DocFactory $docs, TemplateRegistry $templates): int
    {
        $name = $this->argument('name');
        $output = $this->option('output');
        $extension = strtolower(pathinfo((string) $output, PATHINFO_EXTENSION));
        $format = strtolower($this->option('format') ?: (in_array($extension, ['pdf', 'docx', 'html'], true) ? $extension : 'pdf'));

        if (! $templates->exists($name)) {
            $this->components->error("Template [{$name}] was not found. Run php artisan doc:templates to list them.");

            return self::FAILURE;
        }

        if (! in_array($format, ['pdf', 'docx', 'html'], true)) {
            $this->components->error('Use --format=pdf, docx or html.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');

        try {
            $document = $docs->template($name, $templates->get($name)->sample())
                ->locale($locale)
                ->numerals($this->option('numerals'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('driver')) {
            $document->driver($this->option('driver'));
        }

        $path = $output ?: storage_path("app/doc-samples/{$name}-{$locale}.{$format}");

        // A relative --output is relative to where the command runs, not to a storage disk.
        if (! preg_match('#^(/|\\\\|[A-Za-z]:[\\\\/])#', $path)) {
            $path = getcwd().DIRECTORY_SEPARATOR.$path;
        }

        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            $this->error('Could not create the folder ['.dirname($path).'].');

            return self::FAILURE;
        }

        match ($format) {
            'html' => file_put_contents($path, $document->toHtml()),
            'docx' => $document->word()->save($path),
            default => $document->pdf()->save($path),
        };

        $this->components->info("Saved {$path}");

        return self::SUCCESS;
    }
}
