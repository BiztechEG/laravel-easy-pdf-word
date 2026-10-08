<?php

namespace BiztechEG\EasyPdfWord\Console;

use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Create an empty template folder to build a document from scratch.
 */
class MakeTemplateCommand extends Command
{
    protected $signature = 'doc:make-template {name : Folder name, e.g. packing-list}';

    protected $description = 'Create a new document template from a blank starter';

    public function handle(Filesystem $files, TemplateRegistry $templates): int
    {
        $name = $this->argument('name');

        if (! TemplateRegistry::isValidName($name)) {
            $this->components->error('Use letters, digits, dots, dashes or underscores for the template name.');

            return self::FAILURE;
        }

        $base = (array) config('easy-pdf-word.templates.paths', []);
        $target = rtrim($base[0] ?? resource_path('doc-templates'), '/').'/'.$name;

        if ($files->isDirectory($target)) {
            $this->components->error("{$target} already exists.");

            return self::FAILURE;
        }

        // A project template hides the bundled one of the same name.
        if ($templates->isBundled($name)) {
            $this->components->warn("This replaces the bundled [{$name}] template in your app. To start from it instead, run php artisan doc:template {$name}.");
        }

        $files->copyDirectory(dirname(__DIR__, 2).'/resources/stubs/template', $target);
        $files->put($target.'/template.php', str_replace('{{ name }}', $name, $files->get($target.'/template.php')));

        $this->components->info("Template created in {$target}");

        return self::SUCCESS;
    }
}
