# Installation

This page installs the package, a PDF engine and Word support, and checks that everything works. It takes a few minutes on any server that runs Laravel.

## Install the package {#install}

```bash
composer require biztecheg/laravel-easy-pdf-word
```

Laravel discovers the service provider and the `Doc` facade by itself. If your app turns package discovery off, add `BiztechEG\EasyPdfWord\EasyPdfWordServiceProvider` to `bootstrap/providers.php`.

The package needs PHP 8.2 or newer and Laravel 12 or 13. The PDF engines and PhpWord are separate packages, so you install only what you use.

## Choose a PDF engine {#engines}

Install at least one engine. mPDF is the default and is enough for most apps; you can add Chromium later and switch per document.

::: code-group

```bash [mPDF (default)]
composer require mpdf/mpdf
```

```bash [Chromium (Browsershot)]
composer require spatie/browsershot
npm install --global puppeteer
```

```bash [Gotenberg]
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

:::

### mPDF {#mpdf}

Pure PHP, with nothing else to install. It shapes Arabic itself and works on shared hosting. Version 8.2 or newer is needed. Nothing to configure: it is the default engine.

### Chromium through Browsershot {#chromium}

The best rendering and full modern CSS, through `spatie/browsershot` 5.4 or newer. The server needs Node.js, Puppeteer and a Chrome or Chromium browser. Puppeteer downloads its own Chrome when you install it; to use a browser that is already on the server, point to it instead. Then make Chromium the default engine in `.env`:

```dotenv
DOC_PDF_DRIVER=chromium

# Optional, when Node, npm or Chrome are not where Browsershot looks for them
DOC_NODE_BINARY=/usr/bin/node
DOC_NPM_BINARY=/usr/bin/npm
DOC_CHROME_PATH=/usr/bin/google-chrome
# The folder printed by `npm root -g`; saves looking it up for every document
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
# Needed when Chrome runs as root, for example in Docker
DOC_CHROME_NO_SANDBOX=true
```

### Gotenberg {#gotenberg}

[Gotenberg](https://gotenberg.dev) runs Chromium in a Docker container and converts HTML over HTTP, so the app server needs neither Node nor Chrome. The package talks to it with Laravel's HTTP client; there is no Composer package to add. Run the container (the command above), then:

```dotenv
DOC_PDF_DRIVER=gotenberg
DOC_GOTENBERG_URL=http://localhost:3000
```

### The fallback engine {#fallback}

When the chosen engine is not installed or fails, the document is rendered with the fallback engine, mPDF by default, and a warning is logged. Keep `mpdf/mpdf` installed if you want that safety net, or turn it off:

```dotenv
DOC_PDF_FALLBACK=null
```

[PDF engines](/guide/engines) compares the engines and lists every engine option.

## Word files {#word}

Word (.docx) files need PhpWord 1.4 or newer:

```bash
composer require phpoffice/phpword
```

Without it, `->word()` stops with "The [word] engine needs the phpoffice/phpword package. Run: composer require phpoffice/phpword". PDFs do not need it. See [Word files](/guide/word).

## PHP extensions {#extensions}

| Extension | Needed | What it is for |
| --- | --- | --- |
| `mbstring` | Always | Arabic and other multi-byte text |
| `gd` | Always | QR codes and images; mPDF needs it too, and Word files use it to turn WebP and BMP images into PNG |
| `intl` | For Hijri dates | Hijri dates, and amounts in words in languages other than Arabic. Without it, templates leave the Hijri date out. |
| `zip` | For ZIP and Word files | `Doc::zip()` archives; PhpWord also needs it to write `.docx` files |

Check what your PHP has with `php -m`. On Ubuntu or Debian, a missing extension is installed with a package such as `php8.3-intl` or `php8.3-zip`.

## Publish the config {#config}

The defaults work without a config file. To change them (default locale, digits, theme colours and company, fonts, engines), publish the config:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

This writes `config/easy-pdf-word.php`. It is the only file the package publishes: templates are copied into your app with `php artisan doc:template` instead (see [Ready-made templates](/guide/templates)). Every key is described in [Configuration](/guide/configuration).

Most settings also have an `.env` variable, so you may not need the file at all:

```dotenv
DOC_PDF_DRIVER=mpdf
DOC_WORD_FONT=Arial
DOC_REMOTE_IMAGES=false
DOC_PREVIEW=false
```

## Check that it works {#check}

List the templates, then render one with its sample data:

```bash
php artisan doc:templates

php artisan doc:sample invoice --output=storage/app/invoice-sample.pdf
php artisan doc:sample invoice --locale=en --output=storage/app/invoice-sample.docx
```

Open `storage/app/invoice-sample.pdf`: you should see an Arabic tax invoice with joined letters, laid out right to left. The second command makes the English invoice as a Word file; the format follows the extension of `--output`. Without `--output`, files go to `storage/app/doc-samples/`. See [Artisan commands](/guide/commands) for every option.

In the `local` environment you can also open `/doc-preview` in the browser to see every template in Arabic and English, with either engine. See [Preview page](/guide/preview).

## Shared hosting {#shared-hosting}

The package works on shared hosting with mPDF, which is pure PHP:

- Keep the default engine: `DOC_PDF_DRIVER=mpdf`. Chromium needs Node and a browser process, which shared hosts rarely allow. A Gotenberg server elsewhere is an option if you have one.
- Check that the host has the `mbstring`, `gd`, `intl` and `zip` extensions; most control panels let you turn them on per PHP version.
- mPDF keeps a font cache in a folder of its own under the system temp folder. If the host does not allow that, the error names the setting to change; point it to a folder in your app in `config/easy-pdf-word.php`:

  ```php
  'pdf' => [
      'drivers' => [
          'mpdf' => [
              'temp_dir' => storage_path('app/mpdf'),
              // ...
          ],
      ],
  ],
  ```

- Large tables use memory: mPDF needs about 85 KB per table row, so a report of a thousand rows needs more than PHP's default 128 MB. See [PDF engines](/guide/engines).

Next: the [Quick start](/guide/quick-start) makes a real invoice from a controller.
