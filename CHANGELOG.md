# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Doc::zip($files, $filename)` puts PDF and Word files in one ZIP archive to download, save or attach to mail.
- `->queue($path, disk)` renders and saves a document on a queue worker, with `->onQueue()`, `->delay()` and `->chain()`. Template data is validated before the job is queued, and the job is encrypted.
- PDF and Word files can be attached to mail as they are: return one from a Mailable's `attachments()` or pass it to a notification's `->attach()`.
- `->filename()` on a rendered file gives its name with the extension.
- `Doc::fake()` records documents instead of rendering them, with `assertGenerated`, `assertNotGenerated`, `assertGeneratedCount`, `assertNothingGenerated`, `assertSaved`, `assertDownloaded` and `assertStreamed`.

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

[Unreleased]: https://github.com/BiztechEG/laravel-easy-pdf-word/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/BiztechEG/laravel-easy-pdf-word/releases/tag/v1.0.0
