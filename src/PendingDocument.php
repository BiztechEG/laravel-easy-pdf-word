<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Arabic\Direction;
use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Builder\HtmlRenderer;
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\WordNotSupported;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Locale;
use BiztechEG\EasyPdfWord\Templates\Template;
use BiztechEG\EasyPdfWord\Word\DocxTemplateFiller;
use BiztechEG\EasyPdfWord\Word\WordRenderer;
use BadMethodCallException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * A document being configured. Every setter returns $this; ->pdf() and
 * ->word() turn it into a file.
 *
 *   Doc::template('invoice')->data($data)->locale('ar')->pdf()->download('invoice.pdf');
 *   Doc::make()->heading('تقرير')->table($rows)->locale('ar')->word()->download('report.docx');
 *
 * @method static heading(string $text, int $level = 1, array $style = [])
 * @method static paragraph(string|array $text, array $style = [])
 * @method static table(array $rows, array $options = [])
 * @method static image(string $source, float $widthMm = 40, string $align = 'start')
 * @method static qr(string $value, float $sizeMm = 30, string $align = 'start')
 * @method static spacer(float $heightMm = 5)
 * @method static pageBreak()
 * @method static line(?string $color = null)
 */
class PendingDocument
{
    private array $data = [];

    private ?string $locale = null;

    private ?string $direction = null;

    private ?string $numerals = null;

    private array $theme = [];

    private ?string $font = null;

    private ?string $paper = null;

    private ?string $orientation = null;

    private ?array $margins = null;

    private ?string $header = null;

    private ?string $footer = null;

    private ?string $driver = null;

    private ?string $title = null;

    private bool $validate = true;

    private const THEME_COLORS = ['primary' => '#0F766E', 'text' => '#1F2937', 'muted' => '#6B7280', 'border' => '#E5E7EB'];

    /** Validated and prepared template data, kept until the data or theme changes. */
    private ?array $prepared = null;

    private function __construct(
        private PdfManager $pdf,
        private FontRegistry $fonts,
        private ViewFactory $views,
        private Config $config,
        private ?Template $template = null,
        private ?string $view = null,
        private ?string $html = null,
        private ?DocumentBuilder $builder = null,
    ) {}

    public static function forTemplate(Template $template, PdfManager $pdf, FontRegistry $fonts, ViewFactory $views, Config $config): self
    {
        return new self($pdf, $fonts, $views, $config, template: $template);
    }

    public static function forView(string $view, PdfManager $pdf, FontRegistry $fonts, ViewFactory $views, Config $config): self
    {
        return new self($pdf, $fonts, $views, $config, view: $view);
    }

    public static function forHtml(string $html, PdfManager $pdf, FontRegistry $fonts, ViewFactory $views, Config $config): self
    {
        return new self($pdf, $fonts, $views, $config, html: $html);
    }

    public static function forBuilder(DocumentBuilder $builder, PdfManager $pdf, FontRegistry $fonts, ViewFactory $views, Config $config): self
    {
        return new self($pdf, $fonts, $views, $config, builder: $builder);
    }

    /**
     * Data for the template or view. Each call replaces top-level keys:
     * ->data(['invoice' => [...]]) replaces the whole "invoice" array.
     */
    public function data(array $data): static
    {
        $this->data = array_replace($this->data, $data);
        $this->prepared = null;

        return $this;
    }

    public function with(string|array $key, mixed $value = null): static
    {
        return $this->data(is_array($key) ? $key : [$key => $value]);
    }

    /**
     * Sets language and, unless ->direction() is called, the direction (ar => rtl).
     * Takes a locale name such as "ar", "en" or "ar_EG".
     */
    public function locale(string $locale): static
    {
        if (! Locale::isValid($locale)) {
            throw new InvalidArgumentException('Invalid locale ['.substr($locale, 0, 40).'], expected a name such as "ar", "en" or "ar_EG".');
        }

        $this->locale = $locale;

        return $this;
    }

    public function direction(string $direction): static
    {
        $this->direction = strtolower($direction) === 'rtl' ? 'rtl' : 'ltr';

        return $this;
    }

    public function rtl(): static
    {
        return $this->direction('rtl');
    }

    public function ltr(): static
    {
        return $this->direction('ltr');
    }

    /** "arabic" (٠١٢٣) or "latin" (0123) digits in the document text. */
    public function numerals(string $style): static
    {
        $this->numerals = Numerals::normalizeStyle($style);

        return $this;
    }

    /** Colours, logo and company details used by the templates. */
    public function theme(array $theme): static
    {
        $this->theme = array_replace_recursive($this->theme, $theme);
        $this->prepared = null;

        return $this;
    }

