# PDF engines

The package can make PDFs with three engines. This page compares them, shows how to set each one up, how to pick one for the app or for a single document, what happens when an engine fails, and how to add your own.

## Compare the engines {#compare}

| | mPDF | Chromium | Gotenberg |
| --- | --- | --- | --- |
| Name in code | `mpdf` | `chromium` (or `browsershot`) | `gotenberg` |
| Install | `mpdf/mpdf` | `spatie/browsershot`, Node.js, Puppeteer and Chrome on the server | A [Gotenberg](https://gotenberg.dev) container the app can reach over HTTP |
| Runs | Inside PHP | A headless Chrome started for each document | Chrome in another container |
| Shared hosting | Yes | Rarely | No |
| CSS | About CSS 2.1: tables, floats, inline styles. No flexbox or grid | Everything Chrome supports | Everything Chrome supports |
| Arabic | Shaped by mPDF | Shaped by Chrome (HarfBuzz) | Shaped by Chrome |
| `{page}` / `{pages}` digits | Follow `->numerals()` | Always Latin | Always Latin |
| Watermark | Yes | Yes | Yes |
| Password | Yes | Yes, added by mPDF afterwards | Yes, added by mPDF afterwards |
| Large tables | About 90 KB of PHP memory per row | Low PHP memory | Low PHP memory |
| Licence | GPL-2.0 | MIT (Browsershot), Apache-2.0 (Puppeteer) | MIT |

The bundled templates use CSS that every engine understands, so they look the same on all three. mPDF is the default because it needs nothing but PHP. Choose Chromium or Gotenberg when your own views use modern CSS, or for long reports.

## mPDF {#mpdf}

```bash
composer require mpdf/mpdf
```

That is all. mPDF is pure PHP and works on shared hosting.

- mPDF is licensed under GPL-2.0. Check that this fits your project, or use Chromium or Gotenberg instead.
- Its CSS support is roughly CSS 2.1. Lay pages out with tables and floats, not flexbox or grid. Your own views and HTML are covered in [Blade views and HTML](/guide/views-and-html).
- mPDF keeps its font cache and work files in a private folder per system user (`/tmp/easy-pdf-word-{uid}`), so the web server and a queue worker running as different users do not lock each other out. Set another folder in `pdf.drivers.mpdf.temp_dir`.
- `use_kashida` (default `75`) is how much of the stretching in justified Arabic text is done with kashida (ـ) rather than wider spaces, from 0 to 100.
- `auto_lang_to_font` (default `false`) lets mPDF pick a font per script, for documents that mix Arabic with Chinese, Hindi or other scripts the document font does not have. It ignores `font-family` in your CSS, so leave it off otherwise.

### Large documents {#large-documents}

mPDF keeps a whole table in memory while it lays it out: about 90 KB per row. A report of 1,000 rows comes close to PHP's default `memory_limit` of 128 MB, and 1,500 rows go over it. Running out of memory ends the request with a fatal error, which no fallback can catch:

```text
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in vendor/mpdf/mpdf/src/Mpdf.php
```

For reports beyond a few hundred rows, either raise the limit for the code that renders them, or use Chromium, which rendered 2,000 rows with about 50 MB of PHP memory in our tests:

```php
ini_set('memory_limit', '512M');

Doc::template('report', $data)->locale('ar')->pdf()->save('reports/2026-09.pdf', 's3');

// or
Doc::template('report', $data)->locale('ar')->driver('chromium')->pdf()->save('reports/2026-09.pdf', 's3');
```

A queued job is a good place for both; see [Output and delivery](/guide/output#queue-size).

## Chromium {#chromium}

Chromium makes PDFs with a real browser through [Browsershot](https://github.com/spatie/browsershot) and Puppeteer.

```bash
composer require spatie/browsershot
npm install -g puppeteer
```

Installing Puppeteer also downloads a Chrome build for it. You can install Puppeteer in the project instead (`npm install puppeteer` in the app's root folder), and point the package at a Chrome or Chromium you installed yourself with `DOC_CHROME_PATH`.

Then make it the default engine, or use it per document (see below):

```dotenv
DOC_PDF_DRIVER=chromium
```

Settings, under `pdf.drivers.browsershot` in the config:

| Key | `.env` | Default | Meaning |
| --- | --- | --- | --- |
| `chrome_path` | `DOC_CHROME_PATH` | empty | Path to Chrome or Chromium, for example `/usr/bin/chromium`. Empty uses the Chrome that Puppeteer downloaded. |
| `node_binary` | `DOC_NODE_BINARY` | empty | Path to `node` when it is not on the web server's `PATH` (common with nvm). |
| `npm_binary` | `DOC_NPM_BINARY` | empty | Path to `npm`, likewise. |
| `node_modules_path` | `DOC_NODE_MODULES_PATH` | empty | The global `node_modules` folder, the output of `npm root -g`. When empty, Browsershot runs `npm root -g` for every document, which costs time. |
| `no_sandbox` | `DOC_CHROME_NO_SANDBOX` | `false` | Start Chrome without its sandbox. Needed when PHP runs as root, as in many Docker images. |
| `javascript` | `DOC_CHROME_JAVASCRIPT` | `false` | Run JavaScript in the page. The templates need none; turn it on only for documents that draw with it, such as charts. |
| `timeout` | | `60` | Seconds before a render is given up. |

A typical server setup:

```dotenv
DOC_PDF_DRIVER=chromium
DOC_CHROME_PATH=/usr/bin/chromium
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
```

Puppeteer downloads its Chrome into the home folder of the user who ran `npm install`. The web server and queue workers usually run as another user (such as `www-data`), which cannot find it. Setting `DOC_CHROME_PATH` to a Chrome installed for the whole system avoids that. The errors you may see are listed in [Troubleshooting](/guide/troubleshooting#chromium).

JavaScript stays off unless you turn it on, so HTML that slipped into the document's data cannot make the browser request other pages. See [Security](/guide/security).

## Gotenberg {#gotenberg}

[Gotenberg](https://gotenberg.dev) runs Chrome in its own container and makes PDFs over HTTP. You get Chromium's output without Node or Chrome on the app server.

```bash
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

```dotenv
DOC_PDF_DRIVER=gotenberg
DOC_GOTENBERG_URL=http://localhost:3000
```

With Docker Compose, use the service name: `DOC_GOTENBERG_URL=http://gotenberg:3000`.

Settings, under `pdf.drivers.gotenberg`:

| Key | `.env` | Default | Meaning |
| --- | --- | --- | --- |
| `url` | `DOC_GOTENBERG_URL` | `http://localhost:3000` | Where Gotenberg listens. |
| `javascript` | `DOC_CHROME_JAVASCRIPT` | `false` | Run JavaScript in the page, as for Chromium. |
| `timeout` | | `60` | Seconds to wait for Gotenberg's answer. |

The package sends the page to Gotenberg's `/forms/chromium/convert/html` route. An answer that is not a PDF (a sign-in page from a proxy, say) is treated as a failure: `Gotenberg did not return a PDF; check DOC_GOTENBERG_URL. It returned: ...`, followed by the start of the page's text.

## Choose the default engine {#default}

The engine for the whole app comes from `.env`:

```dotenv
DOC_PDF_DRIVER=mpdf        # mpdf, chromium or gotenberg
DOC_PDF_FALLBACK=mpdf      # used when the chosen engine fails; null turns it off
```

Engine names are not case sensitive, and `chrome` and `browsershot` are other names for `chromium`.

## Choose an engine per document {#per-document}

`->driver()` picks the engine for one document:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('report', $data)->locale('ar')->driver('chromium')->pdf();
```

## Fallback {#fallback}

When the chosen engine is not installed, or throws while rendering, the document is rendered again with the fallback engine (`DOC_PDF_FALLBACK`, `mpdf` by default), and a warning is written to the log:

```text
[2026-10-08 22:33:02] production.WARNING: easy-pdf-word: [browsershot] failed, falling back to [mpdf]: The command "PATH=$PATH:/usr/local/bin:/opt/homebrew/bin NODE_PATH=`npm root -g` "node" ...
[2026-10-08 22:33:02] production.WARNING: easy-pdf-word: [gotenberg] failed, falling back to [mpdf]: cURL error 7: Failed to connect to 127.0.0.1 port 3000 ...
```

`->engine()` on the file tells you which engine made it. Chromium is reported as `browsershot`:

```php
$pdf = Doc::template('invoice', $data)->driver('chromium')->pdf();

if ($pdf->engine() !== 'browsershot') {
    // Chromium failed and mPDF made the file; the log says why.
}
```

What to know:

- Errors in the document itself, such as a Blade error or data that fails validation, are thrown as they are. Only engine failures are retried.
- An engine name that does not exist, such as a typo like `chromuim`, is not retried: it throws `InvalidArgumentException` with `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().`
- When the fallback engine is the same as the chosen one, or is not installed either, you get the original error, not a message about the fallback.
- Turn the fallback off with `DOC_PDF_FALLBACK=null`, so that an engine problem throws instead of producing a PDF from another engine.
- A fallback render happens in the same request or job, so it adds its time to the first attempt. Keep that in mind for [queue worker timeouts](/guide/output#queue-timeouts).

## Your own engine {#custom-engine}

An engine is a class that implements `BiztechEG\EasyPdfWord\Contracts\PdfDriver`:

```php
interface PdfDriver
{
    /** Turn a full HTML document into PDF bytes. */
    public function render(string $html, PdfOptions $options): string;

    /** Whether the engine's package or service is installed, so the manager can fall back before trying. */
    public function isAvailable(): bool;

    /** Whether the engine loads fonts from CSS @font-face (Chromium) rather than its own font setup (mPDF). */
    public function usesCssFonts(): bool;
}
```

This one sends the page to an internal PDF service:

```php
namespace App\Pdf;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdfServiceDriver implements PdfDriver
{
    public function __construct(private ?string $url) {}

    public function render(string $html, PdfOptions $options): string
    {
        [$width, $height] = $options->paperSize();
        [$top, $right, $bottom, $left] = $options->margins;

        $response = Http::timeout(60)->post($this->url.'/render', [
            'html'    => Watermark::inject($html, $options),
            'width'   => $width,
            'height'  => $height,
            'margins' => compact('top', 'right', 'bottom', 'left'),
            // This service is Chrome based, so page numbers use Chrome's classes.
            'footer'  => str_replace(
                ['{page}', '{pages}'],
                ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
                (string) $options->footer,
            ),
        ]);

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF-')) {
            throw new RuntimeException("The PDF service returned HTTP {$response->status()}.");
        }

        return $response->body();
    }

    public function isAvailable(): bool
    {
        return ! empty($this->url);
    }

    public function usesCssFonts(): bool
    {
        return true;
    }
}
```

Register it in a service provider, then use it by name:

```php
// app/Providers/AppServiceProvider.php
use App\Pdf\PdfServiceDriver;
use BiztechEG\EasyPdfWord\Facades\Doc;

public function boot(): void
{
    Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver(config('services.pdf.url')));
}
```

```php
Doc::template('invoice', $data)->driver('pdf-service')->pdf();
```

or `DOC_PDF_DRIVER=pdf-service` for the whole app. What your engine gets and must do:

- `$html` is the full document. When `usesCssFonts()` returns `true`, the package embeds the document font, and every registered font the page's CSS names, as `@font-face` rules, so a browser-based engine needs no fonts installed. It also downloads allowed remote images and puts them in the page as data URIs, so your engine never fetches a URL.
- `$options` carries `paperSize()` in millimetres with the orientation applied, `margins` (top, right, bottom, left in mm), `direction`, `locale`, `font`, `numerals`, `title`, `author`, `header` and `footer`. The header and footer still contain `{page}` and `{pages}`; replace them with your engine's page number syntax.
- `$options->watermark` is set when the document has a watermark. `Watermark::inject($html, $options)` adds it to the HTML as a fixed element, as the Chromium engines do.
- Passwords are handled for you: after your engine returns, the package encrypts the PDF with mPDF.
- Throw an exception when rendering fails. The fallback engine then takes over, as for the built-in engines.
- Names are not case sensitive: `Doc::extend('PdfService', ...)` is found as `pdfservice`.
