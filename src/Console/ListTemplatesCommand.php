<?php

namespace BiztechEG\EasyPdfWord\Console;

use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Console\Command;

class ListTemplatesCommand extends Command
{
    protected $signature = 'doc:templates';

    protected $description = 'List the document templates available to the app';

    public function handle(TemplateRegistry $templates): int
    {
        $rows = [];

        foreach ($templates->all() as $name => $template) {
            $rows[] = [
                $name,
                $template->title(),
                implode(', ', $template->locales()),
                $templates->isBundled($name) ? 'package' : 'project',
            ];
        }

        $this->table(['Name', 'Title', 'Locales', 'Source'], $rows);

        return self::SUCCESS;
    }
}
