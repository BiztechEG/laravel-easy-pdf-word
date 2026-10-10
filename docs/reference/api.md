# Doc API

Every public class, method and property of the package, with exact signatures. The [guide](/guide/introduction) explains how to do a task; this page lists everything in one place so you can look up a name, a parameter or a default.

All classes live under the `BiztechEG\EasyPdfWord` namespace. Methods marked *internal* are public for technical reasons only; do not call them from your app.

## How the pieces fit {#overview}

```text
Doc::template() / Doc::view() / Doc::html() / Doc::make()
        │
        ▼
PendingDocument          settings: ->locale() ->theme() ->paper() ... and blocks for Doc::make()
        │
        ├── ->pdf()      → PdfDocument   ┐
        ├── ->word()     → WordDocument  ├─ RenderedFile: download, stream, save, content, mail attachment
        │   Doc::zip()   → ZipFile       ┘
        ├── ->queue()    → SaveDocument job (Laravel PendingDispatch)
        └── ->toHtml()   → the HTML handed to the PDF engine

Doc::fake()              → DocFake, which records a GeneratedDocument for every file
```

| Class | Full name | You get it from |
| --- | --- | --- |
| `Doc` facade | `BiztechEG\EasyPdfWord\Facades\Doc` | Import it, or use the `Doc` alias |
| `DocFactory` | `BiztechEG\EasyPdfWord\DocFactory` | The object behind the facade (a singleton) |
| `PendingDocument` | `BiztechEG\EasyPdfWord\PendingDocument` | `Doc::template()`, `view()`, `html()`, `make()` |
| `DocumentBuilder` | `BiztechEG\EasyPdfWord\Builder\DocumentBuilder` | `Doc::make()` and the first argument of `layout.php` / `word.php` |
| `PdfDocument`, `WordDocument` | `BiztechEG\EasyPdfWord\PdfDocument`, `...\WordDocument` | `->pdf()`, `->word()` |
| `ZipFile` | `BiztechEG\EasyPdfWord\ZipFile` | `Doc::zip()` |
| `SaveDocument` | `BiztechEG\EasyPdfWord\Jobs\SaveDocument` | `->queue()` |
| `DocFake`, `GeneratedDocument` | `BiztechEG\EasyPdfWord\Testing\...` | `Doc::fake()` |
| `TemplateRegistry`, `Template` | `BiztechEG\EasyPdfWord\Templates\...` | `Doc::templates()` |
| `FontRegistry` | `BiztechEG\EasyPdfWord\Fonts\FontRegistry` | `Doc::fonts()` |
| `PdfManager`, `PdfOptions` | `BiztechEG\EasyPdfWord\Pdf\...` | `Doc::pdfManager()`, a PDF engine's `render()` |
| `PdfDriver` | `BiztechEG\EasyPdfWord\Contracts\PdfDriver` | Implement it for your own engine |

The helpers you use while writing a template (`$doc` in Blade, `Arabic`, `ZatcaQr` ...) are listed in [Template helpers](/reference/template-helpers).

## Doc facade {#doc}

`Doc` resolves `DocFactory` from the container. The package registers the `Doc` alias through package discovery, so `Doc::` works in Blade and in `tinker` too; in classes, import the facade:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
```

### template() {#doc-template}

```php
Doc::template(string $name, array $data = []): PendingDocument
```

Starts a document from a template: one of the bundled templates (`invoice`, `receipt` ...) or a folder in the `templates.paths` config. Project folders are searched first, so a copied template replaces the bundled one. `$data` is the same as calling `->data($data)`. Throws [`TemplateNotFound`](#exceptions) at once when no folder has that name.

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$data = [
    'invoice' => ['number' => 'INV-2026-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'buyer'   => ['name' => 'مؤسسة النور'],
    'items'   => [
        ['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000],
    ],
];

return Doc::template('invoice', $data)->locale('ar')->pdf()->download('فاتورة-1024.pdf');
```

The examples below reuse this `$data`.

### view() {#doc-view}

```php
Doc::view(string $view, array $data = []): PendingDocument
```

