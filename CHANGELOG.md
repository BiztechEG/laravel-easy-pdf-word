# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- A template validator that works without Laravel (`Validation\RuleValidator`). It understands the rules templates use (required, required_if, present, nullable, string, numeric, integer, boolean, array, date, date_format, min, max, size, in, gt, after_or_equal, regex) with Laravel's meaning and messages; a test checks it against Laravel's validator on every bundled template. Laravel apps keep using Laravel's validator.
- `Exceptions\ValidationFailed::withMessages()` for checks in a template's `prepare`. Laravel apps receive it as Laravel's `ValidationException`, as before.
- `EasyPdfWord::create($config)` uses the library without Laravel: `->make()`, `->html()`, `->view()` and templates with plain PHP views give `Document` objects whose `->pdf()` and `->word()` files can be saved, sent to the browser or zipped. The settings are those of `config/easy-pdf-word.php` (`EasyPdfWord::defaults()`).
- Templates may use plain PHP views: `pdf.html.php`, `footer.html.php` and `header.html.php` work in and out of Laravel; the `.blade.php` files keep working in Laravel.
- `View\PageLayout::render()` prints the same page as `<x-doc::layout>` for plain PHP views.
- `$doc->monthName()`, `$doc->dayName()` and `$doc->timezone()` for templates, without Carbon, and `$doc->e()` to escape values in plain PHP views.
- `doc:make-template --blade` writes the new template's PDF page and footer in Blade.

### Changed

