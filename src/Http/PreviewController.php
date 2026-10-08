<?php

namespace BiztechEG\EasyPdfWord\Http;

use BiztechEG\EasyPdfWord\DocFactory;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The template preview page: every template rendered with its sample data,
 * in any language, engine and format. Enabled by config "preview".
 */
class PreviewController
{
    public function __construct(
        private DocFactory $docs,
        private TemplateRegistry $templates,
    ) {}

    public function index(Request $request, ViewFactory $views): Response
    {
        $templates = [];

        foreach ($this->templates->all() as $name => $template) {
            $templates[$name] = [
                'title' => $template->title(),
                'description' => $template->description(),
                'locales' => $template->locales(),
                'pdf' => $template->supportsPdf(),
                'word' => $template->supportsWord(),
                'source' => $this->templates->isBundled($name) ? 'package' : 'project',
            ];
        }

        return response($views->make('easy-pdf-word::preview.index', [
            'templates' => $templates,
            'selected' => $request->query('template', array_key_first($templates)),
            'engines' => ['mpdf', 'chromium', 'gotenberg'],
            'base' => rtrim($request->url(), '/'),
        ])->render());
    }

    public function show(Request $request, string $template): Response
    {
        abort_unless(TemplateRegistry::isValidName($template) && $this->templates->exists($template), 404);

        $definition = $this->templates->get($template);
        $document = $this->docs->template($template, $definition->sample())
            ->locale($request->query('locale', $definition->locales()[0] ?? 'ar'))
            ->numerals($request->query('numerals', 'latin'));

        if ($request->filled('engine')) {
            $document->driver((string) $request->query('engine'));
        }

        return match ($request->query('format', 'pdf')) {
            'html' => response($document->toHtml()),
            'docx', 'word' => $document->word()->download($template.'.docx'),
            default => $document->pdf()->stream($template.'.pdf'),
        };
    }
}
