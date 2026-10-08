<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * The object behind the Doc facade.
 */
class DocFactory
{
    public function __construct(
        private PdfManager $pdf,
        private TemplateRegistry $templates,
        private FontRegistry $fonts,
        private ViewFactory $views,
        private Config $config,
    ) {}

    /** Start from a ready-made or project template. */
    public function template(string $name, array $data = []): PendingDocument
    {
        return PendingDocument::forTemplate($this->templates->get($name), $this->pdf, $this->fonts, $this->views, $this->config)
            ->data($data);
    }

    /** Start from any Blade view in the app. */
    public function view(string $view, array $data = []): PendingDocument
    {
        return PendingDocument::forView($view, $this->pdf, $this->fonts, $this->views, $this->config)->data($data);
    }

    /** Start from an HTML string or body fragment. */
    public function html(string $html): PendingDocument
    {
        return PendingDocument::forHtml($html, $this->pdf, $this->fonts, $this->views, $this->config);
    }

    public function templates(): TemplateRegistry
    {
        return $this->templates;
    }

    public function fonts(): FontRegistry
    {
        return $this->fonts;
    }

    public function pdfManager(): PdfManager
    {
        return $this->pdf;
    }

    /** Register a custom PDF engine: Doc::extend('my-engine', fn ($app) => new MyDriver). */
    public function extend(string $driver, Closure $callback): static
    {
        $this->pdf->extend($driver, $callback);

        return $this;
    }
}