    /** A font name from config "fonts": cairo, tajawal, naskh or one you registered. */
    public function font(string $font): static
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]{0,63}\z/', $font)) {
            throw new InvalidArgumentException('Invalid font name ['.substr($font, 0, 40).'].');
        }

        $this->font = strtolower($font);

        return $this;
    }

    public function paper(string $paper, ?string $orientation = null): static
    {
        $this->paper = $paper;

        if ($orientation) {
            $this->orientation = $orientation;
        }

        return $this;
    }

    public function landscape(): static
    {
        $this->orientation = 'landscape';

        return $this;
    }

    public function portrait(): static
    {
        $this->orientation = 'portrait';

        return $this;
    }

    /** Millimetres. One value for all sides, or top, right, bottom, left. */
    public function margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
    {
        $this->margins = [$top, $right ?? $top, $bottom ?? $top, $left ?? $right ?? $top];

        return $this;
    }

    /** Page header HTML; may use {page} and {pages}. */
    public function header(string $html): static
    {
        $this->header = $html;

        return $this;
    }

    /** Page footer HTML; may use {page} and {pages}. */
    public function footer(string $html): static
    {
        $this->footer = $html;

        return $this;
    }

    /** PDF engine for this document: "mpdf", "chromium" or "gotenberg". */
    public function driver(string $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function withoutValidation(): static
    {
        $this->validate = false;
        $this->prepared = null;

        return $this;
    }

    public function pdf(?string $filename = null): PdfDocument
    {
        // A copy, so changes made to this document afterwards do not reach the file.
        $document = clone $this;
        $options = $document->options();

        return new PdfDocument(
            fn () => $document->pdf->render('', $options, $document->driver, fn (PdfDriver $engine) => $document->toHtml($engine, $options)),
            $filename ?? ($this->template?->name ?? 'document').'.pdf',
        );
    }

    /**
     * A Word (.docx) file, from the template's word.docx or word.php, or from
     * the blocks added with Doc::make().
     */
    public function word(?string $filename = null): WordDocument
    {
        if ($this->builder === null && ($this->template === null || ! $this->template->supportsWord())) {
            throw $this->template ? WordNotSupported::forTemplate($this->template->name) : WordNotSupported::forSource();
        }

        $document = clone $this;

        return new WordDocument(
            fn () => $document->renderWord(),
            $filename ?? ($this->template?->name ?? 'document').'.docx',
        );
    }

    /**
     * The final HTML handed to the engine; useful for previews and debugging.
     */
    public function toHtml(?PdfDriver $engine = null, ?PdfOptions $options = null): string
    {
        $options ??= $this->options();
        $engine ??= $this->pdf->driver($this->driver);
        $context = $this->context($options, $engine);
        $data = $this->viewData($context);

        $html = match (true) {
            $this->builder !== null => $this->wrapHtml((new HtmlRenderer)->render($this->builder, $context), $data),
            $this->template !== null && ! $this->template->hasPdfView() && $this->template->supportsPdf()
                => $this->wrapHtml((new HtmlRenderer)->render($this->runLayout($this->template->pdfLayout(), $data, $context), $context), $data),
            $this->template !== null => $this->views->file($this->template->pdfView(), $data)->render(),
            $this->view !== null => $this->views->make($this->view, $data)->render(),
            default => $this->wrapHtml((string) $this->html, $data),
        };

        if ($context->usesCssFonts() && ! str_contains($html, '@font-face')) {
            $html = preg_replace('/<\/head>/i', '<style>'.$context->fontCss.'</style></head>', $html, 1) ?? $html;
        }

        return $context->numerals === Numerals::ARABIC ? Numerals::convertHtml($html, Numerals::ARABIC) : $html;
    }

    public function options(): PdfOptions
    {
        $locale = $this->resolvedLocale();
        $direction = $this->direction ?? Direction::forLocale($locale);
        $config = $this->config->get('easy-pdf-word', []);
        $data = $this->templateData();
        $numerals = $this->resolvedNumerals();

        $partial = function (?string $view) use ($data, $locale, $direction, $numerals) {
            if ($view === null) {
                return null;
            }

            $context = $this->context(new PdfOptions(locale: $locale, direction: $direction, font: $this->resolvedFont($direction)), null);
            $html = $this->views->file($view, $data + ['doc' => $context])->render();

            return $numerals === Numerals::ARABIC ? Numerals::convertHtml($html, Numerals::ARABIC) : $html;
        };

        return new PdfOptions(
            paper: $this->paper ?? $this->template?->paper() ?? $config['pdf']['paper'] ?? 'A4',
            orientation: $this->orientation ?? $this->template?->orientation() ?? $config['pdf']['orientation'] ?? 'portrait',
            margins: $this->margins ?? $this->template?->margins() ?? $config['pdf']['margins'] ?? [15, 15, 15, 15],
            direction: $direction,
            locale: $locale,
            font: $this->resolvedFont($direction),
            header: $this->header ?? $partial($this->template?->headerView()),
            footer: $this->footer ?? $partial($this->template?->footerView()),
            title: $this->title ?? $this->template?->title(),
            author: $this->resolvedTheme()['company']['name'] ?? null,
        );
    }

    public function __clone()
    {
        if ($this->builder !== null) {
            $this->builder = clone $this->builder;
        }
    }

    /**
     * Add blocks to a Doc::make() document: heading, paragraph, table, image,
     * qr, spacer, pageBreak, line.
     */
    public function __call(string $method, array $arguments): static
    {
        if ($this->builder === null || ! method_exists($this->builder, $method) || in_array($method, ['blocks', 'isEmpty'], true)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        $this->builder->{$method}(...$arguments);

        return $this;
    }

    /** @return array{0: string, 1: string} [bytes, engine name] */
    private function renderWord(): array
    {
        $options = $this->options();
        $context = $this->context($options, null);
        $data = $this->templateData();

        if ($this->builder === null && ($file = $this->template->wordFile())) {
            return [(new DocxTemplateFiller)->fill($file, $data, $context), 'docx-template'];
        }

        $builder = $this->builder ?? $this->runLayout($this->template->wordLayout(), $data, $context);
        $renderer = new WordRenderer((array) $this->config->get('easy-pdf-word.word', []));

        return [$renderer->render($builder, $context, $options), 'phpword'];
    }

    /** Run a template's layout.php or word.php. */
    private function runLayout(callable $layout, array $data, DocContext $context): DocumentBuilder
    {
        $builder = new DocumentBuilder;
        $layout($builder, $data, $context);

        return $builder;
    }

    private function context(PdfOptions $options, ?PdfDriver $engine): DocContext
    {
        $usesCss = $engine?->usesCssFonts() ?? false;

        return new DocContext(
            locale: $options->locale,
            direction: $options->direction,
            font: $options->font,
            theme: $this->resolvedTheme(),
            numerals: $this->resolvedNumerals(),
            engine: $engine ? $engine::class : '',
            fontCss: $usesCss ? $this->fonts->cssFontFaces([$options->font]) : '',
            translations: $this->template?->translations($options->locale) ?? [],
            fallbackTranslations: $this->template?->translations('en') ?? [],
            imagePaths: $this->config->get('easy-pdf-word.images.paths'),
            remoteImages: $this->remoteImages(),
        );
    }

    /** Config "images.remote": true, false, or hosts as a list or a comma-separated string. */
    private function remoteImages(): bool|array
    {
        $remote = $this->config->get('easy-pdf-word.images.remote', false);

        if (is_array($remote) || is_bool($remote) || $remote === null) {
            return is_array($remote) ? $remote : (bool) $remote;
        }

        return filter_var($remote, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? array_values(array_filter(array_map('trim', explode(',', (string) $remote))));
    }

    private function viewData(DocContext $context): array
    {
        return $this->templateData() + ['doc' => $context];
    }

    private function templateData(): array
    {
        if ($this->template === null) {
            return $this->data;
        }

        if ($this->prepared !== null) {
            return $this->prepared;
        }

        // Collections and models become arrays, so rules like "array" pass.
        $data = array_replace_recursive($this->template->defaults(), $this->toArrays($this->data));

        if ($this->validate && $this->template->rules() !== []) {
            Validator::make($data, $this->template->rules())->validate();
        }

        return $this->prepared = $this->template->prepare($data, $this->resolvedTheme());
    }

    private function toArrays(array $data): array
    {
        return array_map(fn ($value) => match (true) {
            $value instanceof Arrayable => $this->toArrays($value->toArray()),
            is_array($value) => $this->toArrays($value),
            default => $value,
        }, $data);
    }

    private function wrapHtml(string $html, array $data): string
    {
        if (preg_match('/<html[\s>]/i', $html)) {
            return $html;
        }

        return $this->views->make('easy-pdf-word::raw', ['body' => $html] + $data)->render();
    }

    private function resolvedLocale(): string
    {
        return $this->locale ?? $this->config->get('easy-pdf-word.locale') ?? $this->config->get('app.locale', 'en');
    }

    private function resolvedNumerals(): string
    {
        return $this->numerals ?? Numerals::normalizeStyle($this->config->get('easy-pdf-word.numerals', 'latin'));
    }

    private function resolvedFont(string $direction): string
    {
        $key = $direction === 'rtl' ? 'default' : 'default_ltr';

        return $this->font ?? strtolower($this->config->get("easy-pdf-word.fonts.{$key}", 'cairo'));
    }

    private function resolvedTheme(): array
    {
        $theme = array_replace_recursive(
            (array) $this->config->get('easy-pdf-word.theme', []),
            $this->template?->theme() ?? [],
            $this->theme,
        );

        // Theme colours go into the templates' CSS, so only real colours pass.
        foreach (self::THEME_COLORS as $key => $default) {
            $theme[$key] = Color::css($theme[$key] ?? null, $default);
        }

        return $theme;
    }
}
