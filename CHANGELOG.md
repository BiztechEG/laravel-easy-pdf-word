# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `purchase-order` template: a purchase order to a supplier with item codes and units, discounts, optional VAT, delivery date and place, payment terms and approval signatures.
- `delivery-note` template: a delivery note with ordered, delivered and remaining quantities, packages, driver and vehicle, and the receiver's acknowledgement.
- `credit-note` template: a credit or debit note against an invoice, with the reason, VAT, amount in words and a ZATCA or e-invoice QR.

### Changed

- `doc:sample` picks the format from the `--output` extension when `--format` is not given, so `--output=invoice.docx` makes a Word file.
- `doc:make-template` warns when the new template has the name of a bundled one, which it then replaces in the app.

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

[Unreleased]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/releases/tag/v1.0.0
