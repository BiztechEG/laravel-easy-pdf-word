# Security

What the package protects you from by default, what it leaves to you, and how to report a vulnerability.

## Data is untrusted {#untrusted-data}

The data you pass to a template, a view or `Doc::make()` often comes from users: customer names, addresses, notes, file names. The package treats it as untrusted.

### Text is escaped {#escaping}

- **Blade templates.** The bundled templates print values with Blade's escaped echo (double curly braces), so `<script>` in a customer name prints as text. Do the same in your own views.
- **Documents built in code.** Text in `Doc::make()` blocks (headings, paragraphs, table cells) is escaped.
- **Word files.** Text is escaped for Word's XML. In a `word.docx` template, a value that contains `${...}` stays as text and is not filled as another placeholder.
- **Headers and footers in Word files.** Only their text is used; tags, styles and scripts are dropped.

### Images {#images}

Images are the main way a document could reach outside itself, so they follow strict rules (see [Images](/guide/images)):

- Local files are read only from the folders in `images.paths` (`public`, `storage/app` and `resources` by default), and only when they are real images. A path like `../../.env` in user data embeds nothing.
- Other schemes (`file://`, `phar://`, `php://`, `ftp://`) and network shares are never read.
- Image URLs are ignored unless you allow their hosts with `DOC_REMOTE_IMAGES`, so user data cannot make your server request internal addresses such as `http://169.254.169.254/`.
- Allowed URLs are fetched without following redirects and with a 10-second limit, for every engine and for Word files. For Chromium and Gotenberg the package fetches the image itself and inlines it, so the browser never requests a URL.
- SVG images that refer to files, URLs, scripts or entities are left out.

### Colours, locales and fonts {#colours-locales-fonts}

- **Colours** in the theme, builder styles and the watermark must be real colours: `#0F766E`, `rgb(15, 118, 110)`, `hsl(...)` or a name such as `teal`. A value like `red; background: url(...)` is replaced by the default colour, so it cannot add CSS or requests.
- **Locales** must be locale names (`ar`, `ar_EG`, `zh-Hant-TW`). The locale picks a template's `lang` file, so a value like `../../config/app` throws instead of reading another file.
- **Font names** must be plain names (`cairo`, `my-font`). Anything else throws before it reaches CSS.
- **ZIP entry names** never contain folders: `../../etc/cron.d/x` becomes `-..-etc-cron.d-x.pdf`, so an archive cannot unpack outside its folder.
- **Numbers from data**, such as a report column's `decimals`, are capped, so they cannot build huge strings.

### Chromium runs without JavaScript {#chromium}

Chromium (Browsershot) and Gotenberg render with JavaScript off. HTML that slipped into the data cannot run scripts that request other pages or files, or read the page. Turn JavaScript on with `DOC_CHROME_JAVASCRIPT=true` only for documents that need it, such as charts drawn with a script library, and only when their data is safe.

### The preview page is closed {#preview}

The [preview page](/guide/preview) is on in the `local` environment only. Anywhere else you must turn it on with `DOC_PREVIEW=true`, and it then needs the `viewDocPreview` gate: without the gate, everyone gets a 403. The page only renders the templates' sample data, sends a strict Content Security Policy, and shows the HTML view in a sandbox without scripts.

### Queued documents are encrypted {#queue}

A document saved with `->queue()` travels through your queue as an encrypted job (`ShouldBeEncrypted`), since it carries the document's data and any PDF password. See [Output and delivery](/guide/output#queue-encryption).

## What stays trusted {#trusted}

Some input is code you write, and the package uses it as it is:

- **HTML you pass to `Doc::html()`.** It is rendered as written. Never build it from user input.
- **Your own Blade views.** The double-curly-brace echo escapes, `{!! !!}` does not (see the example below). Never print user input with `{!! !!}`.
- **Templates.** `template.php`, `layout.php` and `word.php` are PHP files that run in your app. Copy or install templates only from sources you trust.
- **Config.** Paths, fonts, hosts and engine settings in `config/easy-pdf-word.php` and `.env`.

```blade
{{-- Safe: escaped --}}
<p>{{ $order->notes }}</p>

{{-- Unsafe with user input: printed as HTML --}}
<p>{!! $order->notes !!}</p>
```

Raw HTML skips every check on this page: an `<img>` in it is loaded by the engine from any local path or URL the engine can reach, and with Chromium JavaScript on, a `<script>` in it runs. Keep `{!! !!}` and `Doc::html()` for HTML your own code builds.

## Settings that open things on purpose {#settings}

The defaults keep everything above closed. These settings open part of it, so turn them on only when you need them:

| Setting | Opens |
| --- | --- |
| `DOC_REMOTE_IMAGES=true` | Image URLs to any host, including internal addresses |
| `images.paths` set to `null` | Local images from any folder the PHP user can read |
| `DOC_CHROME_JAVASCRIPT=true` | Scripts in Chromium and Gotenberg pages |
| `DOC_PREVIEW=true` | The preview page outside `local`, behind the `viewDocPreview` gate |
| `DOC_CHROME_NO_SANDBOX=true` | Chrome without its own sandbox, which is needed when PHP runs as root |

## Passwords are not strong encryption {#passwords}

`->password()` encrypts PDFs with 128-bit RC4, the strongest mPDF offers. That keeps a payslip or a quotation from being opened by chance, by someone who finds the file. It is not strong against a determined attacker, so do not rely on it for secrets: protect such files with access control in your app, and send them through secure channels.

A random owner password is used when you give none, so readers cannot lift the limits you set with `allow`. Word files cannot be encrypted, so `->word()` refuses a document with a password rather than make an open copy. See [Page settings](/guide/page-settings#password).

## Report a vulnerability {#reporting}

Please do not open a public issue for a security problem. Report it privately from the repository's **Security** tab with **Report a vulnerability**, with the steps or the data that trigger it. The full policy, including what counts as a vulnerability, is in [SECURITY.md](https://github.com/BiztechEG/laravel-easy-pdf-word/blob/main/SECURITY.md).

Once a fix is released, the advisory is published with credit to you, unless you would rather stay anonymous.