Starts a PDF from any Blade view of your app. The view gets your data plus `$doc` (a [`DocContext`](/reference/template-helpers#doc-context)); wrap its content in `x-doc::layout`. Blade views make PDFs only: `->word()` throws [`WordNotSupported`](#exceptions).

```php
Doc::view('pdf.contract', ['contract' => $contract])->locale('ar')->pdf()->save('contracts/1024.pdf', 's3');
```

### html() {#doc-html}

```php
Doc::html(string $html): PendingDocument
```

Starts a PDF from HTML. A string that contains an `<html>` tag is used as it is; anything else is placed inside the package layout, so it gets the direction, font and base styles. PDF only. The HTML is trusted: never pass user input to it.

```php
return Doc::html('<h1>مرحبا</h1><p>إيصال استلام رقم 77</p>')->locale('ar')->pdf()->stream();
```

### make() {#doc-make}

```php
Doc::make(): PendingDocument
```

Starts an empty document that you build block by block with the [`DocumentBuilder`](#document-builder) methods. The same blocks render to PDF and to Word.

```php
$report = Doc::make()
    ->heading('تقرير المبيعات')
    ->table([['الفرع', 'المبيعات'], ['القاهرة', '486,500.75']], ['header' => true])
    ->locale('ar');

$report->pdf()->save('reports/sales.pdf');
$report->word()->save('reports/sales.docx');
```

### zip() {#doc-zip}

```php
Doc::zip(array $files, string $filename = 'documents.zip'): ZipFile
```

Puts several files made by `->pdf()`, `->word()` or `Doc::zip()` in one ZIP archive. Give a string key to rename a file inside the archive. See [ZipFile](#zip-file). Needs the PHP `zip` extension.

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([
    $invoice->pdf('فاتورة-1024.pdf'),
    $invoice->word('فاتورة-1024.docx'),
], 'order-1024.zip')->download();
```

### extend() {#doc-extend}

```php
Doc::extend(string $driver, Closure $callback): DocFactory
```

Registers your own PDF engine under a name. The callback receives the application container and returns a [`PdfDriver`](#pdf-driver). Names are matched without case. Returns the factory, so calls can chain. Call it in a service provider's `boot()`.

```php
Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver((string) config('services.pdf.url')));
```

### fake() {#doc-fake}

```php
Doc::fake(): DocFake
```

Swaps the facade for a [`DocFake`](#doc-fake-class): documents are built and their data validated, but no engine runs and nothing is written or sent. Use it in tests, then call the [assertions](#doc-fake-class) on `Doc`.

```php
Doc::fake();

$this->get('/invoices/1024/download')->assertOk();

Doc::assertDownloaded('فاتورة-1024.pdf');
```

### templates() {#doc-templates}

```php
Doc::templates(): TemplateRegistry
```

The registry that finds templates. See [TemplateRegistry](#template-registry).

```php
$sample = Doc::templates()->get('invoice')->sample();   // the template's sample data
```

### fonts() {#doc-fonts}

```php
Doc::fonts(): FontRegistry
```

The fonts the package knows: Cairo, Tajawal, Naskh and the ones in `fonts.custom`. See [FontRegistry](#font-registry).

### pdfManager() {#doc-pdf-manager}

```php
Doc::pdfManager(): PdfManager
```

The manager that resolves PDF engines by name and handles the fallback. See [PdfManager](#pdf-manager).

## PendingDocument {#pending-document}

`BiztechEG\EasyPdfWord\PendingDocument` is a document being set up. Every setting returns the same object (`static`), so calls chain in any order. Nothing is rendered until you ask for the file's bytes. Guides: [Page settings](/guide/page-settings), [Output and delivery](/guide/output).

| Group | Methods |
| --- | --- |
| Data | [`data`](#pending-data), [`with`](#pending-with), [`withoutValidation`](#pending-without-validation) |
| Language and digits | [`locale`](#pending-locale), [`direction`](#pending-direction), [`rtl` / `ltr`](#pending-rtl-ltr), [`numerals`](#pending-numerals) |
| Look | [`theme`](#pending-theme), [`font`](#pending-font), [`title`](#pending-title) |
| Page | [`paper`](#pending-paper), [`landscape` / `portrait`](#pending-landscape-portrait), [`margins`](#pending-margins), [`header`](#pending-header), [`footer`](#pending-footer) |
| PDF only | [`driver`](#pending-driver), [`watermark`](#pending-watermark), [`password`](#pending-password) |
| Output | [`pdf`](#pending-pdf), [`word`](#pending-word), [`queue`](#pending-queue), [`toHtml`](#pending-to-html), [`options`](#pending-options) |
| `Doc::make()` blocks | [`heading`, `paragraph`, `table` ...](#pending-blocks) |

Where a setting is not called, the value comes from the template's `template.php` or from `config/easy-pdf-word.php`:

| Setting | Default when not set |
| --- | --- |
| Locale | `easy-pdf-word.locale`, else `app.locale` |
| Direction | From the locale: `rtl` for `ar`, `fa`, `ur`, `he` ..., `ltr` otherwise |
| Numerals | `easy-pdf-word.numerals` (`latin`) |
| Font | `fonts.default` (`cairo`) for right-to-left documents, `fonts.default_ltr` (`cairo`) for the rest |
| Paper, orientation, margins | `template.php`, then `pdf.paper` (`A4`), `pdf.orientation` (`portrait`), `pdf.margins` (`[15, 15, 15, 15]`) |
| Header, footer | The template's `header.html.php` and `footer.html.php` (or their `.blade.php` versions) |
| Title | `template.php` `title` |
| Theme | `easy-pdf-word.theme`, then `template.php` `theme`, then `->theme()` |
| Engine | `pdf.driver` (`DOC_PDF_DRIVER`, `mpdf`) |

### data() {#pending-data}

```php
data(array $data): static
```

Sets the data for the template or view. Each call replaces top-level keys: `->data(['buyer' => [...]])` replaces the whole `buyer` array and keeps the other keys. For templates, Eloquent models and collections are turned into arrays, merged over the template's `defaults`, validated against its `fields` and passed through its `prepare` callback.

```php
Doc::template('invoice')->data($data)->data(['buyer' => ['name' => 'شركة الأفق للتجارة']]);
```

### with() {#pending-with}

```php
with(string|array $key, mixed $value = null): static
```

The same as `data()`, for one key: `->with('qr', 'zatca')` is `->data(['qr' => 'zatca'])`. An array works like `data()`.

### withoutValidation() {#pending-without-validation}

```php
withoutValidation(): static
```

Skips the template's `fields` rules. Defaults and `prepare` still run. Use it only when your data is already checked and a rule is in your way.

### locale() {#pending-locale}

```php
locale(string $locale): static
```

Sets the document language: the template labels (`lang/{language}.php`), the amount in words, and the direction unless you call `->direction()`. Takes names such as `ar`, `en`, `ar_EG`, `ar-SA`. Throws `InvalidArgumentException` for anything else (paths, markup).

```php
Doc::template('invoice', $data)->locale('ar_EG');   // Arabic, right to left, Arabic labels
```

### direction() {#pending-direction}

```php
direction(string $direction): static
```

Forces the direction: `'rtl'` gives right to left, any other value gives left to right. Without it the direction follows the locale.

### rtl() / ltr() {#pending-rtl-ltr}

```php
rtl(): static
ltr(): static
```

Short for `->direction('rtl')` and `->direction('ltr')`.

### numerals() {#pending-numerals}

```php
numerals(string $style): static
```

`'arabic'` prints Arabic digits (١٢٣) in the document text; `'latin'` prints 0123. Also accepts `arab`, `ar`, `eastern`, `hindi` (Arabic) and `latn`, `en`, `western` (Latin). Throws `InvalidArgumentException` for other values. Only text is converted: tags, attributes, CSS, e-mail addresses and links keep their digits. With the Naskh font the separators become ٫ and ٬; Cairo, Tajawal and Word files keep `.` and `,`.

```php
Doc::template('invoice', $data)->locale('ar')->numerals('arabic');   // INV-٢٠٢٦-١٠٢٤
```

### theme() {#pending-theme}

```php
theme(array $theme): static
```

Colours, logo and company details for this document, merged over the config `theme` (later calls merge over earlier ones). Keys the bundled templates read: `primary`, `text`, `muted`, `border` (colours), `logo` (an image path, URL or data URI) and `company` with `name`, `address`, `phone`, `email`, `tax_number`. A colour that is not a real CSS colour falls back to the default. `company.name` also becomes the PDF author.

```php
Doc::template('invoice', $data)->theme([
    'primary' => '#1D4ED8',
    'logo'    => public_path('images/logo.png'),
    'company' => ['name' => 'شركة بيزتك', 'phone' => '+20 100 000 0000', 'tax_number' => '123-456-789'],
]);
```

### font() {#pending-font}

```php
font(string $font): static
```

A font name from the config: `cairo`, `tajawal`, `naskh`, or one you registered in `fonts.custom`. The name is lowercased. Letters, digits, spaces, `-` and `_` only; anything else throws `InvalidArgumentException`. Word files ignore it and use the `word.font` config instead.

### title() {#pending-title}

```php
title(string $title): static
```

The title stored in the file's properties: the PDF, and Word files built from blocks or a layout (a `word.docx` keeps its own). In a PDF it replaces the page's own `<title>`. Without it, a PDF keeps its page title, and a template whose page has none gets the template's `title`.

### paper() {#pending-paper}

```php
paper(string|array $paper, ?string $orientation = null): static
```

Paper size: `A2`, `A3`, `A4`, `A5`, `A6`, `B4`, `B5`, `Letter`, `Legal`, `Tabloid` or `Executive` (any case), or `[width, height]` in mm. `A4-L` and `A4-P` set the orientation too. `$orientation` is `'landscape'` or `'portrait'`. An unknown name, or an array that is not two positive numbers, throws `InvalidArgumentException`.

```php
Doc::template('receipt', $receipt)->paper('A5', 'landscape');
Doc::template('report', $report)->paper('A4-L');
Doc::view('pdf.till-receipt', ['order' => $order])->paper([80, 200]);
```

### landscape() / portrait() {#pending-landscape-portrait}

```php
landscape(): static
portrait(): static
```

Sets the orientation, keeping the paper size.

### margins() {#pending-margins}

```php
margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
```

Page margins in millimetres, like CSS: one value for all sides, two for top and bottom then right and left, four for each side.

```php
->margins(15)          // 15 on every side
->margins(15, 12)      // top and bottom 15, right and left 12
->margins(20, 15, 25, 15)
```

### header() {#pending-header}

```php
header(string $html): static
```

HTML printed at the top of every page. `{page}` and `{pages}` become the page number and the page count. Replaces the template's header file. In Word files the HTML becomes plain text with page fields.

### footer() {#pending-footer}

```php
footer(string $html): static
```

The same at the bottom of every page; replaces the template's footer file.

```php
->footer('<div style="text-align: center; font-size: 8pt;">صفحة {page} من {pages}</div>')
```

::: info
Chromium and Gotenberg print `{page}` and `{pages}` in Latin digits, even with `->numerals('arabic')`. mPDF follows the numerals setting. In Word files they are page-number fields that Word fills in itself, so the numerals setting does not apply to them.
:::

### driver() {#pending-driver}

```php
driver(string $driver): static
```

The PDF engine for this document: `mpdf`, `chromium` (or `browsershot`, `chrome`), `gotenberg`, or a name registered with `Doc::extend()`. When the engine is not installed or fails, the `pdf.fallback` engine renders the file and a warning is logged. Any other name throws `InvalidArgumentException` when the PDF is rendered: `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().`

### watermark() {#pending-watermark}

```php
watermark(string $text, float $opacity = 0.12, string $color = '#000000'): static
```

Prints large diagonal text across every page of the PDF, such as `مسودة` or `نسخة`. The text is cut to 100 characters, the opacity kept between 0.01 and 1, and an invalid colour becomes black. Empty text throws `InvalidArgumentException`. Word files are made without it.

```php
Doc::template('quotation', $quotation)->watermark('مسودة', opacity: 0.08, color: '#B91C1C')->pdf();
```

### password() {#pending-password}

```php
password(string $user, ?string $owner = null, array $allow = ['print', 'print-highres', 'copy']): static
```

Encrypts the PDF. `$user` is asked for when the file is opened (`''` opens it without a password). `$owner` unlocks everything; when it is `null` a random one is used, so the limits hold. `$allow` lists what readers may do, any of `PendingDocument::PERMISSIONS`:

```php
public const PERMISSIONS = ['print', 'print-highres', 'copy', 'modify', 'annot-forms', 'fill-forms', 'extract', 'assemble'];
```

An unknown permission throws `InvalidArgumentException`. Word files cannot be encrypted, so `->word()` and `->queue('….docx')` throw `LogicException` on a document with a password. With Chromium or Gotenberg, the PDF is encrypted afterwards by mPDF, so `mpdf/mpdf` must be installed.

```php
// Opens freely, but readers may only print it.
Doc::template('receipt', $receipt)->password('', owner: 'office-2026', allow: ['print'])->pdf();
```

### pdf() {#pending-pdf}

```php
pdf(?string $filename = null): PdfDocument
```

Returns the PDF as a [`PdfDocument`](#rendered-files). `$filename` is the name used by `download()`, `stream()` and mail attachments; it defaults to the template name (`invoice.pdf`) or `document.pdf`. The settings are copied at this moment and template data is validated now; the file itself is rendered when its bytes are first needed.

### word() {#pending-word}

```php
word(?string $filename = null): WordDocument
```

Returns the Word file as a [`WordDocument`](#rendered-files), built from the template's `word.docx`, `word.php` or `layout.php`, or from the `Doc::make()` blocks. The name defaults to `invoice.docx` or `document.docx`. As with `pdf()`, template data is validated now and the file is rendered when its bytes are first needed. Throws [`WordNotSupported`](#exceptions) for Blade views, HTML and templates without a Word layout, and `LogicException` when a password is set. Needs `phpoffice/phpword`.

### queue() {#pending-queue}

```php
queue(string $path, ?string $disk = null): PendingDispatch
```

Renders and saves the file on a queue worker instead of during the request. The extension picks the format: `.pdf` or `.docx` (anything else throws `InvalidArgumentException`). Template data is validated before the job is queued. Returns Laravel's `Illuminate\Foundation\Bus\PendingDispatch`, so `->onQueue()`, `->onConnection()`, `->delay()` and `->chain()` work. The job is [`SaveDocument`](#save-document).

```php
Doc::template('invoice', $data)->locale('ar')
    ->queue('invoices/INV-2026-1024.pdf', 's3')
    ->onQueue('documents');
```

### toHtml() {#pending-to-html}

```php
toHtml(?PdfDriver $engine = null, ?PdfOptions $options = null): string
```

The final HTML the PDF engine receives, with digits converted. Useful for debugging a template or for a quick HTML preview. Both arguments are optional; by default the document's own engine and options are used.

### options() {#pending-options}

```php
options(): PdfOptions
```

The resolved page and document settings as a [`PdfOptions`](#pdf-options) object: what the engine will get. Validates template data.

```php
$options = Doc::template('receipt', $receipt)->options();
$options->paper;        // 'A5'
$options->orientation;  // 'landscape'
```

### Builder blocks on a document {#pending-blocks}

A document from `Doc::make()` also takes every [`DocumentBuilder`](#document-builder) block method (`heading`, `paragraph`, `table`, `image`, `qr`, `spacer`, `pageBreak`, `line`) and returns itself, so blocks and settings mix in one chain. On a template, view or HTML document, these methods throw `BadMethodCallException`.

### Internal methods {#pending-internal}

`forTemplate()`, `forView()`, `forHtml()`, `forBuilder()` (static constructors used by `Doc`), `recordTo()` (used by `Doc::fake()`), `toQueue()` and `fromQueue()` (used by the queued job) are *internal*.

`PendingDocument` extends `BiztechEG\EasyPdfWord\Document`, the same document without Laravel; `PendingDocument` adds `->queue()`, `Doc::fake()` recording and Laravel collections and models as data.

## DocumentBuilder {#document-builder}

`BiztechEG\EasyPdfWord\Builder\DocumentBuilder` describes a document in blocks. You use it through `Doc::make()`, and you receive one as the first argument of a template's `layout.php` or `word.php`. Every block method returns the builder. Guide: [Building in code](/guide/builder).

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::make()
    ->heading('عرض أسعار الخدمات')
    ->paragraph([['text' => 'العميل: ', 'bold' => true], 'مؤسسة النور'])
    ->table([
        ['الخدمة', 'السعر'],
        ['تصميم الهوية', '8,000.00'],
        ['تطوير الموقع', '25,000.00'],
    ], ['header' => true, 'columns' => [70, ['width' => 30, 'align' => 'end']]])
    ->qr('https://biztech.example/q/2026-77', 25, 'end')
    ->locale('ar')
    ->pdf();
```

### heading() {#builder-heading}

```php
heading(string $text, int $level = 1, array $style = []): static
```

A heading at level 1, 2 or 3 (other values are clamped): 18, 14 and 12 pt, bold. Level 1 uses the theme's `primary` colour. A heading always stays on the same page as the block after it. `$style` takes the [text styles](#builder-styles).

### paragraph() {#builder-paragraph}

```php
paragraph(string|array $text, array $style = []): static
```

A paragraph. `$text` is a string, or a list of runs: each run a string or an array with `text` and run styles (`bold`, `italic`, `size`, `color`, `ltr`). `$style` applies to the whole paragraph and also takes `space_after` (mm) and `line_height` (`1.5` is one and a half lines). Line breaks in the text are kept.

```php
->paragraph([['text' => 'المبلغ: ', 'bold' => true], '1,250.00 ج.م'], ['align' => 'end', 'space_after' => 4])
```

### table() {#builder-table}

```php
table(array $rows, array $options = []): static
```

A table. `$rows` is a list of rows, each a list of [cells](#builder-cells). `$options` is described in [table options](#builder-table-options).

### image() {#builder-image}

```php
image(string $source, float $widthMm = 40, string $align = 'start'): static
```

An image from a file path, a URL or a data URI, `$widthMm` wide, aligned `start`, `center` or `end`. The [image rules](/guide/images) apply: local files only from the allowed folders, URLs only from allowed hosts; an image that is not allowed is left out. Word files take JPEG, PNG and GIF (WebP and BMP are converted, SVG is left out).

### qr() {#builder-qr}

```php
qr(string $value, float $sizeMm = 30, string $align = 'start'): static
```

A QR code of `$value` (a link, a ZATCA payload ...), `$sizeMm` wide.

### spacer() {#builder-spacer}

```php
spacer(float $heightMm = 5): static
```

Empty vertical space.

### pageBreak() {#builder-page-break}

```php
pageBreak(): static
```

Starts a new page.

### line() {#builder-line}

```php
line(?string $color = null): static
```

A thin horizontal rule, in the theme's `border` colour unless you give one.

### blocks() / isEmpty() {#builder-blocks}

```php
blocks(): array
isEmpty(): bool
```

The blocks added so far, as arrays with a `type` key, and whether there are none. Available on the builder in `layout.php`; a `Doc::make()` document does not forward them.

### Text styles {#builder-styles}

Headings, paragraphs, runs and table cells take these keys:

| Key | Value | Notes |
| --- | --- | --- |
| `bold` | `true` | |
| `italic` | `true` | |
| `size` | points, e.g. `9.5` | |
| `color` | `#hex`, `rgb()` or `hsl()` (or a colour name in PDF) | Word leaves colour names out and drops the alpha of `#RRGGBBAA` |
| `align` | `start`, `end`, `center`, `justify` | `start` is right in Arabic documents, left in English ones. Not for single runs |
| `ltr` | `true` | Keeps a phone number, code or e-mail in left-to-right order inside Arabic text |
| `background` | `#hex` | Table cells only |
| `space_after` | millimetres | Paragraphs only |
| `line_height` | e.g. `1.5` | Paragraphs only |
| `font` | a font name, e.g. `Tahoma` | Word files only; PDFs use the document font |

### Table options {#builder-table-options}

| Option | Default | Effect |
| --- | --- | --- |
| `columns` | `[]` | One entry per column: a percent width (`30`), or `['width' => 30, 'align' => 'end']` |
| `header` | `false` | The first row is a header: bold, coloured, repeated at the top of each page |
| `header_background` | theme `primary` | Header row background |
| `header_color` | `#FFFFFF` | Header row text colour |
| `borders` | `true` | A line under every cell |
| `border_color` | theme `border` | Colour of those lines |
| `striped` | `null` | A `#hex` background for every other row |
| `footer` | `false` | The last row is bold (a totals row) |
| `font_size` | `null` | Text size of the whole table, in points |

### Table cells {#builder-cells}

A cell is a string, or an array with these keys plus any [text style](#builder-styles):

| Key | Content |
| --- | --- |
| `text` | A string |
| `lines` | Several paragraphs: each a string, a styled run (`['text' => ..., 'bold' => true, 'align' => 'center']`), a list of runs, or `['image' => $path, 'width' => 30]` |
| `image` | An image path, URL or data URI; `width` in mm (default 30) |
| `qr` | A value to draw as a QR code; `width` in mm (default 30) |
| `colspan` | Number of columns the cell spans |
| `border` | A `#hex` colour: a box around this cell |

```php
->table([
    [['text' => 'الإجمالي', 'colspan' => 2, 'bold' => true], ['text' => '28,500.00', 'ltr' => true]],
    [['lines' => ['شركة بيزتك', ['text' => '+20 100 000 0000', 'ltr' => true, 'color' => '#6B7280']]], ['qr' => 'https://biztech.example/i/1024', 'width' => 22], ''],
], ['borders' => false, 'columns' => [50, 25, 25]])
```

## Rendered files {#rendered-files}

`PdfDocument`, `WordDocument` and `ZipFile` extend the abstract `BiztechEG\EasyPdfWord\RenderedFile`, which implements Laravel's `Attachable` (mail) and `Responsable` (controller responses). The file is rendered once, on the first call that needs its bytes. `RenderedFile` extends `BiztechEG\EasyPdfWord\Output\File`, the file without Laravel.

| Method | Returns | Description |
| --- | --- | --- |
| `content(): string` | bytes | Renders (once) and returns the file's bytes |
| `toString(): string` | bytes | The same as `content()` |
| `base64(): string` | string | The bytes, base64 encoded (for APIs) |
| `engine(): string` | name | What made the file: `mpdf`, `browsershot`, `gotenberg` or your engine's name (after a fallback, the fallback's), `phpword` or `docx-template` for Word, `zip`, or `fake` under `Doc::fake()` |
| `filename(): string` | name | The file name with its extension |
| `mimeType(): string` | type | `application/pdf`, the Word type, or `application/zip` |
| `extension(): string` | `pdf`, `docx`, `zip` | |
| `download(?string $filename = null): Response` | response | Sends the file as a download |
| `stream(?string $filename = null): Response` | response | Shows the file in the browser |
| `inline(?string $filename = null): Response` | response | The same as `stream()` |
| `save(string $path, ?string $disk = null): string` | the path | Saves to a disk, or to an absolute local path when no disk is given |
| `toResponse($request): Response` | response | Lets a controller return the file; it is streamed |
| `toMailAttachment(): Attachment` | attachment | Lets a Mailable or `MailMessage::attach()` take the file |

`Response` is `Symfony\Component\HttpFoundation\Response`; `Attachment` is `Illuminate\Mail\Attachment`.

### Names and saving {#rendered-names}

A name without the right extension gets it (`'فاتورة-1024'` becomes `فاتورة-1024.pdf`), and `/` or `\` in a name become `-`. Arabic names work: the response carries the UTF-8 name, and for old clients an ASCII name in Latin letters (`fator-1024.pdf`), or `document.pdf` when nothing is left.

`save()` with a disk writes through `Storage::disk($disk)`; without a disk, an absolute path (`/var/...` or `C:\...`) is written directly, creating the folder, and a relative path goes to the default disk. A write that fails throws `RuntimeException` instead of looking saved.

```php
$pdf = Doc::template('invoice', $data)->locale('ar')->pdf('فاتورة-1024');

$pdf->save('invoices/INV-2026-1024.pdf', 's3');   // on the s3 disk
$pdf->save(storage_path('app/archive/1024.pdf')); // an absolute local path
return $pdf->download();                          // فاتورة-1024.pdf
```

### Mail attachments {#rendered-mail}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Mail\Mailable;

class InvoiceMail extends Mailable
{
    public function __construct(public array $invoice) {}

    // envelope() and content() as in any Mailable

    public function attachments(): array
    {
        return [Doc::template('invoice', $this->invoice)->locale('ar')->pdf('فاتورة-1024.pdf')];
    }
}
```

The name given to `pdf()` or `word()` is the attachment's name. Make the file inside `attachments()` (or `toMail()`), not in the constructor, so a queued mail does not carry an unrendered file.

### PdfDocument and WordDocument {#pdf-word-document}

`BiztechEG\EasyPdfWord\PdfDocument` (`application/pdf`, `.pdf`) and `BiztechEG\EasyPdfWord\WordDocument` (`application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `.docx`) add nothing to `RenderedFile` but their type and extension. Returning either from a controller shows it in the browser.

## ZipFile {#zip-file}

`BiztechEG\EasyPdfWord\ZipFile` is a `RenderedFile`, so it has every method above (`download`, `save`, `toMailAttachment` ...).

```php
ZipFile::make(array $files, string $filename = 'documents.zip'): ZipFile
```

`Doc::zip()` calls this. `$files` is a list of `PdfDocument`, `WordDocument` or `ZipFile` objects; a string key is the name inside the archive, otherwise each file keeps its own `filename()`. Names never contain folders, and a name used twice becomes `name (2).pdf`, `name (3).pdf`. Each file is rendered when the archive is built. An empty list or anything that is not a rendered file throws `InvalidArgumentException`; a missing `zip` extension throws `RuntimeException` when the archive is built.

```php
Doc::zip([
    'كشف-رواتب-سبتمبر.pdf' => Doc::template('report', $payroll)->pdf(),
    Doc::template('payslip', $ahmed)->pdf('قسيمة-أحمد.pdf'),
    Doc::template('payslip', $sara)->pdf('قسيمة-سارة.pdf'),
], 'payroll-2026-09.zip')->save('payroll/2026-09.zip', 's3');
```

## SaveDocument job {#save-document}

`BiztechEG\EasyPdfWord\Jobs\SaveDocument` is the job `->queue()` dispatches. It implements `ShouldQueue` and `ShouldBeEncrypted` (Laravel encrypts its payload with the app key, since it carries the document's data and any PDF password) and uses `Dispatchable`, `InteractsWithQueue` and `Queueable`.

```php
new SaveDocument(array $document, string $format, string $path, ?string $disk = null)
```

| Public property | Type | Value |
| --- | --- | --- |
| `$document` | `array` | The document's source and settings (*internal* shape) |
| `$format` | `string` | `'pdf'` or `'word'` |
| `$path` | `string` | Where the file is saved |
| `$disk` | `?string` | The disk, or `null` for the default disk (or an absolute path) |

```php
handle(DocFactory $factory): void
```

Rebuilds the document, renders it and saves it to `$path` on `$disk`. The file name is the last part of the path. Check the job in tests with `Queue::fake()`:

```php
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use Illuminate\Support\Facades\Queue;

Queue::fake();

Doc::template('invoice', $data)->queue('invoices/INV-2026-1024.pdf', 's3');

Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->format === 'pdf'
    && $job->path === 'invoices/INV-2026-1024.pdf'
    && $job->disk === 's3');
```

## Testing {#testing}

### DocFake {#doc-fake-class}

`BiztechEG\EasyPdfWord\Testing\DocFake` extends `DocFactory` and implements Laravel's `Fake`. After `Doc::fake()`, `Doc::template()`, `view()`, `html()` and `make()` work as usual (data is still merged with defaults, prepared and validated), but `->pdf()` and `->word()` return placeholder files and record a [`GeneratedDocument`](#generated-document). Saves, downloads and streams are recorded instead of done. `Doc::zip()` still builds an archive from the placeholder files. Guide: [Testing your app](/guide/testing).

| Method | Passes when |
| --- | --- |
| `generated(?Closure $callback = null): array` | Not an assertion: every recorded `GeneratedDocument`, or those the callback accepts |
| `assertGenerated(?Closure $callback = null): void` | At least one file was made (that the callback accepts) |
| `assertNotGenerated(Closure $callback): void` | No file matches the callback |
| `assertGeneratedCount(int $count): void` | Exactly `$count` files were made |
| `assertNothingGenerated(): void` | No file was made |
| `assertSaved(string\|Closure $path, ?string $disk = null): void` | A file was saved to `$path` (on `$disk`, when given), or a saved file matches the callback |
| `assertDownloaded(string\|Closure\|null $filename = null): void` | A file was sent as a download (with that name, or matching the callback) |
| `assertStreamed(string\|Closure\|null $filename = null): void` | A file was shown in the browser with `stream()`, `inline()` or returned from a controller |

The callbacks receive a `GeneratedDocument` and return `bool`. Call them all on the facade: `Doc::assertSaved(...)`.

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;

Doc::fake();

Doc::template('invoice', $data)->locale('ar')->pdf('فاتورة-1024.pdf')->save('invoices/INV-2026-1024.pdf', 's3');

Doc::assertGeneratedCount(1);
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'invoice'
    && $doc->data('totals.total') == 28500
    && $doc->contains('مؤسسة النور'));
Doc::assertSaved('invoices/INV-2026-1024.pdf', 's3');
```

### GeneratedDocument {#generated-document}

`BiztechEG\EasyPdfWord\Testing\GeneratedDocument` describes one file asked for while the fake is on.

| Public property | Type | Value |
| --- | --- | --- |
| `format` | `string` | `'pdf'` or `'word'` |
| `template` | `?string` | The template name, or `null` |
| `view` | `?string` | The Blade view name, or `null` |
| `locale` | `string` | The resolved locale, e.g. `'ar'` |
| `direction` | `string` | `'rtl'` or `'ltr'` |
| `numerals` | `string` | `'arabic'` or `'latin'` |
| `driver` | `?string` | The engine given with `->driver()`, or `null` |
| `watermark` | `?string` | The PDF watermark text (in the document's digits), or `null` |
| `protected` | `bool` | Whether the PDF has a password |

| Method | Returns |
| --- | --- |
| `isPdf(): bool`, `isWord(): bool` | The format |
| `filename(): string` | The name with its extension, as a download would get it |
| `data(?string $key = null, mixed $default = null): mixed` | The data the file was made from; for templates after defaults and `prepare` (so totals are there). A dot key picks one value |
| `html(): string` | The HTML a PDF engine would get. Throws `LogicException` for Word files |
| `contains(string $text): bool` | Whether the PDF's HTML contains the text, escaped as Blade prints it. With Arabic digits, write the text in Arabic digits |
| `saves(): array` | Every save: `['path' => ..., 'disk' => ...]` |
| `wasSaved(?string $path = null, ?string $disk = null): bool` | Saved (to that path and disk) |
| `wasDownloaded(?string $filename = null): bool` | Sent as a download (with that name) |
| `wasStreamed(?string $filename = null): bool` | Shown in the browser (with that name) |

`for()`, `content()`, `recordSave()` and `recordResponse()` are *internal*.

## Templates registry {#template-registry}

`Doc::templates()` returns the `BiztechEG\EasyPdfWord\Templates\TemplateRegistry` singleton.

| Method | Description |
| --- | --- |
| `get(string $name): Template` | The template, or `TemplateNotFound` |
| `exists(string $name): bool` | Whether a template of that name can be found |
| `all(): array` | `name => Template` for every folder with a `template.php`, project folders first |
| `paths(): array` | The folders searched, in order; the package's own folder is last |
| `addPath(string $path, bool $first = true): static` | Adds a folder to search, first (default) or last |
| `isBundled(string $name): bool` | Whether the name resolves to the package's own template |
| `static packagePath(): string` | The package's `resources/templates` folder |
| `static isValidName(string $name): bool` | Letters, digits, `.`, `-`, `_`, and not `.` or `..` |

```php
// Templates kept by a module of your app, searched before the others.
Doc::templates()->addPath(base_path('modules/Sales/doc-templates'));

foreach (Doc::templates()->all() as $name => $template) {
    echo $name.': '.$template->title().PHP_EOL;
}
```

### Template {#template-class}

`BiztechEG\EasyPdfWord\Templates\Template` is one template folder. It reads `template.php` (see [template.php keys](/reference/template-helpers#template-php)).

| Member | Returns |
| --- | --- |
| `name`, `path` | Public read-only: the folder name and its full path |
| `title(): string` | `title`, or the name |
| `description(): string` | `description`, or `''` |
| `locales(): array` | `locales`, or `['ar', 'en']` |
| `rules(): array` | `fields`: Laravel validation rules |
| `defaults(): array` | `defaults` |
| `prepare(array $data, array $theme = []): array` | The data after the `prepare` callback |
| `sample(): array` | `sample` (a closure is called) |
| `theme(): array` | `theme` |
| `paper()`, `orientation(): ?string`, `margins(): ?array` | Page settings, or `null`. `paper()` returns a name such as `'A4-L'` or `[width, height]` in mm |
| `hasPdfView(): bool`, `pdfView(): string` | `pdf.html.php`, else `pdf.blade.php`; `pdfView()` throws `RuntimeException` when it is missing |
| `wordFile(): ?string` | The path of `word.docx`, or `null` |
| `wordLayout(): ?callable` | `word.php`, else `layout.php` |
| `pdfLayout(): ?callable` | `layout.php`, else `word.php` (used when there is no PDF page) |
| `supportsPdf(): bool`, `supportsWord(): bool` | Which formats the folder can make |
| `headerView(): ?string`, `footerView(): ?string` | Paths of the header and footer files: `.html.php`, else `.blade.php` |
| `translations(string $locale): array` | The labels in `lang/{language}.php` |

## FontRegistry {#font-registry}

`Doc::fonts()` returns the `BiztechEG\EasyPdfWord\Fonts\FontRegistry` singleton, filled from the bundled fonts and `fonts.custom`.

| Method | Description |
| --- | --- |
| `register(string $name, array $files): static` | Adds a font. `$files`: `regular` (required), `bold`, `italic`, `bold_italic` (`.ttf` paths), `arabic` (default `true`), `arabic_separators` (draw ٫ and ٬ with Arabic digits). Without `regular` it throws `InvalidArgumentException` |
| `has(string $name): bool` | Whether the font is known (any case) |
| `get(string $name): array` | Its files; `InvalidArgumentException` when unknown |
| `all(): array` | Every font: `name => files` |
| `supportsArabic(string $name): bool` | Whether the font is marked as covering Arabic |
| `hasArabicSeparators(string $name): bool` | Whether Arabic digits use ٫ and ٬ with this font (only `naskh` of the bundled fonts) |
| `cssFontFaces(array $names): string` | `@font-face` rules with the font files embedded, for engines that load fonts from CSS |
| `forMpdf(int $kashida = 75): array` | *Internal*: the font table for mPDF |

```php
Doc::fonts()->register('amiri', [
    'regular' => resource_path('fonts/Amiri-Regular.ttf'),
    'bold'    => resource_path('fonts/Amiri-Bold.ttf'),
]);

Doc::template('letter', $letter)->font('amiri')->pdf();
```

Registering in the `fonts.custom` config does the same for every request.

## PdfManager {#pdf-manager}

`Doc::pdfManager()` returns the `BiztechEG\EasyPdfWord\Pdf\PdfManager` singleton, a Laravel `Manager`.

| Method | Description |
| --- | --- |
| `driver($driver = null)` | The `PdfDriver` by name (any case; `chromium` and `chrome` mean `browsershot`), or the default engine |
| `extend($driver, Closure $callback)` | What `Doc::extend()` calls |
| `getDefaultDriver(): string` | `pdf.driver` from the config |
| `normalize(?string $driver): string` | The name as stored: lowercased, aliases resolved |
| `engineConfig(string $name): array` | `pdf.drivers.{name}` from the config |
| `render(string $html, PdfOptions $options, ?string $driver = null, ?callable $htmlFor = null): array` | *Internal*: renders with fallback and returns `[bytes, engine name]` |

```php
if (! Doc::pdfManager()->driver('chromium')->isAvailable()) {
    // spatie/browsershot is not installed
}
```

## Your own PDF engine {#pdf-driver}

Implement `BiztechEG\EasyPdfWord\Contracts\PdfDriver` and register it with [`Doc::extend()`](#doc-extend). The built-in engines are described in [PDF engines](/guide/engines).

```php
interface PdfDriver
{
    /** Turn a full HTML document into PDF bytes. */
    public function render(string $html, PdfOptions $options): string;

    /** Whether the engine's package or service is installed, so the manager can fall back. */
    public function isAvailable(): bool;

    /** Whether the engine loads fonts from CSS @font-face (Chromium) rather than its own configuration (mPDF). */
    public function usesCssFonts(): bool;
}
```

- `render()` gets the final HTML (digits already converted) and a [`PdfOptions`](#pdf-options). It should apply the paper size, margins, header and footer (replacing `{page}` and `{pages}`), and the watermark if you support it; `BiztechEG\EasyPdfWord\Pdf\Watermark::inject($html, $options)` adds the watermark the way the Chromium engines do.
- When `usesCssFonts()` is `true`, the package adds `@font-face` rules to the HTML for the document font and for every registered font the page's CSS names (`font-family: 'naskh'`), and fetches allowed remote images itself and inlines them, so your engine never loads a URL.
- A password is added after `render()` by mPDF, so your engine does not need to encrypt.
- When `isAvailable()` is `false` or `render()` throws, the fallback engine renders the document.

```php
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use Illuminate\Support\Facades\Http;

class PdfServiceDriver implements PdfDriver
{
    public function __construct(private string $url) {}

    public function render(string $html, PdfOptions $options): string
    {
        [$width, $height] = $options->paperSize();

        return Http::timeout(60)->post($this->url, [
            'html' => $html,
            'width_mm' => $width,
            'height_mm' => $height,
            'margins_mm' => $options->margins,
        ])->throw()->body();
    }

    public function isAvailable(): bool
    {
        return $this->url !== '';
    }

    public function usesCssFonts(): bool
    {
        return true;
    }
}

// AppServiceProvider::boot()
Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver((string) config('services.pdf.url')));

// Anywhere
Doc::template('invoice', $data)->driver('pdf-service')->pdf()->engine();   // 'pdf-service'
```

### PdfOptions {#pdf-options}

`BiztechEG\EasyPdfWord\Pdf\PdfOptions` carries the page and document settings to the engine. All properties are public.

| Property | Type | Default |
| --- | --- | --- |
| `paper` | `string` or `[width, height]` in mm | `'A4'` |
| `orientation` | `string` | `'portrait'` |
| `margins` | `[top, right, bottom, left]` in mm | `[15, 15, 15, 15]` |
| `direction` | `string` | `'ltr'` |
| `locale` | `string` | `'en'` |
| `font` | `string` | `'cairo'` |
| `header`, `footer` | `?string` HTML with `{page}` and `{pages}` | `null` |
| `title`, `author` | `?string` | `null` |
| `numerals` | `string` | `'latin'` |
| `watermark` | `['text' => ..., 'opacity' => ..., 'color' => ...]` or `null` | `null` |
| `protection` | `['user' => ..., 'owner' => ..., 'allow' => [...]]` or `null` | `null` |

| Method | Returns |
| --- | --- |
| `isLandscape(): bool` | Whether the orientation is `landscape` (or `L`) |
| `paperSize(): array` | `[width, height]` in mm with the orientation applied |
| `static unknownPaper(string $paper): InvalidArgumentException` | The error for an unknown size |
| `PdfOptions::PAPER_SIZES` | Constant: `name => [width, height]` in mm for `A2` ... `EXECUTIVE` |

## Exceptions {#exceptions}

The package's own exceptions are in `BiztechEG\EasyPdfWord\Exceptions`:

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `TemplateNotFound` | `InvalidArgumentException` | `Doc::template($name)` or `Doc::templates()->get($name)` finds no folder with that name (or the name has other characters than letters, digits, `.`, `-`, `_`). The message lists the folders searched |
| `WordNotSupported` | `LogicException` | `->word()` or `->queue('….docx')` on a Blade view or HTML document, or on a template without `layout.php`, `word.php` or `word.docx` |
| `DriverNotAvailable` | `RuntimeException` | The chosen PDF engine is not installed or configured (`mpdf/mpdf`, `spatie/browsershot`, `DOC_GOTENBERG_URL`, or your engine's `isAvailable()` is `false`) and the fallback cannot render either; a Word file is rendered without `phpoffice/phpword`; a password is set with Chromium or Gotenberg and `mpdf/mpdf` is missing |

Other exceptions you may meet:

| Exception | When |
| --- | --- |
| `Illuminate\Validation\ValidationException` | Template data fails the template's `fields` rules: on `->pdf()`, `->word()`, `->queue()`, `->toHtml()` and `->options()` at once |
| `InvalidArgumentException` | An invalid locale, font name, paper size, numerals style, an unknown PDF engine name, empty watermark, unknown password permission, a `->queue()` path not ending in `.pdf` or `.docx`, an empty or wrong `Doc::zip()` list |
| `LogicException` | `->word()` or `->queue('….docx')` on a document with a password; `GeneratedDocument::html()` on a Word file |
| `BadMethodCallException` | A block method (`heading`, `table` ...) on a template, view or HTML document, or a method that does not exist |
| `RuntimeException` | A save that could not be written, a ZIP without `ext-zip`, a Hijri date without `ext-intl`, an mPDF temp folder that cannot be written, an engine error with no fallback |

::: tip
An engine name that is neither built in nor registered with `Doc::extend()`, such as a typo, throws instead of falling back. The fallback is only for engines that exist but are not installed or fail; check `->pdf()->engine()` if you are not sure which engine ran.
:::
