# Laravel Easy PDF & Word

Generate PDF documents from Laravel in any language, with first-class Arabic support and ready-made templates. Word (.docx) output is coming in the next phase.

- Arabic that renders correctly: joined letters, right-to-left layout, mixed Arabic and English, Arabic or Latin digits.
- Two PDF engines you can switch between: **mPDF** (pure PHP, works on shared hosting) and **Chromium** (via Browsershot or Gotenberg), with automatic fallback.
- Ready-made templates: tax invoice (with ZATCA QR), official letter, table report. Use them as they are, copy and customise them, or build your own.
- Arabic helpers: amounts in words (تفقيط), Hijri dates, Arabic numerals.
- Bundled Arabic fonts: Cairo, Tajawal and Noto Naskh Arabic.

[العربية](#بالعربي)

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- ext-mbstring, ext-gd; ext-intl for Hijri dates

## Installation

```bash
composer require biztecheg/laravel-easy-pdf-word

# Pick at least one PDF engine
composer require mpdf/mpdf              # default, pure PHP
composer require spatie/browsershot     # optional, Chromium
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
| `->pdf()->content()` | PDF bytes, e.g. for mail attachments |
| `->toHtml()` | The final HTML, for debugging |

Returning `->pdf()` from a controller streams it.

### Page settings

```php
Doc::template('report', $data)
    ->paper('A4')->landscape()
    ->margins(15, 12)                                  // mm: top/bottom, right/left
    ->footer('<div style="text-align:center">{page} / {pages}</div>')
    ->pdf();
```

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
| `letter` | Official letter: letterhead, reference number, Gregorian and Hijri date, recipient, subject, body, signature, stamp, copies |
| `report` | Table report: any rows, chosen columns, totals row, summary cards, header repeated on every page |

Each template's `template.php` lists its fields and contains sample data.

```bash
php artisan doc:templates                              # list templates
php artisan doc:template invoice --as=my-invoice       # copy to resources/doc-templates to customise
php artisan doc:template invoice                       # copy under the same name: overrides the original
php artisan doc:make-template delivery-note            # start a new template from scratch
```

A template is a folder:

```
resources/doc-templates/my-invoice/
  template.php        title, fields (validation rules), defaults, prepare(), sample data
  pdf.blade.php       the layout
  footer.blade.php    optional, may use {page} and {pages}
  header.blade.php    optional
  lang/ar.php         labels, read with $doc->t('key')
  lang/en.php
```

Data passed to a template is validated against its `fields` rules before rendering.

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

## Fonts

Cairo (default), Tajawal and Noto Naskh Arabic are bundled under the SIL Open Font License. Use one with `->font('tajawal')` or `font-family: 'naskh'` in CSS.

Register your own in the config:

```php
'fonts' => [
    'custom' => [
        'my-font' => [
            'regular' => resource_path('fonts/MyFont-Regular.ttf'),
            'bold' => resource_path('fonts/MyFont-Bold.ttf'),
        ],
    ],
],
```

mPDF cannot read some recent fonts and stops with "MarkGlyphSets - Not tested yet" or "GPOS Lookup Type 5, Format 3 not supported". Fix the font files once with the included script (needs `pip install fonttools`):

```bash
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/MyFont-*.ttf
```

The bundled fonts are already fixed this way.

## Testing

```bash
composer test
```

The Chromium tests run when `DOC_CHROME_PATH` points to a Chrome binary and Puppeteer is installed.

## License

MIT. mPDF, an optional dependency, is GPL-2.0; check that it fits your project, or use the Chromium engine instead.

---

## بالعربي

مكتبة Laravel لإنشاء ملفات PDF بأي لغة، مع دعم كامل للعربي وقوالب جاهزة. دعم ملفات Word جاي في المرحلة الجاية.

- العربي بيطلع صح: الحروف متشبكة، الاتجاه من اليمين للشمال، والنص المختلط عربي وإنجليزي، وأرقام عربية أو لاتينية.
- محركين PDF تقدر تبدل بينهم: **mPDF** (PHP بس، شغال على الاستضافة المشتركة) و **Chromium** (عن طريق Browsershot أو Gotenberg)، ولو المحرك المختار مش موجود بيرجع للتاني تلقائياً.
- قوالب جاهزة: فاتورة ضريبية (مع QR هيئة الزكاة)، خطاب رسمي، تقرير جدولي. تستخدمها زي ما هي، أو تنسخها وتعدلها، أو تعمل قالبك.
- أدوات عربية: التفقيط، التاريخ الهجري، الأرقام العربية.
- خطوط عربية مدمجة: Cairo و Tajawal و Noto Naskh Arabic.

### التثبيت

```bash
composer require biztecheg/laravel-easy-pdf-word
composer require mpdf/mpdf
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
```

### الأدوات العربية

```php
Arabic::tafqeet(1250.5, 'EGP');   // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::hijri('2026-10-08');      // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::numerals('2026');         // ٢٠٢٦
```

باقي التفاصيل في الجزء الإنجليزي فوق.
