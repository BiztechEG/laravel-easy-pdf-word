# Troubleshooting

Common problems, what causes them and how to fix them. The messages quoted are the ones the package, its engines and Laravel print.

## Arabic letters are boxes, disconnected or in another font {#arabic-letters}

**Boxes (□□□) instead of letters.** The font in use has no Arabic letters. This happens when:

- you registered a font without Arabic letters and made it the document font (`->font()` or `fonts.default`);
- with Chromium or Gotenberg, your own CSS names a font that is not registered with the package, and the server has no font with Arabic letters;
- the document has letters from another script, such as Chinese or Hindi, that the document font does not have.

Use one of the bundled fonts or a font that covers Arabic. For other scripts with mPDF, turn on `auto_lang_to_font` (see [Fonts](/guide/fonts#auto-lang-to-font)). For a second font with Chromium, register it (see [Fonts](/guide/fonts#custom-fonts)); a registered font named in your CSS is embedded for you.

**Letters drawn one by one, not joined.** The font has the Arabic letters but not the shaping rules that join them (its GSUB table). Some old fonts, and fonts converted with some tools, lack them. Use a modern TrueType font, such as the bundled ones.

**Arabic in another font than you chose.** With mPDF, a `font-family` in your CSS that is not a registered font name (`Arial`, `Tahoma`, `Roboto`) is replaced by DejaVu Sans, and so is a name passed to `->font()` that you did not register. Use the registered names: `cairo`, `tajawal`, `naskh` or your own. Old mPDF core font names (`chelvetica`, `ctimes`, `ccourier`) fail with `You cannot use core fonts in a document which contains RTL text.`

**The whole document is left to right.** The document has no right-to-left locale. Call `->locale('ar')`, or set the app locale or `locale` in the config. See [Arabic support](/guide/arabic#locale).

## Numbers or codes in the wrong order {#mixed-order}

Inside Arabic text, a phone number `+20 100 000 0000` shows as `0000 000 100 20+`, a tax number `123-456-789` as `789-456-123`, or a date `2026-10-08` as `08-10-2026`.

This is how the Unicode bidirectional rules treat digits after Arabic letters: groups separated by spaces or dashes are laid out right to left. Numbers like `1,250.00`, dates with slashes (`2026/10/08`) and codes that start with Latin letters (`INV-2026-1024`) keep their order.

Wrap such values with `$doc->ltr()` in a Blade view, or give them the `ltr` style in `Doc::make()`:

```blade
<p>الهاتف: {{ $doc->ltr('+20 100 000 0000') }}</p>
```

```php
Doc::make()->paragraph(['الرقم الضريبي: ', ['text' => '123-456-789', 'ltr' => true]])->locale('ar');
```

See [Mixed Arabic and English](/guide/arabic#mixed-text).

## mPDF stops with a font error {#mpdf-font-errors}

```text
Font "almarai" contains MarkGlyphSets which is not supported
This font [almarai] contains MarkGlyphSets - Not tested yet
GPOS Lookup Type 5, Format 3 not supported (ttfontsuni.php).
```

The font uses features that mPDF cannot read, which is common in recent fonts and Google Fonts. Fix the font files once with the script that comes with the package:

```bash
python3 -m pip install fonttools
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/Almarai-*.ttf
```

Other font errors:

| Message | Fix |
| --- | --- |
| `Fonts with postscript outlines are not supported` | Use the `.ttf` (TrueType) version of the font, not `.otf`. |
| `The font files [...] and [...] have the same name. mPDF finds fonts by file name, so rename one of them.` | Give each font file a unique name. |
| `Font [name] needs at least a "regular" file.` | Add `regular` to the font in `fonts.custom`. |

See [Fonts](/guide/fonts#mpdf-font-fix).

## Memory exhausted on big tables {#memory}

```text
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in vendor/mpdf/mpdf/src/Mpdf.php
```

mPDF keeps a whole table in memory, about 90 KB per row, so a report of 1,000 to 1,500 rows passes PHP's default limit of 128 MB. A fatal error cannot fall back to another engine.

- Raise the limit where the report is made: `ini_set('memory_limit', '512M')`, ideally in a queued job.
- Or render long reports with Chromium or Gotenberg, which use little PHP memory: `->driver('chromium')`.
- Or split the report, for example one PDF per month or branch.

See [PDF engines](/guide/engines#large-documents).

## Fallback warnings in the log {#fallback-warning}

```text
production.WARNING: easy-pdf-word: [browsershot] failed, falling back to [mpdf]: ...
production.WARNING: easy-pdf-word: [gotenberg] failed, falling back to [mpdf]: cURL error 7: Failed to connect to localhost port 3000 ...
```

The engine you chose failed or is not installed, so mPDF made the file instead. The text after the colon is the engine's error; the sections below cover the usual ones. Until it is fixed, your users get PDFs from mPDF, which may look different for your own views.

To see the error directly instead of a fallback, set `DOC_PDF_FALLBACK=null` while you fix it, or check `->pdf()->engine()`. See [Fallback](/guide/engines#fallback).

## Chromium or Puppeteer not found {#chromium}

| Message | Cause and fix |
| --- | --- |
| `The [browsershot] engine needs the spatie/browsershot package. Run: composer require spatie/browsershot` | Browsershot is not installed. |
| `sh: 1: node: not found` (exit code 127) | The web server cannot find Node. Set `DOC_NODE_BINARY` (and `DOC_NPM_BINARY`) to their full paths; find them with `which node`. |
| `Error: Cannot find module 'puppeteer'` | Puppeteer is not installed where Node looks. Run `npm install -g puppeteer` and set `DOC_NODE_MODULES_PATH` to the output of `npm root -g`, or run `npm install puppeteer` in the app's root folder. |
| `Error: Could not find Chrome (ver. ...)` or `Could not find chrome-headless-shell (ver. ...)` | Puppeteer's Chrome is missing, or was installed for another user (it lives in that user's `~/.cache/puppeteer`). Install Chrome or Chromium for the whole system and set `DOC_CHROME_PATH`, or run `npx puppeteer browsers install chrome-headless-shell` as the user PHP runs as. |
| `Browser was not found at the configured executablePath (/usr/bin/google-chrome)` | `DOC_CHROME_PATH` points to a file that does not exist. Check the path with `which chromium` or `which google-chrome`. |
| `Running as root without --no-sandbox is not supported` | PHP runs as root, as in many Docker images. Set `DOC_CHROME_NO_SANDBOX=true`, or run PHP as another user. |
| A timeout error after 60 seconds | The page took longer than `pdf.drivers.browsershot.timeout`, often because of slow remote images. Raise the timeout, or use local images. |

The full message also contains the whole Browsershot command; the useful part is under `Error Output`. With the fallback on, you find it in the log warning.

## Gotenberg not reachable {#gotenberg}

| Message | Cause and fix |
| --- | --- |
| `cURL error 7: Failed to connect to localhost port 3000` | Gotenberg is not running, or not at that address. Check `DOC_GOTENBERG_URL`; inside Docker Compose use the service name, such as `http://gotenberg:3000`. |
| `cURL error 28: Operation timed out` | Gotenberg did not answer within `pdf.drivers.gotenberg.timeout` (60 seconds). |
| `Gotenberg returned HTTP 404: ...` | The URL points to something else, or has an extra path. Use the server's base address only. |
| `Gotenberg did not return a PDF; check DOC_GOTENBERG_URL. It returned: ...` | Something answered with a web page, such as a proxy's sign-in page. The message ends with the start of that page's text. |
| `The [gotenberg] engine needs the URL of a Gotenberg server in DOC_GOTENBERG_URL.` | The URL is empty. |

## Images are missing {#images}

Images that do not pass the [image rules](/guide/images) are left out without an error. Check these in order:

1. **A local file outside the allowed folders.** Only `public`, `storage/app` and `resources` are allowed by default. Add your folder to `images.paths`.
2. **A file that is not an image,** for example an HTML error page saved as `logo.png`. The package checks the content, not the name.
3. **A path on a cloud disk** like `tenants/14/logo.png` on S3. It is not a local path: pass the file's URL or a data URI.
4. **A URL whose host is not allowed.** Add the host to `DOC_REMOTE_IMAGES`, then run `php artisan config:cache` again in production.
5. **A URL that redirects.** Redirects are not followed: mPDF shows a small "image not found" icon instead, and Chromium, Gotenberg and Word files leave the image out. Use the final URL.
6. **An SVG that refers to files or URLs**, or has a `<!DOCTYPE>` line. Export it again as plain SVG, or use a PNG.
7. **An SVG in a Word file.** Word files leave SVG out. Use a PNG or JPEG.

To check one image, put it in a small document and look for it in the HTML the engine gets:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

str_contains(Doc::make()->image($path)->toHtml(), '<img');   // false: the image is refused
```

## Word files look wrong {#word}

**Text runs left to right.** The document has no right-to-left locale: call `->locale('ar')`. For a `word.docx` template you designed in Word, set the paragraphs and tables to right to left in Word itself, as the package only fills in the values.

**The wrong font.** Word files do not embed fonts. Word shows the font from `DOC_WORD_FONT` (Arial by default) when the reader has it, and another font when not. Choose a font your readers have that covers Arabic, such as Arial, Tahoma or Sakkal Majalla, and run `php artisan config:cache` again. See [Fonts](/guide/fonts#word).

**Thousands and decimal separators.** With Arabic digits, Word files keep `,` and `.` (١٢,٥٠٠.٧٥), because the reader's font may not draw the Arabic separators.

**No watermark.** Word files are made without the watermark; it is for PDFs only.

**Errors when making the file:**

| Message | Fix |
| --- | --- |
| `The [word] engine needs the phpoffice/phpword package. Run: composer require phpoffice/phpword` | Install PhpWord. |
| `Template [name] has no Word layout. Add layout.php, word.php or word.docx to its folder.` | The template makes PDFs only. See [Word files](/guide/word). |
| `Word files are made from a template with word.php or word.docx, or from Doc::make(). Blade views and HTML only make PDFs.` | Views and HTML cannot become Word files. Build the document with [`Doc::make()`](/guide/builder). |
| `Word files cannot take a password; ->password() works for PDF files only.` | Remove `->password()` for the Word file, or send a PDF. |

## Hijri dates fail {#hijri}

```text
Hijri dates need the PHP intl extension.
```

Install and enable the `intl` extension for the PHP that runs your app (for example `sudo apt install php8.3-intl`, then restart PHP-FPM), and check with `php -m | grep intl`. The bundled templates leave the Hijri date out while `intl` is missing, so they still render; only your own calls to `Arabic::hijri()`, `hijri_date()` or `@hijri` throw.

## ZIP or Word files fail: ZipArchive missing {#zip}

```text
ZIP files need the PHP zip extension (ext-zip).
Class "ZipArchive" not found
```

The first comes from `Doc::zip()`, the second from Word files, since a `.docx` is a ZIP archive too. Install and enable the `zip` extension (`sudo apt install php8.3-zip`). Composer checks for it when you install PhpWord, so when it works on the command line but not on the website, the web server's PHP (PHP-FPM) is missing the extension: check its `phpinfo()`.

## Template data is refused {#validation}

```text
The buyer.name field is required. (and 1 more error)
```

Data passed to a template is validated against the rules in its `template.php` (`fields`) before anything is rendered, so a mistake throws an `Illuminate\Validation\ValidationException`. In a controller, Laravel turns it into a redirect back with errors, or a 422 JSON answer. Catch it to see every message:

```php
use Illuminate\Validation\ValidationException;

try {
    $pdf = Doc::template('invoice', $data)->locale('ar')->pdf();
} catch (ValidationException $e) {
    logger()->error('Invoice data', $e->errors());   // ['buyer.name' => ['The buyer.name field is required.'], ...]
    throw $e;
}
```

The fields each template takes are listed on its page under [Templates](/templates/). Some templates check more than the rules: the invoice refuses `The discount cannot be more than the line amount (quantity × unit price).`

Other template errors:

- `Template [invoce] was not found in: ...` lists the folders searched. Check the name with `php artisan doc:templates`.
- `View [pdf.contract] not found.` comes from `Doc::view()` with a view that does not exist.

## Queued documents {#queue}

**The job is too large.** The job carries the document's data, so a report with thousands of rows can pass the queue's size limit: 1 MB on Amazon SQS, 64 KB by default on Beanstalkd (`JOB_TOO_BIG`). Queue a job of your own that loads the data on the worker; see [Output and delivery](/guide/output#queue-size).

**The job times out.**

```text
BiztechEG\EasyPdfWord\Jobs\SaveDocument has timed out.
BiztechEG\EasyPdfWord\Jobs\SaveDocument has been attempted too many times.
```

The render took longer than the worker's `--timeout` (60 seconds by default). Give the documents queue more time, such as `--timeout=180`, keep `retry_after` above it, and fix a failing engine so the fallback does not add a second render. See [Worker timeouts](/guide/output#queue-timeouts).

**`The MAC is invalid.`** The job is encrypted with the `APP_KEY` of the app that queued it. Give the workers the same `APP_KEY`.

**The job finished but there is no file.** A worker on another server saved the file to its own local disk. Use a shared disk such as `s3` for queued documents.

**A queued mail fails with `Failed to serialize job of type [Illuminate\Mail\SendQueuedMailable]: Serialization of 'Closure' is not allowed`.** A PDF was passed to the Mailable's constructor. Make the file inside `attachments()` instead; see [Queued mail](/guide/output#queued-mail).

## Other errors {#other}

| Message | Fix |
| --- | --- |
| `The [mpdf] engine needs the mpdf/mpdf package. Run: composer require mpdf/mpdf` | Install mPDF, or choose another engine with `DOC_PDF_DRIVER`. |
| `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().` | A misspelt engine name in `->driver()`, `DOC_PDF_DRIVER` or `doc:sample --driver`. Fix the spelling; there is no fallback for a name that is not an engine. |
| `PDF passwords need the mpdf/mpdf package, also with Chromium. Run: composer require mpdf/mpdf` | `->password()` with Chromium or Gotenberg needs mPDF to encrypt the file. |
| `Cannot create the mPDF temp folder [...]` or `The mPDF temp folder [...] is not writable.` | Set a folder the web server and workers can write to in `pdf.drivers.mpdf.temp_dir`. |
| `Could not write [invoices/INV-2026-1024.pdf] to the [s3] disk.` | Check the disk's settings and permissions. See [Output and delivery](/guide/output#save-failures). |
| `Unknown paper size [Foolscap].` | Use a listed size, or `[width, height]` in mm. See [Page settings](/guide/page-settings#paper). |
| `Unknown currency [GBP]. Register it with Tafqeet::registerCurrency().` | Add the currency under `currencies` in the config. See [Arabic support](/guide/arabic#currencies). |
