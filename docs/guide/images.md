# Images

Add a logo, a signature or a stamp to your documents, and control where the package may read images from: local folders, image URLs, data URIs and SVG files.

## Where images go {#where}

The bundled templates take images in a few places:

- the company logo in the theme, used by most templates: `->theme(['logo' => ...])`;
- `signature` and `stamp` in the official letter;
- `->image()` blocks and image cells in documents [built in code](/guide/builder);
- `$doc->image()` in your own Blade views (see [Template helpers](/reference/template-helpers));
- `${logo}` style placeholders in a `word.docx` template (see [Word files](/guide/word)).

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('letter', [
    'reference' => 'ص/2026/417',
    'date' => '2026-10-08',
    'recipient' => ['name' => 'المهندس أحمد عبد الرحمن', 'organization' => 'مؤسسة النور للتجارة'],
    'subject' => 'عرض تنفيذ نظام إدارة المستندات',
    'body' => 'يسعدنا أن نقدم لكم عرضنا لتنفيذ نظام إدارة المستندات الإلكترونية.',
    'sender' => ['name' => 'م. خالد حسن', 'title' => 'المدير التنفيذي'],
    'signature' => storage_path('app/signatures/khaled.png'),
    'stamp' => storage_path('app/signatures/stamp.png'),
])
    ->theme(['logo' => public_path('images/logo.png')])
    ->locale('ar')
    ->pdf();
```

Every image goes through the same checks, described below. An image that does not pass them is left out of the document without an error, so a missing image never breaks a document. When an image you expect is missing, see [Troubleshooting](/guide/troubleshooting#images).

## Accepted sources {#sources}

| Source | Example | Used when |
| --- | --- | --- |
| A local file path | `public_path('images/logo.png')` | The file is inside an allowed folder and is a real image. |
| A URL | `https://cdn.biztech.example/logo.png` | Remote images are allowed for its host. Off by default. |
| A data URI | `data:image/png;base64,iVBORw0...` | It is an image (`data:image/...`). An SVG data URI must also pass the [SVG checks](#svg). |

Paths with other schemes (`file://`, `phar://`, `ftp://`, `php://`) and network shares (`\\server\share`, `//server/share`) are never read.

PNG, JPEG, GIF, WebP, BMP and SVG images all work in PDFs, with mPDF and with Chromium. Word files have their own rules; see [Images in Word files](#word).

## Local files and the allowed folders {#allowed-folders}

Local images are read only from these folders:

```php
// config/easy-pdf-word.php
'images' => [
    'paths' => [
        public_path(),
        storage_path('app'),
        resource_path(),
    ],
    // ...
],
```

So a path that arrives in user data, such as `../../.env` or `/etc/passwd`, cannot pull other files from the server into a PDF. A file must also be a real image: the package checks its content, not its name.

Add the folders your images live in, for example a shared uploads folder:

```php
'paths' => [
    public_path(),
    storage_path('app'),
    resource_path(),
    '/mnt/shared/uploads',
],
```

Set `'paths' => null` to allow any folder. Do that only when image paths never come from users.

Files on a cloud disk such as S3 are not local paths. Pass their URL (and allow the host, below), or read them into a data URI:

```php
use Illuminate\Support\Facades\Storage;

$logo = 'data:image/png;base64,'.base64_encode(Storage::disk('s3')->get('tenants/14/logo.png'));

Doc::template('invoice', $data)->theme(['logo' => $logo])->pdf();
```

## Remote images {#remote}

An image URL is downloaded by your server: by the PDF engine, or by the package for Word files. A URL taken from user input could make your server request internal addresses, so image URLs are ignored unless you allow them.

Allow the hosts you use, in `.env`:

```dotenv
DOC_REMOTE_IMAGES=cdn.biztech.example,*.amazonaws.com
```

or in the config, as an array:

```php
'images' => [
    'remote' => ['cdn.biztech.example', '*.amazonaws.com'],
],
```

- Hosts are matched without case. `*.amazonaws.com` matches `bucket.s3.amazonaws.com`, but not `amazonaws.com` itself.
- `DOC_REMOTE_IMAGES=true` allows any URL. Use it only when image URLs never come from users.
- `false` (the default) ignores every URL.

Redirects and slow servers:

- mPDF and Word files do not follow redirects, so a URL on an allowed host cannot lead to another host. A URL that redirects shows mPDF's small "image not found" icon in the PDF, and nothing in a Word file. Use the final URL.
- mPDF and Word files wait up to 10 seconds for an image.
- Chromium and Gotenberg load images as a browser does, and follow redirects. Allow only hosts whose redirects you trust.

## SVG images {#svg}

SVG logos and stamps work in PDFs from every engine, as files or data URIs, when the SVG is self-contained. An SVG that could point outside itself is left out, because the engine would load what it points to. The package refuses an SVG that contains:

- an `href` or `src` that is not a reference to a part of the same SVG (`href="#shape"` is fine, `href="logo.png"` is not);
- `url(...)` that is not such a reference (`url(#gradient)` is fine);
- `<image>`, `<script>`, `<foreignObject>` or `<feImage>` elements, even with embedded data;
- `@import`, `<!DOCTYPE>`, entities or an `<?xml-stylesheet ...?>` line.

Some design tools add a `<!DOCTYPE>` line or embed bitmaps with `<image>`. If your SVG is left out, open it in a text editor and look for these, export it again as plain SVG, or use a PNG.

## Images in Word files {#word}

Word shows JPEG, PNG and GIF images. The package converts WebP and BMP images to PNG for you, with PHP's GD extension.

SVG images are left out of Word files, as PHP cannot draw them. When you make Word files, use a PNG or JPEG logo, signature and stamp:

```php
$document = Doc::template('invoice', $data)
    ->theme(['logo' => public_path('images/logo.png')])   // PNG works in PDF and Word
    ->locale('ar');

$document->pdf()->save('invoices/INV-2026-1024.pdf');
$document->word()->save('invoices/INV-2026-1024.docx');
```

The same allowed folders and hosts apply to Word files.