- Everything that works without Laravel now lives in its own package, [biztecheg/easy-pdf-word](https://github.com/BiztechEG/easy-pdf-word), which Composer installs with this one. The namespace, the `Doc` facade, the config, the templates and the commands are unchanged, so apps need no changes. The font fix script is now at `vendor/biztecheg/easy-pdf-word/bin/mpdf-font-fix.py`.
- The PDF engine manager and the Gotenberg engine no longer need Laravel. Outside Laravel, Gotenberg is reached with the curl extension. In Laravel nothing changes: `Doc::extend()` callbacks still receive the app, `Http::fake()` still sees Gotenberg requests, and fallbacks are still logged as warnings. `PdfManager` no longer extends Laravel's `Manager`, but keeps `driver()`, `extend()`, `getDrivers()` and `forgetDrivers()`; `GotenbergDriver` now takes a `Contracts\HttpClient`.
- `PendingDocument` and `DocFactory` now extend the framework-free `Document` and `DocumentFactory`, and take a `DocumentServices` object instead of the view factory and config. Code that only uses the `Doc` facade is not affected. HTML fragments and `Doc::make()` documents get their page from `View\PageLayout` (the same HTML as before), so the `easy-pdf-word::raw` view is gone.
- The bundled templates are plain PHP: the invoice, letter, report and certificate pages are `pdf.html.php`, the footers are `footer.html.php`, and `layout.php`, `word.php` and `template.php` no longer use Carbon or Laravel helpers. All twelve make PDF and Word files without Laravel, and their output in Laravel is unchanged (the PDFs are pixel-identical). Copies made with `doc:template` and new templates from `doc:make-template` are plain PHP too; Blade copies made before keep working in Laravel. The report's `generated_at` default is now a `DateTimeImmutable` instead of a Carbon date.
- The Arabic helpers, the ZATCA QR, the document builder, Word templates, watermarks and the Chromium engine no longer use Laravel classes or helpers, the first step to a PHP core that works without Laravel. Behaviour is unchanged, and `Carbon::setTestNow()` / `travelTo()` still set today's date in documents.

## [1.3.0] - 2026-10-09

### Added

- Paper sizes `A2`, `B4`, `B5`, `Tabloid` and `Executive`, and `A4-L` for A4 landscape.
- `node_modules_path` (`DOC_NODE_MODULES_PATH`) for Browsershot, which saves running `npm root -g` for every document.
- Word files take WebP and BMP images, turned into PNG.
- `->paper([width, height])` in mm, and `[width, height]` or `A4-L` in `template.php` and the config.
- The e-invoice prints the ETA submission ID, names the non-taxable taxes T13 to T20 and accepts lowercase tax types.
- The invoice prints the amount in words in English too (with `ext-intl`), like the other templates.
- Chromium gets every registered font the page's CSS names, so `font-family: 'naskh'` works there as in mPDF.
- Word files take `rgb()`, `hsl()` and `#RRGGBBAA` colours.

### Changed

- The package requires `laravel/framework` instead of single `illuminate/*` packages, as it already needed the framework. Apps with mPDF older than 8.2, PhpWord older than 1.4 or Browsershot older than 5.4 now get a Composer conflict instead of a runtime error.
- mPDF works in a private folder per system user (`/tmp/easy-pdf-word-{uid}`), so the web server and a queue worker running as different users no longer lock each other out.
- Unknown paper names, a line discount larger than the line amount, and custom fonts whose files share a name now fail with a clear message instead of rendering something wrong.
- A misspelt engine name (`->driver('chromuim')`) is an error instead of a silent fallback to mPDF.
- `->word()` checks the template data when it is called, as `->pdf()` does.
- Certificates refuse zero hours, and a letter's `cc` entries must be text.
- An empty `DOC_PREVIEW=` means the same as leaving it out: the preview page is on in `local` only.
- `doc:template` with an unknown name prints an error instead of throwing.

### Fixed

- mPDF failed on HTML over 1 MB (`pcre.backtrack_limit`): a report of about 1,650 rows, or an invoice with a large logo.
- Saving to a disk that refused the write looked successful, and a queued save finished without a file.
- Queued documents without `->locale()` rendered in the worker's locale instead of the request's.
- When the fallback engine was not installed, the real engine error was replaced by "needs mpdf/mpdf".
- A Gotenberg answer that was not a PDF (a sign-in page) was saved as the PDF.
- Chromium headers and footers used a system font instead of the document font.
- Word files: SVG, WebP and BMP images made `->word()` throw; footers printed `<style>` contents; `word.docx` templates printed SVG paths, rounded KWD amounts to 2 decimals and left tables empty for lists with gaps in their keys.
- Items, lines and contract clauses were numbered from their array keys, and clauses not keyed from 0 crashed the contract.
- Report totals read `1,240` as 1, and report columns without a label crashed.
- The ZATCA QR showed the day before for a date without a time east of UTC, and read `'1,150.00'` as 1.
- Amounts between -1 and 0 lost their minus in words, and `-0` read as "سالب صفر".
- `rate('1,500')` printed 1.
- Arabic digits broke links containing `&amp;` and attributes containing `>`.
- Custom headers and footers ignored `->numerals('arabic')`.
- `ltr` on a whole paragraph or heading worked in Word only.
- Margins with fewer than four values in the config or `template.php` crashed.
- Engine names given to `Doc::extend()` in mixed case were never found.
- `doc:template NAME --force` did not restore the bundled template over a project copy.
- The e-invoice printed ETA's UTC issue time as local time.
- Receipt signature roles without a label printed their translation key, and the receipt took two pages with Chromium.
- Certificates said "103 ساعة" instead of "103 ساعات".
- Browsershot temp pages and PhpWord template copies were left in the temp folder when a render failed.
- `template.php` with `'paper' => [width, height]` crashed.
- `$doc->t()` replaced `:page` inside `:pages` ("صفحة 3 من 3s").
- mPDF swapped the right and left margins of Arabic documents.
- PDFs from 8 of the 12 templates had no title, and `->title()` was ignored.
- The word "الله" was lost when text was copied or searched in Cairo PDFs.
- mPDF kept using its cached copy of a font file after the file changed.
- `word.docx` templates printed the path of an image they could not read, reversed phone numbers, dates and codes in Arabic paragraphs, and read the currency only from `invoice` or `document`.
- Arabic download names became a row of underscores for clients that do not read UTF-8 names; they are now written in Latin letters.
- The receipt's amount box stayed teal with any theme colour, and English contracts named the 11th party "11 party".

### Security

- A report column's `decimals` from data could build a huge string; decimals are capped at 10.
- Word images from allowed hosts were fetched by PhpWord, which followed redirects past the allowed hosts. They are now fetched without redirects and with a 10 second limit, as mPDF's remote images also are.
- Gotenberg ran JavaScript in every page; it is now off unless `DOC_CHROME_JAVASCRIPT` is on, as with Browsershot.
- Chromium and Gotenberg followed redirects on allowed remote images, past the allowed hosts. The package now fetches those images itself, without redirects, as it does for mPDF and Word.

## [1.2.0] - 2026-10-08

### Added

- `purchase-order` template: a purchase order to a supplier with item codes and units, discounts, optional VAT, delivery date and place, payment terms and approval signatures.
- `delivery-note` template: a delivery note with ordered, delivered and remaining quantities, packages, driver and vehicle, and the receiver's acknowledgement.
- `credit-note` template: a credit or debit note against an invoice, with the reason, VAT, amount in words and a ZATCA or e-invoice QR.
- `payslip` template: a monthly payslip with earnings and deductions side by side, the net pay in figures and words, attendance and signatures.
- `contract` template: a contract between two or more parties with a preamble, numbered clauses (البند الأول، البند الثاني ...), copies, signatures, witnesses and initials on every page.
- `certificate` template: a landscape certificate of completion, attendance, participation or appreciation with up to three signatures and a verification QR. The Arabic wording follows the recipient's gender.
- Builder paragraphs take `space_after` and `line_height` in PDF files too, as they already did in Word.

### Changed

- `doc:sample` picks the format from the `--output` extension when `--format` is not given, so `--output=invoice.docx` makes a Word file.
- `doc:make-template` warns when the new template has the name of a bundled one, which it then replaces in the app.
- A heading stays on the same page as the text after it, in PDF and Word.

## [1.1.0] - 2026-10-08

### Added

- PDF and Word files can be attached to mail as they are: return one from a Mailable's `attachments()` or pass it to a notification's `->attach()`.
- `->filename()` on a rendered file gives its name with the extension.
- `Doc::fake()` records documents instead of rendering them, with `assertGenerated`, `assertNotGenerated`, `assertGeneratedCount`, `assertNothingGenerated`, `assertSaved`, `assertDownloaded` and `assertStreamed`.
- `->watermark($text, opacity, color)` prints text across every page of a PDF, with mPDF, Chromium and Gotenberg.
- `->password($user, owner, allow)` encrypts a PDF. Chromium files are encrypted afterwards by mPDF.
- `Doc::zip($files, $filename)` puts PDF and Word files in one ZIP archive to download, save or attach to mail.
- `->queue($path, disk)` renders and saves a document on a queue worker, with `->onQueue()`, `->delay()` and `->chain()`. Template data is validated before the job is queued.

### Changed

- The package now requires `illuminate/bus` and `illuminate/queue`, which every Laravel app already has. `ext-zip` is suggested for `Doc::zip()`.

### Security

- Queued documents are encrypted with the app key, since the job carries the document's data and any PDF password.
- A document with a password is never made into a Word file, which cannot be encrypted: `->word()` and `->queue('….docx')` refuse it.

## [1.0.0] - 2026-10-08

### Added

The first release.

- PDF output with mPDF (default), Chromium through Browsershot, or Gotenberg. The engine is picked in config, `.env` or per call, and falls back to mPDF when the chosen one is missing.
- Word (.docx) output with PhpWord: right-to-left Arabic documents built in code (`Doc::make()`), or Word files designed in Word with `${placeholders}`.
- Arabic everywhere: shaping, right-to-left layout, Arabic-Indic digits, amounts in words (tafqeet) and Hijri dates.
- Six ready-made templates in Arabic and English: Saudi tax invoice with the ZATCA QR code, Egyptian e-invoice (ETA), quotation, receipt and payment voucher, formal letter, and tabular report.
- Your own templates: copy a bundled one with `doc:template`, or start a new one with `doc:make-template`.
- `doc:templates` lists the templates, and `doc:sample` renders one with its sample data.
- A local preview page at `/doc-preview` to try templates, engines and languages in the browser.
- Bundled Arabic fonts: Cairo, Noto Naskh Arabic and Tajawal.
- Currency-aware rounding, including three-decimal currencies such as KWD.

### Security

- Remote images are off by default, and allowed hosts are listed in config.
- The preview page is on in the local environment only. Anywhere else it has to be turned on and passes through the `viewDocPreview` gate.
- Colour values, fonts and locales are checked before they reach HTML, CSS or file paths.
- Chromium runs without JavaScript unless it is turned on in config.

[Unreleased]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/releases/tag/v1.0.0
