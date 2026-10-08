# Quick start

In ten minutes you will have a controller that downloads an Arabic tax invoice, and you will add Arabic digits, your colours and logo, an English version, a Word file, saving to a disk and an email. Each step links to the page that covers it in depth.

## 1. Install {#install}

```bash
composer require biztecheg/laravel-easy-pdf-word mpdf/mpdf phpoffice/phpword
```

This installs the package, the mPDF engine and PhpWord for Word files. [Installation](/guide/installation) covers the other engines and the PHP extensions.

## 2. Download an Arabic invoice {#first-invoice}

Add a route and a controller. The controller passes the invoice data to the bundled `invoice` template and returns the PDF as a download:

```php
// routes/web.php
use App\Http\Controllers\InvoicePdfController;

Route::get('/invoices/{number}/pdf', [InvoicePdfController::class, 'pdf']);
```

```php
// app/Http/Controllers/InvoicePdfController.php
namespace App\Http\Controllers;

use BiztechEG\EasyPdfWord\Facades\Doc;

class InvoicePdfController extends Controller
{
    public function pdf(string $number)
    {
        return Doc::template('invoice', $this->data($number))
            ->locale('ar')
            ->pdf()
            ->download("فاتورة-{$number}.pdf");
    }

    private function data(string $number): array
    {
        return [
            'invoice' => [
                'number' => $number,
                'date' => '2026-10-08',
                'currency' => 'EGP',
                'tax_rate' => 14,
            ],
            'seller' => [
                'name' => 'شركة بيزتك للحلول البرمجية',
                'address' => '15 شارع التحرير، الدقي، الجيزة',
                'tax_number' => '123-456-789',
            ],
            'buyer' => [
                'name' => 'مؤسسة النور للتجارة',
                'address' => 'مدينة نصر، القاهرة',
            ],
            'items' => [
                ['description' => 'تطوير نظام إدارة المخزون', 'quantity' => 1, 'unit_price' => 25000],
                ['description' => 'استضافة سحابية - اشتراك سنوي', 'quantity' => 12, 'unit_price' => 450],
            ],
        ];
    }
}
```

Open `/invoices/INV-2026-1024/pdf` and the browser downloads `فاتورة-INV-2026-1024.pdf`. The template did the rest:

- the page runs right to left with joined Arabic letters and Arabic labels;
- each line total, the subtotal (30,400.00), the 14% VAT (4,256.00) and the total (34,656.00) are calculated;
- the total is written in words: فقط أربعة وثلاثون ألفاً وستمائة وستة وخمسون جنيهاً لا غير;
- the Hijri date is added under the issue date, and the footer shows the invoice number and page numbers.

The data is checked before anything is drawn. Leave out `buyer.name` and you get a `ValidationException` saying "The buyer.name field is required." See [Ready-made templates](/guide/templates#validation). In a real app you build this array from your models; the [invoice template page](/templates/invoice) lists every field.

## 3. Arabic digits {#arabic-digits}

Add `->numerals('arabic')` to print ١٢٣ instead of 123 everywhere in the document, including dates, amounts and page numbers:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->numerals('arabic')
    ->pdf()
    ->download("فاتورة-{$number}.pdf");
```

More in [Arabic support](/guide/arabic).

## 4. Your colours and logo {#theme}

`->theme()` sets the colours and the logo for one document:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->numerals('arabic')
    ->theme([
        'primary' => '#1D4ED8',
        'logo' => public_path('images/logo.png'),
    ])
    ->pdf()
    ->download("فاتورة-{$number}.pdf");
```

<div class="preview">
  <figure><img src="/images/guide-a/quick-start-invoice-ar.png" alt="The invoice from this page with Arabic digits, a blue theme and a logo"><figcaption>The invoice after steps 2 to 4</figcaption></figure>
</div>

To use the same look in every document, put it in `config/easy-pdf-word.php` (publish it with `php artisan vendor:publish --tag=easy-pdf-word-config`). The company details there are printed by the templates, and the invoice uses them as the seller, so you can drop `seller` from the data:

```php
'theme' => [
    'primary' => '#1D4ED8',
    'text' => '#1F2937',
    'muted' => '#6B7280',
    'border' => '#E5E7EB',
    'logo' => public_path('images/logo.png'),
    'company' => [
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
        'phone' => '+20 100 000 0000',
        'email' => 'info@biztech.example',
        'tax_number' => '123-456-789',
    ],
],
```

The logo can be a file under `public/`, `storage/app` or `resources/`, or a data URI. See [Images](/guide/images) for URLs and other folders.

## 5. The English version {#english}

Change the locale. The labels switch to English and the page runs left to right:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('en')
    ->pdf()
    ->download("invoice-{$number}.pdf");
```

Your data is printed as you pass it, so Arabic names stay Arabic. The English invoice leaves out the Hijri date and the amount in Arabic words.

## 6. A Word file {#word}

Replace `->pdf()` with `->word()`. The same template and data make a `.docx` with right-to-left paragraphs and tables:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->word()
    ->download("فاتورة-{$number}.docx");
```

See [Word files](/guide/word).

## 7. Save to a disk {#save}

Instead of sending the file, keep it. `save()` writes to any Laravel filesystem disk and returns the path:

```php
Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->pdf()
    ->save("invoices/{$number}.pdf", disk: 's3');
```

To render and save on a queue worker instead of during the request, use `->queue("invoices/{$number}.pdf", disk: 's3')`. See [Output and delivery](/guide/output).

## 8. Email it {#mail}

A PDF can be returned from a Mailable's `attachments()` as it is. The name you give `->pdf()` becomes the attachment's name:

```php
// app/Mail/InvoiceMail.php
namespace App\Mail;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(public array $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'فاتورة رقم '.$this->invoice['invoice']['number']);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>مرفق فاتورتكم. شكراً لتعاملكم معنا.</p>');
    }

    public function attachments(): array
    {
        $number = $this->invoice['invoice']['number'];

        return [
            Doc::template('invoice', $this->invoice)->locale('ar')->pdf("فاتورة-{$number}.pdf"),
        ];
    }
}
```

```php
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;

Mail::to('accounts@alnoor.example')->send(new InvoiceMail($data));
```

The PDF is rendered when the mail is built, also for queued mail. The [Email an invoice](/recipes/email-invoice) recipe adds a notification and a queued version.

## Where to go next {#next}

- [Ready-made templates](/guide/templates): how data, defaults, labels and the theme work in every template, and the [template gallery](/templates/).
- [Your own templates](/guide/custom-templates): change a bundled template or build a new one.
- [Building in code](/guide/builder): reports and price lists from your data, in PDF and Word.
- [Recipes](/recipes/): complete examples such as a [Saudi ZATCA invoice](/recipes/zatca-invoice) or [course certificates in bulk](/recipes/certificates).
