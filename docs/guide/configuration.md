# Configuration

Every setting in `config/easy-pdf-word.php`, what it does and its default, and every `.env` variable the package reads.

The package works without a config file. To change a setting, publish the file first:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

The package merges its own file under yours one level deep: a whole section you leave out (such as `currencies`) keeps its defaults, but a section you keep, such as `pdf`, is used as you wrote it. So keep every key inside the sections you edit, as the published file has them.

## Environment variables {#env}

| Variable | Default | Meaning |
| --- | --- | --- |
| `DOC_PDF_DRIVER` | `mpdf` | The PDF engine: `mpdf`, `chromium` (or `browsershot`), `gotenberg`, or one you added. |
| `DOC_PDF_FALLBACK` | `mpdf` | The engine used when the chosen one is missing or fails. `null` turns the fallback off. |
| `DOC_CHROME_PATH` | empty | Path to Chrome or Chromium for the Chromium engine. Empty uses the Chrome that Puppeteer downloaded. |
| `DOC_NODE_BINARY` | empty | Path to `node`, when it is not on the `PATH` of the web server or worker. |
| `DOC_NPM_BINARY` | empty | Path to `npm`, likewise. |
| `DOC_NODE_MODULES_PATH` | empty | The global `node_modules` folder (`npm root -g`). Saves running that command for every Chromium document. |
| `DOC_CHROME_NO_SANDBOX` | `false` | Start Chrome without its sandbox. Needed when PHP runs as root. |
| `DOC_CHROME_JAVASCRIPT` | `false` | Run JavaScript in Chromium and Gotenberg pages. |
| `DOC_GOTENBERG_URL` | `http://localhost:3000` | Address of the Gotenberg server. |
| `DOC_REMOTE_IMAGES` | `false` | Image URLs: `false` ignores them, `true` allows any, or a comma-separated list of hosts (`cdn.biztech.example,*.amazonaws.com`). |
| `DOC_WORD_FONT` | `Arial` | The font of Word files. |
| `DOC_PREVIEW` | not set | The preview page: not set, `null` or empty (`DOC_PREVIEW=`) means on in `local` only; `true` or `false` turns it on or off everywhere. |
| `APP_NAME` | | Laravel's own variable, used as the default company name in the theme. |

```dotenv
DOC_PDF_DRIVER=chromium
DOC_PDF_FALLBACK=mpdf
DOC_CHROME_PATH=/usr/bin/chromium
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
DOC_REMOTE_IMAGES=cdn.biztech.example
DOC_WORD_FONT=Tahoma
```

After changing `.env` in production, run `php artisan config:cache` again.

## Language and digits {#locale}

```php
'locale' => null,
'numerals' => 'latin',
```

| Key | Default | Meaning |
| --- | --- | --- |
| `locale` | `null` | The language of documents that do not call `->locale()`. `null` uses the app's locale. Right-to-left languages (`ar`, `fa`, `ur`, `he` ...) switch the document to right to left. |
| `numerals` | `'latin'` | `'latin'` prints 0123456789 and `'arabic'` prints ٠١٢٣٤٥٦٧٨٩ in the document text. Change per document with `->numerals()`. |

See [Arabic support](/guide/arabic).

## PDF {#pdf}

