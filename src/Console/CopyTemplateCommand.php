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
        $source = $templates->get($this->argument('name'));
        $name = $this->option('as') ?: $source->name;

        if (! TemplateRegistry::isValidName($name)) {
            $this->components->error('Use letters, digits, dots, dashes or underscores for the template name.');

            return self::FAILURE;
        }

        $target = $this->targetPath($name);

        if ($files->isDirectory($target) && ! $this->option('force')) {
            $this->components->error("{$target} already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $files->copyDirectory($source->path, $target);

        $this->components->info("Template copied to {$target}");

        return self::SUCCESS;
    }

    private function targetPath(string $name): string
    {
        $base = (array) config('easy-pdf-word.templates.paths', []);

        return rtrim($base[0] ?? resource_path('doc-templates'), '/').'/'.$name;
    }
}
