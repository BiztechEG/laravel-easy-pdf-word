<?php

namespace BiztechEG\EasyPdfWord\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Create an empty template folder to build a document from scratch.
 */
class MakeTemplateCommand extends Command
{
    protected $signature = 'doc:make-template {name : Folder name, e.g. delivery-note}';

    protected $description = 'Create a new document template from a blank starter';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            $this->components->error('Use letters, digits, dots, dashes or underscores for the template name.');

            return self::FAILURE;
        }

        $base = (array) config('easy-pdf-word.templates.paths', []);
        $target = rtrim($base[0] ?? resource_path('doc-templates'), '/').'/'.$name;

        if ($files->isDirectory($target)) {
            $this->components->error("{$target} already exists.");

            return self::FAILURE;
        }

        $files->copyDirectory(dirname(__DIR__, 2).'/resources/stubs/template', $target);
        $files->put($target.'/template.php', str_replace('{{ name }}', $name, $files->get($target.'/template.php')));

        $this->components->info("Template created in {$target}");

        return self::SUCCESS;
    }
}