```php
'pdf' => [
    'driver' => env('DOC_PDF_DRIVER', 'mpdf'),
    'fallback' => env('DOC_PDF_FALLBACK', 'mpdf'),
    'paper' => 'A4',
    'orientation' => 'portrait',
    'margins' => [15, 15, 15, 15],
    'drivers' => [ /* below */ ],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `pdf.driver` | `'mpdf'` | The default engine. |
| `pdf.fallback` | `'mpdf'` | The engine used when the chosen one fails; `null` turns it off. See [Fallback](/guide/engines#fallback). |
| `pdf.paper` | `'A4'` | Paper name (`A2` to `A6`, `B4`, `B5`, `Letter`, `Legal`, `Tabloid`, `Executive`), with `-L` for landscape (`A4-L`), or `[width, height]` in mm. |
| `pdf.orientation` | `'portrait'` | `'portrait'` or `'landscape'`. |
| `pdf.margins` | `[15, 15, 15, 15]` | Millimetres: top, right, bottom, left. Shorter forms work as in CSS: `[15]`, `[20, 15]`, `[25, 15, 20]`. |

A template's `template.php` and calls on the document come before these; see [Page settings](/guide/page-settings#defaults).

### mPDF {#mpdf}

```php
'mpdf' => [
    'temp_dir' => null,
    'use_kashida' => 75,
    'auto_lang_to_font' => false,
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `temp_dir` | `null` | Folder for mPDF's font cache and work files, kept in a `fonts-<hash>` subfolder per version of the registered font files. `null` uses a private folder per system user in the system temp folder (`/tmp/easy-pdf-word-{uid}`). Set a folder that the web server and workers can write to if `/tmp` is not usable. |
| `use_kashida` | `75` | How much of the stretching in justified Arabic text uses kashida (ـ) instead of wider spaces, 0 to 100. |
| `auto_lang_to_font` | `false` | Pick a font per script, for documents that mix Arabic with scripts the document font lacks (Chinese, Hindi ...). Ignores `font-family` in your CSS. |

### Chromium {#browsershot}

```php
'browsershot' => [
    'node_binary' => env('DOC_NODE_BINARY'),
    'npm_binary' => env('DOC_NPM_BINARY'),
    'node_modules_path' => env('DOC_NODE_MODULES_PATH'),
    'chrome_path' => env('DOC_CHROME_PATH'),
    'no_sandbox' => env('DOC_CHROME_NO_SANDBOX', false),
    'javascript' => env('DOC_CHROME_JAVASCRIPT', false),
    'timeout' => 60,
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `node_binary` | `null` | Path to `node`. |
| `npm_binary` | `null` | Path to `npm`. |
| `node_modules_path` | `null` | The global `node_modules` folder; found with `npm root -g` on every render when empty. |
| `chrome_path` | `null` | Path to Chrome or Chromium. |
| `no_sandbox` | `false` | Start Chrome with `--no-sandbox`. |
| `javascript` | `false` | Run JavaScript in the page. |
| `timeout` | `60` | Seconds before a render is given up. |

See [PDF engines](/guide/engines#chromium).

### Gotenberg {#gotenberg}

```php
'gotenberg' => [
    'url' => env('DOC_GOTENBERG_URL', 'http://localhost:3000'),
    'javascript' => env('DOC_CHROME_JAVASCRIPT', false),
    'timeout' => 60,
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `url` | `'http://localhost:3000'` | Where Gotenberg listens. |
| `javascript` | `false` | Run JavaScript in the page. Shares `DOC_CHROME_JAVASCRIPT` with Chromium. |
| `timeout` | `60` | Seconds to wait for Gotenberg. |

## Fonts {#fonts}

```php
'fonts' => [
    'default' => 'cairo',
    'default_ltr' => 'cairo',
    'custom' => [],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `fonts.default` | `'cairo'` | The font of right-to-left documents. |
| `fonts.default_ltr` | `'cairo'` | The font of other documents. |
| `fonts.custom` | `[]` | Your own fonts, by name: `'almarai' => ['regular' => ..., 'bold' => ...]`. Each takes `regular` (required), `bold`, `italic`, `bold_italic`, `arabic_separators` and `arabic`. |

The bundled fonts are `cairo`, `tajawal` and `naskh`. See [Fonts](/guide/fonts).

## Images {#images}

```php
'images' => [
    'paths' => [
        public_path(),
        storage_path('app'),
        resource_path(),
    ],
    'remote' => env('DOC_REMOTE_IMAGES', false),
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `images.paths` | `public`, `storage/app` and `resources` | The only folders local images are read from. `null` allows any folder. |
| `images.remote` | `false` | Image URLs: `false`, `true`, or the hosts to allow as an array (`['cdn.biztech.example', '*.amazonaws.com']`) or a comma-separated string. |

See [Images](/guide/images).

## Word {#word}

```php
'word' => [
    'font' => env('DOC_WORD_FONT', 'Arial'),
    'font_size' => 11,
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `word.font` | `'Arial'` | The font of Word files. Word does not embed fonts, so use one your readers have and that covers Arabic. |
| `word.font_size` | `11` | The text size in points. Headings are larger. |

See [Word files](/guide/word) and [Fonts](/guide/fonts#word).

## Preview page {#preview}

```php
'preview' => [
    'enabled' => env('DOC_PREVIEW'),
    'path' => 'doc-preview',
    'middleware' => ['web'],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `preview.enabled` | `null` | `null`: on in `local` only. `true` or `false`: on or off everywhere. Outside `local` the page also needs the `viewDocPreview` gate. |
| `preview.path` | `'doc-preview'` | The page's URL. |
| `preview.middleware` | `['web']` | Middleware for its routes, such as `['web', 'auth']`. |

See [Preview page](/guide/preview).

## Templates {#templates}

```php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
    ],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `templates.paths` | `resources/doc-templates` | Folders searched for templates, in order, before the bundled ones. The first match wins, so a project template replaces a bundled one with the same name. `doc:template` and `doc:make-template` write to the first folder. |

See [Your own templates](/guide/custom-templates).

## Theme {#theme}

```php
'theme' => [
    'primary' => '#0F766E',
    'text' => '#1F2937',
    'muted' => '#6B7280',
    'border' => '#E5E7EB',
    'logo' => null,
    'company' => [
        'name' => env('APP_NAME'),
        'address' => null,
        'phone' => null,
        'email' => null,
        'tax_number' => null,
    ],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `theme.primary` | `'#0F766E'` | The main colour: headings, table headers, totals. |
| `theme.text` | `'#1F2937'` | The text colour. |
| `theme.muted` | `'#6B7280'` | Secondary text, such as labels and footers. |
| `theme.border` | `'#E5E7EB'` | Lines and table borders. |
| `theme.logo` | `null` | The company logo: a path, URL or data URI, following the [image rules](/guide/images). |
| `theme.company.*` | `APP_NAME`, then `null` | Your company's name, address, phone, e-mail and tax number. Templates print them, and the invoice uses them as the seller when you pass none. |

Colours must be real CSS colours; anything else falls back to the default. Override the theme per document with `->theme([...])`; see [Ready-made templates](/guide/templates).

## Currencies {#currencies}

```php
'currencies' => [],
```

Currencies to add to, or replace in, the amount in words (tafqeet). Each one lists the Arabic forms of its main and fractional units, their gender, and how many fractional units make one main unit. See [Arabic support](/guide/arabic#currencies).
