# Plain PHP

The library also works without Laravel: in a plain PHP script, WordPress, Symfony, CodeIgniter or any framework. It is the package [biztecheg/easy-pdf-word](https://github.com/BiztechEG/easy-pdf-word), which the Laravel package is built on. It has the same templates, engines, Arabic support and Word files; only the Laravel conveniences are missing. This page shows how to use it. [WordPress](/guide/wordpress) and [Symfony](/guide/symfony) have their own pages.

## Install {#install}

```bash
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

This installs the library, the mPDF engine and PhpWord for Word files. You need PHP 8.2 or newer with `mbstring` and `gd`; `intl` for Hijri dates and `zip` for Word files and ZIP archives. For Chromium instead of mPDF, see [PDF engines](/guide/engines); the engines work the same way here.

## Create the factory {#create}

Everything starts with `EasyPdfWord::create()`. It takes the same settings as the Laravel package's [config file](/guide/configuration), as a PHP array; leave out what you do not change.

```php
require __DIR__.'/vendor/autoload.php';

use BiztechEG\EasyPdfWord\EasyPdfWord;

$docs = EasyPdfWord::create([
    'locale' => 'ar',
    'theme' => [
        'primary' => '#0F766E',
        'logo' => __DIR__.'/assets/logo.png',
        'company' => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    ],
    'images' => ['paths' => [__DIR__.'/assets']],
]);
```

Create it once and reuse it, for example in a small function or your framework's service container. `EasyPdfWord::defaults()` returns every setting with its default value.

The settings you are most likely to change:

| Setting | Default | What it does |
| --- | --- | --- |
| `locale` | `en` | The documents' language: `ar` gives Arabic labels and a right-to-left page |
| `numerals` | `latin` | `arabic` prints ٠١٢٣ |
| `theme` | | Colours, logo and company details shown on every template |
| `pdf.driver` | `mpdf` | `mpdf`, `browsershot` or `gotenberg`; see [PDF engines](/guide/engines) |
| `templates.paths` | `[]` | Folders with your own templates, searched before the bundled ones |
| `views.paths` | `[]` | Folders where `->view('contracts.show')` finds `contracts/show.html.php` |
| `images.paths` | `[]` | Folders local images (logo, signature, stamp) may come from; none by default |
| `fonts.custom` | `[]` | Your own fonts; see [Fonts](/guide/fonts) |

Lists such as `images.paths` or `pdf.margins` replace the default list; other settings merge key by key.

## Make documents {#make}

The factory has the same four ways to start a document as the Laravel `Doc` facade, and the document has the same settings (`->locale()`, `->paper()`, `->font()`, `->watermark()`, `->password()` ...). The rest of this guide applies as written; read `$docs->` where it says `Doc::`.

```php
// A ready-made template, with your data
$invoice = $docs->template('invoice', [
    'invoice' => ['number' => 'INV-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'seller'  => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    'buyer'   => ['name' => 'مؤسسة النور'],
    'items'   => [['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000]],
]);

// A document built in code
$report = $docs->make()
    ->heading('تقرير المبيعات')
    ->table([['البند', 'القيمة'], ['المبيعات', '120,000']], ['header' => true]);

// Your own HTML
$page = $docs->html('<h1>مرحباً</h1>');

// Your own view: views/contracts/show.html.php
$contract = $docs->view('contracts.show', ['contract' => $data]);
```

Templates and documents built in code make both PDF and Word files. HTML and views make PDF files.

| Start from | Guide |
| --- | --- |
| `$docs->template()` | [Ready-made templates](/guide/templates), [Your own templates](/guide/custom-templates) |
| `$docs->make()` | [Building in code](/guide/builder), [Word files](/guide/word) |
| `$docs->html()`, `$docs->view()` | [Your own views](#views) below |

## Save or send the file {#output}

`->pdf()` and `->word()` return a file. Nothing is rendered until you ask for its bytes, and then only once.

```php
$file = $invoice->pdf('INV-1024.pdf');

$file->save(__DIR__.'/storage/INV-1024.pdf');   // write it to a folder (created if missing)
$file->send();                                  // send it to the browser as a download
$file->send('فاتورة-1024.pdf', inline: true);   // or show it in the browser, under another name
$bytes = $file->content();                       // the bytes, for an email or an upload
$file->base64();                                 // the bytes in base64, for an API
$file->mimeType();                               // application/pdf
$file->filename();                               // INV-1024.pdf
$file->engine();                                 // the engine that made it: mpdf, browsershot ...
```

`send()` sets the `Content-Type`, `Content-Length` and `Content-Disposition` headers and prints the file. Call it before anything else is printed, and stop the script after it. An Arabic file name is kept for browsers, with a Latin fallback for old clients.

Several files go in one ZIP archive. The keys are the names inside the archive:

```php
$docs->zip([
    'INV-1024.pdf' => $invoice->pdf(),
    'INV-1024.docx' => $invoice->word(),
], 'INV-1024.zip')->send();
```

## Wrong data {#validation}

Template data is checked against the template's rules before anything is rendered. A mistake throws `ValidationFailed`, whose `errors()` lists the messages by field:

```php
use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;

try {
    $docs->template('invoice', $data)->pdf()->save($path);
} catch (ValidationFailed $e) {
    $e->errors();   // ['invoice.number' => ['The invoice.number field is required.'], ...]
}
```

The rules and messages are Laravel's, without Laravel. The messages are in English. `->withoutValidation()` skips the check.

## Your own views {#views}

A view is a plain PHP file that prints HTML. It gets your data as variables, and `$doc`, the document helpers:

```php
<?php // views/contracts/show.html.php ?>
<h1><?= $doc->e($contract['title']) ?></h1>
<p><?= $doc->e($contract['client']) ?></p>
<p><?= $doc->money($contract['total'], 'EGP') ?></p>
<p><?= $doc->e($doc->tafqeet($contract['total'], 'EGP')) ?></p>
```

Escape every value from your data with `$doc->e()`. The library wraps the view in a full page with the document's language, direction, fonts and theme; [Template helpers](/reference/template-helpers) lists every helper. `$docs->html($html)` works the same way with HTML you already have.

## Your own templates {#templates}

A template is a folder; [Your own templates](/guide/custom-templates) explains every file. Put your folders under one path and add it to `templates.paths`:

```php
$docs = EasyPdfWord::create(['templates' => ['paths' => [__DIR__.'/doc-templates']]]);

$docs->template('warranty', $data)->pdf()->save(__DIR__.'/warranty.pdf');
```

There is no `doc:template` command outside Laravel. To change a bundled template, copy its folder from `vendor/biztecheg/easy-pdf-word/resources/templates/` into your path and edit the copy; it is used instead of the bundled one because your folders come first. Each template's sample data is a quick way to try it:

```php
$letter = $docs->templates()->get('letter');

$docs->template('letter', $letter->sample())->pdf()->save(__DIR__.'/letter.pdf');
```

## Engine warnings {#warnings}

When an engine fails and the library falls back to mPDF, or an image is left out because its folder is not in `images.paths` or its host is not allowed, the library calls a `warn` function you pass as a second argument. Without one, the warning goes to PHP's `error_log()`.

```php
$docs = EasyPdfWord::create($config, [
    'warn' => fn (string $message) => $logger->warning($message),
]);
```

## What only Laravel has {#laravel-only}

These come from the Laravel package. Outside Laravel, use the plain PHP way:

| Laravel | Plain PHP |
| --- | --- |
| `Doc::template()` and the `Doc` facade | `$docs->template()` on the factory from `create()` |
| Blade views and `.blade.php` templates | `.html.php` views and templates, which also work in Laravel |
| `config/easy-pdf-word.php` and `.env` | The array you pass to `create()` |
| `->download()`, `->stream()`, returning the file from a controller | `->send()`, or `->content()` in your framework's response |
| `->save($path, 's3')` on a storage disk | `->save($path)` to a local folder, or `->content()` to your storage |
| Mail attachments, `->queue()` | `->content()` with your mailer or queue |
| `Doc::fake()` | Your own test double |
| The preview page and the Artisan commands | `$docs->templates()->get($name)->sample()` |
| Laravel's translated validation messages | English messages |
