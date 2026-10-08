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
                implode(', ', array_keys(array_filter(['PDF' => $template->supportsPdf(), 'Word' => $template->supportsWord()]))),
                $templates->isBundled($name) ? 'package' : 'project',
            ];
        }

        $this->table(['Name', 'Title', 'Locales', 'Formats', 'Source'], $rows);

        return self::SUCCESS;
    }
}
