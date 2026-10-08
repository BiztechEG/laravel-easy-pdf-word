# Laravel Easy PDF & Word

[![Tests](https://github.com/BiztechEG/laravel-easy-pdf-word/actions/workflows/tests.yml/badge.svg)](https://github.com/BiztechEG/laravel-easy-pdf-word/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/biztecheg/laravel-easy-pdf-word)](https://packagist.org/packages/biztecheg/laravel-easy-pdf-word)
[![PHP](https://img.shields.io/packagist/php-v/biztecheg/laravel-easy-pdf-word)](https://packagist.org/packages/biztecheg/laravel-easy-pdf-word)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Generate PDF and Word (.docx) documents from Laravel in any language, with first-class Arabic support and ready-made templates.

- Arabic that renders correctly: joined letters, right-to-left layout, mixed Arabic and English, Arabic or Latin digits.
- Two PDF engines you can switch between: **mPDF** (pure PHP, works on shared hosting) and **Chromium** (via Browsershot or Gotenberg), with automatic fallback.
- Word files with real right-to-left paragraphs and tables, from the same templates, from code, or from a .docx you design in Word.
- Ready-made templates, each in PDF and Word: tax invoice (with ZATCA QR), Egyptian e-invoice (ETA), price quotation, receipt/payment voucher, official letter, table report. Use them as they are, copy and customise them, or build your own.
- A preview page that shows every template in any language, digits and engine.
- Arabic helpers: amounts in words (تفقيط), Hijri dates, Arabic numerals.
- Bundled Arabic fonts: Cairo, Tajawal and Noto Naskh Arabic.

[العربية](#بالعربي)

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- ext-mbstring, ext-gd; ext-intl for Hijri dates

## Installation

```bash
composer require biztecheg/laravel-easy-pdf-word

# Pick at least one PDF engine
composer require mpdf/mpdf              # default, pure PHP
composer require spatie/browsershot     # optional, Chromium

# For Word files
composer require phpoffice/phpword
```

Publish the config if you want to change the defaults:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

## Usage

### A ready-made template

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::template('invoice', [
        'invoice' => ['number' => 'INV-1024', 'date' => now(), 'currency' => 'EGP', 'tax_rate' => 14],
        'seller'  => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
        'buyer'   => ['name' => 'مؤسسة النور'],
        'items'   => [
            ['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000],
        ],
    ])
    ->locale('ar')               // RTL, Arabic font, Arabic labels
    ->numerals('arabic')         // ١٢٣ instead of 123
    ->theme(['primary' => '#1D4ED8', 'logo' => public_path('logo.png')])
    ->pdf()
    ->download('فاتورة-1024.pdf');
```

### Your own Blade view

```php
Doc::view('pdf.contract', ['contract' => $contract])
    ->locale('ar')
    ->pdf()
    ->save('contracts/'.$contract->id.'.pdf', disk: 's3');
```

In the view, wrap the content in the layout component; `$doc` carries direction, fonts, theme and helpers:

```blade
<x-doc::layout :doc="$doc">
    <h1 style="color: {{ $doc->theme('primary') }}">عقد خدمات</h1>
    <p>المبلغ: {{ $doc->number($contract->amount) }} ({{ $doc->tafqeet($contract->amount, 'EGP') }})</p>
    <p>الهاتف: {{ $doc->ltr($contract->phone) }}</p>
</x-doc::layout>
```

### Plain HTML

```php
Doc::html('<h1>مرحبا</h1>')->locale('ar')->pdf()->stream();
```

### Output

| Method | Result |
| --- | --- |
| `->pdf()->download('file.pdf')` | Download response |
| `->pdf()->stream()` | Show in the browser |
| `->pdf()->save('path.pdf', disk: 's3')` | Save to a disk (or an absolute path without a disk) |
| `->pdf()->content()` | PDF bytes |
| `->word()->download('file.docx')` | The same for Word: `download`, `stream`, `save`, `content` |
| `->toHtml()` | The final HTML, for debugging |

Returning `->pdf()` from a controller streams it.

### Mail attachments

A PDF or Word file can be attached to a mail as it is. The name given to `->pdf()` or `->word()` becomes the attachment's name:

```php
// In a Mailable
public function attachments(): array
{
    return [
        Doc::template('invoice', $this->data)->locale('ar')->pdf('فاتورة-1024.pdf'),
    ];
}

// In a notification
return (new MailMessage)
    ->line('مرفق إيصال الدفع.')
    ->attach(Doc::template('receipt', $data)->pdf('receipt.pdf'));
```

The file is rendered when the mail is built. For a queued mail, make the file inside `attachments()` or `toMail()` as above, not in the constructor: a file waiting to be rendered cannot be serialized onto the queue.

### ZIP files

`Doc::zip()` puts several files in one archive, which has the same `download`, `stream`, `save` and `content` methods and can be attached to mail:

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([
    $invoice->pdf('فاتورة-1024.pdf'),
    $invoice->word('فاتورة-1024.docx'),
    'receipt.pdf' => Doc::template('receipt', $receipt)->pdf(),
], 'order-1024.zip')->download();
```

Each file keeps its own name unless a key gives another. A name used twice becomes `name (2).pdf`. ZIP files need the PHP `zip` extension.

### Queued saving

A worker can render and save the file instead of the request:

```php
Doc::template('invoice', $data)->locale('ar')->queue('invoices/1024.pdf', disk: 's3');

Doc::make()->heading('تقرير المبيعات')->table($rows)->locale('ar')
    ->queue('reports/sales.docx', disk: 's3')
    ->onQueue('documents')
    ->chain([new SendReportReady($user)]);
```

The extension picks the format: `.pdf` or `.docx`. `->queue()` returns Laravel's pending dispatch, so `->onQueue()`, `->onConnection()`, `->delay()` and `->chain()` work. Template data is validated before anything is queued, so a mistake shows up in the request, not as a failed job.

The job, `BiztechEG\EasyPdfWord\Jobs\SaveDocument`, carries the document's settings and data, and Laravel encrypts it with the app key. Template data is stored as plain arrays; data for your own Blade views is serialized as it is, so it cannot hold closures. A worker on another server saves to its own local disk, so use a shared disk such as `s3` there.

In tests, the `sync` queue runs the job at once and `Doc::fake()` records the save, so `Doc::assertSaved('invoices/1024.pdf', disk: 's3')` works. Under `Queue::fake()`, check the job instead:

```php
Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->path === 'invoices/1024.pdf' && $job->disk === 's3');
```

### Page settings

```php
Doc::template('report', $data)
    ->paper('A4')->landscape()
    ->margins(15, 12)                                  // mm: top/bottom, right/left
    ->footer('<div style="text-align:center">{page} / {pages}</div>')
    ->pdf();
```

### Watermark and password

```php
Doc::template('quotation', $data)
    ->watermark('مسودة')                                  // across every page; opacity: 0.12, color: '#000000'
    ->password('1234')                                     // asked for when the PDF is opened
    ->pdf();

// Opens freely, but readers may only print it.
Doc::template('receipt', $data)->password('', owner: 'admin-secret', allow: ['print'])->pdf();
```

Both are for PDF files. Word files are made without the watermark, and `->word()` refuses a document with a password so it never goes out unprotected. `allow` takes any of `print`, `print-highres`, `copy`, `modify`, `annot-forms`, `fill-forms`, `extract`, `assemble`; without an owner password a random one is used, so the limits hold.

mPDF encrypts with 128-bit RC4, the strongest it offers: enough to keep a file from being opened by chance, not to protect secrets. Chromium cannot encrypt, so its PDF is copied into an encrypted file by mPDF (`mpdf/mpdf` must be installed). The pages stay text, but links inside them stop working.

## Word files

Every bundled template also makes a Word file from the same data:

```php
Doc::template('invoice', $data)->locale('ar')->word()->download('فاتورة-1024.docx');
```

Arabic documents get right-to-left paragraphs, Arabic text marked as Arabic (so Word uses the right font and size), and tables laid out from the right. The table header repeats on every page, and `{page}` / `{pages}` in the footer become Word page numbers.

### Build a document in code

`Doc::make()` describes a document block by block. The same document renders to Word and to PDF:

```php
$report = Doc::make()
    ->heading('تقرير المبيعات')
    ->paragraph([['text' => 'الفترة: ', 'bold' => true], 'سبتمبر 2026'])
    ->table([
        ['الفرع', 'الطلبات', 'الإيرادات'],
        ['القاهرة', '1,240', '486,500.75'],
        ['الجيزة', '980', '371,200.00'],
    ], ['header' => true, 'columns' => [50, 20, ['width' => 30, 'align' => 'end']]])
    ->qr('https://example.com/reports/9')
    ->locale('ar');

$report->word()->download('sales.docx');
$report->pdf()->download('sales.pdf');
```

| Block | Example |
| --- | --- |
| `heading($text, $level = 1)` | `->heading('عقد خدمات', 2)` |
| `paragraph($text or runs, $style)` | `->paragraph('نص', ['align' => 'justify'])` |
| `table($rows, $options)` | options: `header`, `columns`, `striped`, `footer`, `borders`, `font_size` |
| `image($path, $widthMm, $align)` | `->image(public_path('logo.png'), 40, 'end')` |
| `qr($value, $sizeMm)` | `->qr($url, 30)` |
| `spacer($mm)`, `line()`, `pageBreak()` | |

Styles: `bold`, `italic`, `size` (pt), `color`, `align` (`start`, `end`, `center`, `justify`), `background` (cells), `border` (a box around a cell), and `ltr` to keep a phone number or code in order inside Arabic text. A cell is a string, or an array with `text`, `lines`, `image` or `qr`, plus `colspan`.

### Word layout in a template

A template folder can describe its layout in three ways:

- `layout.php` returns `fn (DocumentBuilder $doc, array $data, DocContext $context)` and adds blocks. One layout makes both the PDF and the Word file; the quotation, receipt and Egyptian e-invoice templates work this way.
- `word.php` has the same shape and is used for Word only, next to a `pdf.blade.php` for the PDF, like the invoice, letter and report templates.
- `word.docx` is a file you design in Word with placeholders. It wins over `word.php` and `layout.php` for Word.

Placeholders in `word.docx`:

| Placeholder | Value |
| --- | --- |
| `${invoice.number}`, `${buyer.name}` | Data, nested with dots |
| `${items.description}` in a table row | The row repeats for every item; `${items.row_number}` numbers them |
| `${logo}` or `${logo:120:60}` | An image path or data URI, optionally with a size in pixels |
| `${t.title}` | A label from `lang/{locale}.php` |
| `${theme.company.name}` | Theme values |
| `${doc.hijri_date}`, `${doc.qr}`, `${doc.today}` | Hijri date of `date`, a QR image of `qr`, today's date |

Values are escaped, and digits follow `->numerals()`. Set right-to-left direction for the paragraphs in Word itself.

Word does not embed fonts, so Word files use a font your readers already have. The default is Arial; change it with `DOC_WORD_FONT` (for example `Tahoma` or `Sakkal Majalla`).

## Switching PDF engines

| Engine | Name | Needs | Best for |
| --- | --- | --- | --- |
| mPDF | `mpdf` | `mpdf/mpdf` | Shared hosting, no extra software. CSS 2.1: tables and floats, no flexbox or grid. |
| Chromium | `chromium` (or `browsershot`) | `spatie/browsershot`, Node, Puppeteer, Chrome | Best quality, full modern CSS |
| Gotenberg | `gotenberg` | A [Gotenberg](https://gotenberg.dev) container | Chromium quality without Node on the app server |

Default for the whole app, in `.env`:

```dotenv
DOC_PDF_DRIVER=mpdf
DOC_PDF_FALLBACK=mpdf
```

Per document:

```php
Doc::template('invoice', $data)->driver('chromium')->pdf();
```

If the chosen engine is not installed or fails, the document is rendered with the fallback engine and a warning is logged. `->pdf()->engine()` tells you which engine was used. Set `DOC_PDF_FALLBACK=null` to turn this off.

The bundled templates use CSS that both engines understand, so they look the same on either.

Your own engine:

```php
Doc::extend('my-engine', fn ($app) => new MyPdfDriver);   // implements Contracts\PdfDriver
```

## Templates

| Name | Contents |
| --- | --- |
| `invoice` | Tax invoice: seller, buyer, items, discount, VAT, total, amount in words, Hijri date, QR (ZATCA or any link) |
| `eg-invoice` | Egyptian e-invoice, credit or debit note (ETA): tax registration numbers, branch and activity codes, EGS/GS1 item codes, units, ETA tax types per line (T1 VAT, T2/T3 table tax, T4 withholding ...), tax summary, portal QR |
| `quotation` | Price quotation: customer, items with details and units, discount, optional VAT, validity date, terms, signature |
| `receipt` | Receipt or payment voucher (سند قبض / سند صرف): amount in figures and words, payer or payee, purpose, cash/cheque/transfer/card, signatures |
| `letter` | Official letter: letterhead, reference number, Gregorian and Hijri date, recipient, subject, body, signature, stamp, copies |
| `report` | Table report: any rows, chosen columns, totals row, summary cards, header repeated on every page |

Each template's `template.php` lists its fields and contains sample data.

```bash
php artisan doc:templates                              # list templates
php artisan doc:template invoice --as=my-invoice       # copy to resources/doc-templates to customise
php artisan doc:template invoice                       # copy under the same name: overrides the original
php artisan doc:make-template delivery-note            # start a new template from scratch
php artisan doc:sample eg-invoice --locale=ar --format=docx   # render the sample data to a file
```

### Preview page

In the `local` environment, `/doc-preview` lists every template and shows it with its sample data. You can switch the language, the digits and the PDF engine, open the PDF or download the Word file. Turn it on elsewhere with `DOC_PREVIEW=true`, or off with `DOC_PREVIEW=false`. Outside `local` the page also needs the `viewDocPreview` gate, so only the people you choose can open it:

```php
// app/Providers/AppServiceProvider.php
Gate::define('viewDocPreview', fn ($user) => $user->isAdmin());
```

When working on the package itself, `composer preview` serves the page at http://127.0.0.1:8000/doc-preview.

A template is a folder:

```
resources/doc-templates/my-invoice/
  template.php        title, fields (validation rules), defaults, prepare(), sample data
  pdf.blade.php       the PDF layout in Blade
  layout.php          or one code layout for PDF and Word
  word.php            optional Word layout (or word.docx designed in Word)
  footer.blade.php    optional, may use {page} and {pages}
  header.blade.php    optional
  lang/ar.php         labels, read with $doc->t('key')
  lang/en.php
```

Data passed to a template is validated against its `fields` rules before rendering.

### Egyptian e-invoice (ETA)

The `eg-invoice` template prints a document submitted to the Egyptian Tax Authority. Its fields follow the ETA document, so you can print the data you submit:

```php
Doc::template('eg-invoice', [
    'document' => ['type' => 'I', 'internal_id' => 'INV-1024', 'issued_at' => now(), 'uuid' => $uuid, 'long_id' => $longId],
    'issuer'   => ['name' => 'شركة بيزتك', 'rin' => '123456789', 'branch_id' => '0', 'activity_code' => '6201'],
    'receiver' => ['type' => 'B', 'id' => '987654321', 'name' => 'مؤسسة النور'],
    'lines'    => [[
        'description' => 'تطوير نظام', 'item_type' => 'EGS', 'item_code' => 'EG-123456789-1001', 'unit' => 'EA',
        'quantity' => 1, 'unit_price' => 25000,
        'taxes' => [['type' => 'T1', 'rate' => 14], ['type' => 'T4', 'subtype' => 'W010', 'rate' => 3]],
    ]],
])->locale('ar')->pdf();
```

VAT (T1) is charged on the net amount plus the other taxes, withholding (T4) is deducted, and fixed taxes take an `amount` instead of a `rate`. With `uuid` and `long_id`, the QR opens the document on the ETA portal.

### Saudi e-invoice QR (ZATCA)

```php
Doc::template('invoice', [...$data, 'qr' => 'zatca'])->pdf();
```

builds the phase 1 QR (seller name, VAT number, time, total, VAT) from the invoice data. Any other `qr` value, such as an Egyptian e-invoice link, is encoded as is. The QR class can be used on its own:

```php
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

ZatcaQr::make('شركة المثال', '300000000000003', now(), 1150, 150)->toBase64();
```

## Arabic helpers

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::tafqeet(1250.5, 'EGP');          // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::tafqeet(3.03, 'SAR', only: true); // فقط ثلاثة ريالات وثلاث هللات لا غير
Arabic::tafqeet(123456);                 // مائة وثلاثة وعشرون ألفاً وأربعمائة وستة وخمسون
Arabic::hijri('2026-10-08');             // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::numerals('2026');                // ٢٠٢٦
```

Also available as global helpers `tafqeet()`, `hijri_date()`, `arabic_numerals()` and Blade directives `@tafqeet(1250.5, 'EGP')`, `@hijri($date)`.

Currencies included: EGP, SAR, AED, QAR, KWD, USD, EUR. Add more in `config/easy-pdf-word.php` under `currencies`.

## Images

Logos, signatures and stamps can be file paths, URLs or data URIs. Local files are read only from `public/`, `storage/app` and `resources/` by default, and only when they are real images, so a path that comes from user input cannot embed other files from the server. Change the folders in `images.paths`.

Image URLs are downloaded by your server (the PDF engine or PhpWord), so they are ignored unless you allow them. Allow the hosts you use, or any URL when image URLs never come from users:

```env
DOC_REMOTE_IMAGES=cdn.example.com,*.amazonaws.com
# or: DOC_REMOTE_IMAGES=true
```

SVG images are used only when they are self-contained: an SVG that links to other files or URLs is ignored, because the PDF engine would load them.

## Security

The package treats the data you pass to a template as untrusted:

- Text is escaped in Blade templates, `Doc::make()` blocks and Word files. `${...}` in a value stays text in `word.docx` templates.
- Images follow the rules in [Images](#images); colours must be real colours (`#0F766E`, `rgb(...)`, `red`), so they cannot add CSS.
- `->locale()` and `->font()` accept plain names only (`ar`, `ar_EG`, `cairo`).
- Chromium renders with JavaScript off (`DOC_CHROME_JAVASCRIPT=true` turns it on).
- The preview page is local only unless you enable it and define the `viewDocPreview` gate.

HTML you write yourself is trusted as is: never pass user input to `Doc::html()` or print it with `{!! !!}` in a view.

Found a vulnerability? Please report it privately, as described in [SECURITY.md](SECURITY.md).

## Fonts

Cairo (default), Tajawal and Noto Naskh Arabic are bundled under the SIL Open Font License. Use one with `->font('tajawal')` or `font-family: 'naskh'` in CSS.

Register your own in the config:

```php
'fonts' => [
    'custom' => [
        'my-font' => [
            'regular' => resource_path('fonts/MyFont-Regular.ttf'),
            'bold' => resource_path('fonts/MyFont-Bold.ttf'),
            // Optional: the font draws ٫ and ٬ clearly, so Arabic digits use them (١٢٬٥٠٠٫٧٥).
            'arabic_separators' => true,
        ],
    ],
],
```

With Arabic digits, Naskh uses the Arabic decimal and thousands separators (١٢٬٥٠٠٫٧٥). Cairo and Tajawal keep `,` and `.` (Cairo draws both Arabic separators like commas, and Tajawal has none), and so do Word files.

mPDF cannot read some recent fonts and stops with "MarkGlyphSets - Not tested yet" or "GPOS Lookup Type 5, Format 3 not supported". Fix the font files once with the included script (needs `pip install fonttools`):

```bash
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/MyFont-*.ttf
```

The bundled fonts are already fixed this way.

## Testing your app

`Doc::fake()` records documents instead of rendering them, like `Mail::fake()`. Templates still get their defaults, `prepare()` and validation, but no PDF engine runs and nothing is saved, so tests stay fast:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;

Doc::fake();

$this->post('/orders/1024/invoice')->assertOk();

Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'invoice'
    && $doc->locale === 'ar'
    && $doc->data('invoice.number') === 'INV-1024'
    && $doc->contains('مؤسسة النور'));
Doc::assertSaved('invoices/INV-1024.pdf', disk: 's3');
Doc::assertDownloaded('فاتورة-1024.pdf');
```

| Assertion | Passes when |
| --- | --- |
| `assertGenerated(?fn)` | A file was made (and matches the callback) |
| `assertNotGenerated(fn)`, `assertNothingGenerated()`, `assertGeneratedCount(n)` | The opposite, or an exact count |
| `assertSaved($path or fn, ?disk)` | A file was saved there |
| `assertDownloaded($name or fn)` | A file was sent as a download |
| `assertStreamed($name or fn)` | A file was shown in the browser, or returned from a controller |

Each `GeneratedDocument` has `format` (`pdf` or `word`), `template`, `view`, `locale`, `direction`, `numerals`, `driver`, `watermark`, `protected`, `filename()`, `data($key)` with the prepared data, and for PDFs `html()` and `contains($text)`. With Arabic digits, `contains()` needs the text in Arabic digits too. `Doc::generated()` returns them all.

## Running the package's tests

```bash
composer test
```

The Chromium tests run when `DOC_CHROME_PATH` points to a Chrome binary and Puppeteer is installed.

## License

MIT. mPDF, an optional dependency, is GPL-2.0; check that it fits your project, or use the Chromium engine instead.

---

## بالعربي

مكتبة Laravel لإنشاء ملفات PDF و Word بأي لغة، مع دعم كامل للعربي وقوالب جاهزة.

- العربي بيطلع صح: الحروف متشبكة، الاتجاه من اليمين للشمال، والنص المختلط عربي وإنجليزي، وأرقام عربية أو لاتينية.
- محركين PDF تقدر تبدل بينهم: **mPDF** (PHP بس، شغال على الاستضافة المشتركة) و **Chromium** (عن طريق Browsershot أو Gotenberg)، ولو المحرك المختار مش موجود بيرجع للتاني تلقائياً.
- ملفات Word بفقرات وجداول من اليمين للشمال، من نفس القوالب، أو من الكود، أو من ملف docx تصممه في Word.
- قوالب جاهزة، وكل قالب بيطلع PDF و Word: فاتورة ضريبية (مع QR هيئة الزكاة)، فاتورة إلكترونية مصرية، عرض سعر، سند قبض وسند صرف، خطاب رسمي، تقرير جدولي. تستخدمها زي ما هي، أو تنسخها وتعدلها، أو تعمل قالبك.
- صفحة معاينة بتعرض كل القوالب بأي لغة وأي محرك.
- أدوات عربية: التفقيط، التاريخ الهجري، الأرقام العربية.
- خطوط عربية مدمجة: Cairo و Tajawal و Noto Naskh Arabic.

### التثبيت

```bash
composer require biztecheg/laravel-easy-pdf-word
composer require mpdf/mpdf
composer require phpoffice/phpword   # لملفات Word
```

### مثال سريع

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::template('invoice', $data)
    ->locale('ar')
    ->numerals('arabic')
    ->pdf()
    ->download('فاتورة.pdf');
```

### ملفات Word

```php
// نفس القالب بصيغة Word
Doc::template('invoice', $data)->locale('ar')->word()->download('فاتورة.docx');

// مستند من الكود يطلع Word و PDF
$doc = Doc::make()
    ->heading('تقرير المبيعات')
    ->table([['الفرع', 'المبيعات'], ['القاهرة', '1,000']], ['header' => true])
    ->locale('ar');

$doc->word()->download('تقرير.docx');
$doc->pdf()->download('تقرير.pdf');
```

وتقدر تحط في فولدر القالب ملف `word.docx` تصممه في Word وتكتب فيه `${invoice.number}` و `${items.description}` جوه صف جدول، والمكتبة تملاه.

### التبديل بين المحركات

من `.env` للمشروع كله:

```dotenv
DOC_PDF_DRIVER=mpdf
```

أو لكل ملف لوحده:

```php
Doc::template('invoice', $data)->driver('chromium')->pdf();
```

### القوالب

```bash
php artisan doc:templates                          # عرض القوالب
php artisan doc:template invoice --as=my-invoice   # نسخ قالب جاهز لتعديله
php artisan doc:make-template delivery-note        # قالب جديد من الصفر
php artisan doc:sample eg-invoice --format=docx    # ملف تجريبي من بيانات القالب
```

وفي بيئة `local` افتح `/doc-preview` عشان تشوف كل القوالب ببياناتها التجريبية، وتبدّل بين العربي والإنجليزي والأرقام والمحرك، وتنزّل PDF أو Word. ولو شغّلتها برّه `local` بـ `DOC_PREVIEW=true` لازم تعرّف صلاحية `viewDocPreview` بـ `Gate::define` عشان محدش غير اللي تختاره يفتحها.

### الأدوات العربية

```php
Arabic::tafqeet(1250.5, 'EGP');   // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::hijri('2026-10-08');      // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::numerals('2026');         // ٢٠٢٦
```

### إرفاق الملف في إيميل

ملف الـ PDF أو Word يتحط زي ما هو في `attachments()` بتاعة الـ Mailable أو في `->attach()` بتاعة الإشعار، والاسم اللي بتديه لـ `->pdf()` هو اسم المرفق:

```php
public function attachments(): array
{
    return [Doc::template('invoice', $this->data)->locale('ar')->pdf('فاتورة-1024.pdf')];
}
```

### ملفات ZIP

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([$invoice->pdf('فاتورة-1024.pdf'), $invoice->word('فاتورة-1024.docx')], 'طلب-1024.zip')->download();
```

ملف الـ ZIP ليه نفس `download` و `save` و `content`، وينفع يترفق في إيميل. محتاج إضافة `zip` في PHP.

### التوليد في الـ queue

```php
Doc::template('invoice', $data)->locale('ar')->queue('invoices/1024.pdf', disk: 's3')->onQueue('documents');
```

الملف بيتولّد ويتحفظ في الـ worker بدل الـ request. الامتداد هو اللي بيحدد النوع (`.pdf` أو `.docx`). بيانات القالب بتتراجع قبل ما الـ job يتبعت، فالغلط بيظهر في الـ request نفسه، والـ job بيتشفّر بالـ APP_KEY. لو الـ worker على سيرفر تاني استخدم disk مشترك زي `s3`.

### الاختبارات في مشروعك

`Doc::fake()` بيسجّل الملفات بدل ما يولّدها، زي `Mail::fake()`. القالب بيتعمله validation عادي، لكن مفيش محرك PDF بيشتغل ولا ملف بيتحفظ:

```php
Doc::fake();

$this->post('/orders/1024/invoice');

Doc::assertGenerated(fn ($doc) => $doc->template === 'invoice' && $doc->data('invoice.number') === 'INV-1024');
Doc::assertSaved('invoices/INV-1024.pdf', disk: 's3');
```

باقي الـ assertions في الجزء الإنجليزي فوق.

### علامة مائية وكلمة سر

```php
Doc::template('quotation', $data)->watermark('مسودة')->password('1234')->pdf();
```

الاتنين لملفات الـ PDF بس. ملف Word بيطلع من غير العلامة المائية، و `->word()` بيرفض مستند عليه كلمة سر عشان ميطلعش مفتوح. التشفير RC4 بطول 128 بت، وده كفاية يمنع فتح الملف بالصدفة لكنه مش حماية لأسرار مهمة.

### الأمان

البيانات اللي بتبعتها للقالب بتتعامل كأنها من المستخدم: النصوص بتتعمل لها escape، والصور بتتقري من الفولدرات المسموحة بس، وروابط الصور مقفولة إلا لو سمحت بيها في `DOC_REMOTE_IMAGES`، والألوان لازم تكون ألوان حقيقية. أما الـ HTML اللي بتكتبه بنفسك فبيتعامل كأنه موثوق، فمتبعتش أي حاجة من المستخدم لـ `Doc::html()` ولا تطبعها بـ `{!! !!}`.

لو لقيت ثغرة، بلّغ عنها بشكل خاص زي ما هو مكتوب في [SECURITY.md](SECURITY.md)، مش في issue عام.

باقي التفاصيل في الجزء الإنجليزي فوق.
