# Introduction

Laravel Easy PDF & Word makes PDF and Word (.docx) files from a Laravel app, in any language, with Arabic that renders correctly. This page explains the few ideas you need before anything else.

## What the package does {#what-it-does}

You give the package data, and it gives you back a file: a tax invoice, a quotation, a payslip, a contract, a report or any document you design. It runs inside your Laravel app, so the file can be downloaded, shown in the browser, saved to a disk, attached to an email or made on a queue worker.

It is built for Laravel developers who make business documents, in particular for Arabic-speaking markets such as Egypt, Saudi Arabia and the Gulf. Arabic letters are joined, pages and tables run right to left, Arabic and English mix in one line without breaking phone numbers or invoice codes, and amounts can be written in Arabic words (تفقيط). English and other languages work just as well: the same document switches language with one call.

## Three ways to make a document {#three-ways}

Every document starts with one of these calls on the `Doc` facade:

| You start from | Call | Formats | Good for |
| --- | --- | --- | --- |
| A template | `Doc::template('invoice', $data)` | PDF and Word | Invoices, quotations, payslips and the other [ready-made templates](/guide/templates), or [your own templates](/guide/custom-templates) |
| Your own Blade view or HTML | `Doc::view('pdf.contract', $data)`, `Doc::html($html)` | PDF | A design you already have in HTML and CSS |
| Code | `Doc::make()->heading(...)->table(...)` | PDF and Word | Documents assembled from data, such as a report or a price list |

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

// 1. A template, with your data
Doc::template('invoice', $data)->locale('ar')->pdf()->download('فاتورة-1024.pdf');

// 2. Your own Blade view (resources/views/pdf/contract.blade.php)
Doc::view('pdf.contract', ['contract' => $contract])->locale('ar')->pdf()->stream();

// 3. Code, block by block
Doc::make()
    ->heading('تقرير المبيعات')
    ->table([
        ['الفرع', 'الإيرادات'],
        ['القاهرة', '486,500.75'],
    ], ['header' => true])
    ->locale('ar')
    ->word()
    ->download('تقرير-المبيعات.docx');
```

A **template** is a folder with a list of fields, labels in each language and a layout. The package ships twelve of them, and you can copy one to change it or create your own. Read [Ready-made templates](/guide/templates) and [Your own templates](/guide/custom-templates).

A **view** is any Blade file in your app. The package gives it a `$doc` object for the direction, fonts, colours and Arabic helpers. Read [Blade views and HTML](/guide/views-and-html).

The **builder** (`Doc::make()`) describes a document as headings, paragraphs, tables, images and QR codes. One description makes both a PDF and a Word file. Read [Building in code](/guide/builder).

## Two output formats {#formats}

After the settings, `->pdf()` or `->word()` turns the document into a file:

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

$invoice->pdf()->download('فاتورة-1024.pdf');     // a PDF
$invoice->word()->download('فاتورة-1024.docx');   // the same invoice as a Word file
```

Both files have the same methods: `download()`, `stream()` (show in the browser), `save()` (to a disk or a path) and `content()` (the bytes). A file can also be returned from a controller or attached to an email as it is. See [Output and delivery](/guide/output).

Word files come from templates and from the builder. Blade views and HTML make PDFs only, because Word cannot read HTML layouts. See [Word files](/guide/word).

## PDF engines {#engines}

A PDF is drawn by an engine. You can switch engines without changing your documents:

- **mPDF** (`mpdf`, the default): pure PHP, works anywhere PHP runs, including shared hosting. It understands CSS 2.1: tables and floats, no flexbox or grid.
- **Chromium** (`chromium`, through Browsershot): the best rendering and full modern CSS. Needs Node, Puppeteer and Chrome on the server.
- **Gotenberg** (`gotenberg`): the same Chromium quality from a Docker container, with nothing extra on the app server.

When the chosen engine is missing or fails, the document is rendered with the fallback engine (mPDF by default) and a warning is logged. The bundled templates look the same on every engine. See [PDF engines](/guide/engines).

## Settings for each document {#settings}

