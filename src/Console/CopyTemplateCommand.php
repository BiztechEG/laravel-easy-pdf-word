<?php

namespace BiztechEG\EasyPdfWord\Console;

use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Copy a ready-made template into the project to customise it.
 */
class CopyTemplateCommand extends Command
{
    protected $signature = 'doc:template
        {name : The template to copy, e.g. invoice}
        {--as= : New name for the copy (defaults to the same name, which overrides the original)}
        {--force : Overwrite an existing copy}';

    protected $description = 'Copy a ready-made document template into resources/doc-templates to customise it';

    public function handle(TemplateRegistry $templates, Filesystem $files): int
    {
        if (! $templates->exists($this->argument('name'))) {
            $this->components->error("Template [{$this->argument('name')}] was not found. Run php artisan doc:templates to list them.");

            return self::FAILURE;
        }

        $source = $templates->get($this->argument('name'));
        $name = $this->option('as') ?: $source->name;
        // The package's own template when there is one, so --force restores
        // it over a project copy rather than copying that copy onto itself.
        $bundled = TemplateRegistry::packagePath().'/'.$source->name;
        $from = $files->isDirectory($bundled) ? $bundled : $source->path;

        if (! TemplateRegistry::isValidName($name)) {
            $this->components->error('Use letters, digits, dots, dashes or underscores for the template name.');

            return self::FAILURE;
        }

        $target = $this->targetPath($name);

        if ($files->isDirectory($target) && ! $this->option('force')) {
            $this->components->error("{$target} already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        if (realpath($from) === realpath($target)) {
            $this->components->error("{$target} is the template itself. Use --as to copy it under another name.");

            return self::FAILURE;
        }

        if (! $files->copyDirectory($from, $target)) {
            $this->components->error("Could not copy the template to {$target}.");

            return self::FAILURE;
        }

        $this->components->info("Template copied to {$target}");

        return self::SUCCESS;
    }

    private function targetPath(string $name): string
    {
        $base = (array) config('easy-pdf-word.templates.paths', []);

        return rtrim($base[0] ?? resource_path('doc-templates'), '/').'/'.$name;
    }
}