Settings are chained before `->pdf()` or `->word()`. Anything you do not set comes from `config/easy-pdf-word.php`.

```php
Doc::template('invoice', $data)
    ->locale('ar')                       // language, direction, labels
    ->numerals('arabic')                 // ١٢٣ instead of 123
    ->theme([                            // colours, logo and company details
        'primary' => '#1D4ED8',
        'logo' => public_path('images/logo.png'),
    ])
    ->paper('A4')->portrait()->margins(15)
    ->pdf();
```

| Setting | What it does | Default |
| --- | --- | --- |
| `->locale('ar')` | The language. Right-to-left languages (Arabic, Persian, Urdu, Hebrew ...) turn the whole document right to left. Templates pick their labels from it. | `locale` in the config, else the app locale |
| `->numerals('arabic')` | Arabic digits (١٢٣) or Latin digits (123) in the text. Tags, CSS and links are left alone. | `latin` |
| `->theme([...])` | `primary`, `text`, `muted` and `border` colours, a `logo`, and `company` details (name, address, phone, email, tax number) that templates print. | `theme` in the config |
| `->paper()`, `->landscape()`, `->margins()` | Paper size, orientation and margins in millimetres. | A4, portrait, 15 mm |

Other settings include `->font()`, `->header()` and `->footer()` with page numbers, `->watermark()` and `->password()` for PDFs, and `->driver()` to pick the engine. See [Page settings](/guide/page-settings), [Arabic support](/guide/arabic) and [Fonts](/guide/fonts).

## Features {#features}

- Twelve ready-made templates, each in Arabic and English, PDF and Word: tax invoice with the ZATCA QR code, Egyptian e-invoice (ETA), credit and debit note, price quotation, purchase order, delivery note, receipt and payment voucher, payslip, contract, certificate, official letter and table report. See the [template gallery](/templates/).
- Your own templates: copy a bundled one and change it, or start a new one with `php artisan doc:make-template`.
- Arabic everywhere: joined letters, right-to-left pages and tables, Arabic or Latin digits, amounts in words (تفقيط), Hijri dates.
- Word files with real right-to-left paragraphs and tables, from templates, from code, or from a `.docx` you design in Word with placeholders.
- Three PDF engines with automatic fallback.
- Download, show in the browser, save to any disk, attach to mail, bundle several files in a ZIP, or render on a queue.
- Bundled Arabic fonts: Cairo (the default), Tajawal and Noto Naskh Arabic.
- A [preview page](/guide/preview) that shows every template in any language, digits and engine.
- Template data is validated, values are escaped, image paths are restricted, and `Doc::fake()` keeps your [tests](/guide/testing) fast.

## Requirements {#requirements}

- PHP 8.2 or newer and Laravel 12 or 13.
- The PHP extensions `mbstring` and `gd`. `intl` is needed for Hijri dates, and `zip` for ZIP files and for PhpWord.
- At least one PDF engine: `mpdf/mpdf`, `spatie/browsershot` or a Gotenberg server.
- `phpoffice/phpword` for Word files.

[Installation](/guide/installation) walks through each of these.

## How these docs are organised {#docs-map}

- **Guide**: how the package works, one topic per page. Start with [Installation](/guide/installation) and the [Quick start](/guide/quick-start).
- **[Templates](/templates/)**: one page for each ready-made template, with a preview, its fields and an example.
- **[Recipes](/recipes/)**: complete solutions to common tasks, such as [emailing an invoice](/recipes/email-invoice), a [Saudi ZATCA invoice](/recipes/zatca-invoice) or [monthly payslips in one ZIP](/recipes/payslips-zip).
- **Reference**: every method of the [Doc API](/reference/api) and every [template helper](/reference/template-helpers) on `$doc`.

## Licence {#licence}

The package is open source under the MIT licence. Its optional mPDF engine is licensed under GPL-2.0: check that this fits your project, or use the Chromium or Gotenberg engine instead. The bundled fonts (Cairo, Tajawal and Noto Naskh Arabic) are under the SIL Open Font License.
