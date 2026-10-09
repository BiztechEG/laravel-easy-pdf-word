# Output and delivery

This page covers what you can do with a document once it is ready: download it, show it in the browser, save it to a disk, attach it to an email, put several files in one ZIP, or render it later on a queue worker.

## The file objects {#files}

`->pdf()` returns a `BiztechEG\EasyPdfWord\PdfDocument` and `->word()` returns a `BiztechEG\EasyPdfWord\WordDocument`. Both share the same methods, and so does the ZIP file from `Doc::zip()`.

The examples on this page start from this invoice:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$document = Doc::template('invoice', [
    'invoice' => ['number' => 'INV-2026-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'seller'  => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    'buyer'   => ['name' => 'مؤسسة النور للتجارة'],
    'items'   => [
        ['description' => 'تطوير نظام إدارة المخزون', 'quantity' => 1, 'unit_price' => 25000],
        ['description' => 'تدريب فريق العمل', 'quantity' => 3, 'unit_price' => 1500],
    ],
])->locale('ar');

$pdf  = $document->pdf('فاتورة-INV-2026-1024.pdf');
$word = $document->word('فاتورة-INV-2026-1024.docx');
```

The name you pass to `->pdf()` or `->word()` is used for downloads and mail attachments. Without one, the file is named after the template (`invoice.pdf`, `invoice.docx`), or `document.pdf` for views, HTML and `Doc::make()`. The extension is added when it is missing, and `/` or `\` in a name become `-`.

## Methods {#methods}

| Method | Returns | What it does |
| --- | --- | --- |
| `download(?string $filename = null)` | `Response` | Sends the file as a download (`Content-Disposition: attachment`). |
| `stream(?string $filename = null)` | `Response` | Shows the file in the browser (`inline`). `inline()` is the same method. |
| `save(string $path, ?string $disk = null)` | `string` | Writes the file to a filesystem disk, or to an absolute path when no disk is given. Returns the path. |
| `content()` | `string` | The file's bytes. `toString()` is the same. |
| `base64()` | `string` | The bytes encoded as base64, for example for a JSON API. |
| `filename()` | `string` | The name used for downloads and attachments, with its extension. |
| `engine()` | `string` | What made the file: `mpdf`, `browsershot` (Chromium), `gotenberg` or your own engine for PDFs; `phpword` or `docx-template` for Word files; `zip` for archives. |
| `mimeType()` | `string` | `application/pdf`, the Word type, or `application/zip`. |
| `extension()` | `string` | `pdf`, `docx` or `zip`. |
| `toMailAttachment()` | `Attachment` | Called by Laravel when you attach the file to a mail. |

The `Response` is a `Symfony\Component\HttpFoundation\Response`, which every Laravel route can return. A name given to `download()` or `stream()` is used for that response only.

## Download or show in the browser {#download-and-stream}

```php
// Save dialog in the browser
return $pdf->download();

// Open in the browser's PDF viewer, with another name
return $pdf->stream('invoice-INV-2026-1024.pdf');

// Word files work the same way
return $word->download();
```

Arabic file names are fine. The response carries the UTF-8 name, plus an ASCII copy of it in Latin letters for old clients: `فاتورة-1024.pdf` becomes `fator-1024.pdf`, and a name with nothing left in Latin letters becomes `document.pdf`.

## Return a file from a controller {#controllers}

A PDF or Word file returned from a route or controller is shown in the browser, as if you had called `->stream()`:

```php
namespace App\Http\Controllers;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        return Doc::template('invoice', [
            'invoice' => ['number' => $invoice->number, 'date' => $invoice->issued_at, 'currency' => $invoice->currency],
            'buyer'   => ['name' => $invoice->customer_name],
            'items'   => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'unit_price'  => $item->unit_price,
            ])->all(),
        ])->locale('ar')->pdf("invoice-{$invoice->number}.pdf");
    }
}
```

Return `->pdf()->download()` instead when the browser should save the file. The [Download, preview or store](/recipes/controller-responses) recipe shows the three choices side by side.

## Save to a disk or a path {#save}

```php
// On a filesystem disk from config/filesystems.php
$pdf->save('invoices/2026/INV-2026-1024.pdf', disk: 's3');

// On the default disk (FILESYSTEM_DISK)
$pdf->save('invoices/INV-2026-1024.pdf');

// At an absolute path; missing folders are created
$pdf->save(storage_path('app/exports/INV-2026-1024.pdf'));
```

A path that starts with `/` (or `C:\` on Windows) is written straight to the file system when you give no disk. Any other path goes to a disk.

### When a save fails {#save-failures}

A save that does not happen throws a `RuntimeException`, so a failed write never looks like a success:

```text
Could not write [invoices/INV-2026-1024.pdf] to the [s3] disk.
Could not create the folder [/var/www/exports/2026].
Could not write [/var/www/exports/2026/INV-2026-1024.pdf].
```

Check the disk's credentials and bucket, or the folder's permissions for the user PHP runs as. A disk with `'throw' => true` in `config/filesystems.php` throws Flysystem's own exception instead, which often says more.

## When rendering happens {#when-rendering-happens}

`->pdf()` and `->word()` do not run the engine. They take a copy of the document, so later changes to `$document` do not reach the file, and the file is rendered the first time its bytes are needed:

- `content()`, `toString()`, `base64()`, `engine()`, `download()`, `stream()` and `save()`;
- returning the file from a controller;
- sending a mail that has the file attached;
- building a ZIP that contains the file.

The bytes are kept, so calling `download()` after `save()` does not render twice.

Template data is checked against the template's rules when you call `->pdf()` or `->word()`, so a mistake throws a `ValidationException` at that line.

## See the HTML: toHtml() {#to-html}

`toHtml()` returns the final HTML that the PDF engine gets, after the layout, fonts and digits are applied. It is the quickest way to debug a layout:

```php
file_put_contents(storage_path('app/invoice-debug.html'), $document->toHtml());

// or look at it in the browser
Route::get('/debug/invoice', fn () => response($document->toHtml()));
```

With mPDF the HTML does not carry the fonts, so a browser shows it with its own fonts. Call `->driver('chromium')` first to get HTML with the fonts embedded, as Chromium receives it. `php artisan doc:sample invoice --format=html` writes the same HTML for a template's sample data (see [Artisan commands](/guide/commands)).

## Mail attachments {#mail-attachments}

A PDF or Word file can be attached to a mail as it is. In a Mailable, return it from `attachments()`:

```php
namespace App\Mail;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceIssued extends Mailable implements ShouldQueue
{
    use Queueable;

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
        return [
            Doc::template('invoice', $this->invoice)
                ->locale('ar')
                ->pdf('فاتورة-'.$this->invoice['invoice']['number'].'.pdf'),
        ];
    }
}
```

```php
Mail::to('accounts@alnoor.example')->send(new InvoiceIssued($data));
```

In a notification, pass the file to `->attach()`:

```php
namespace App\Notifications;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    use Queueable;

    public function __construct(public array $receipt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تم استلام دفعتك')
            ->line('شكراً لك، مرفق سند القبض.')
            ->attach(Doc::template('receipt', $this->receipt)->locale('ar')->pdf('سند-قبض-'.$this->receipt['number'].'.pdf'));
    }
}
```

The attachment gets the file's name and MIME type, and the file is rendered when the mail is built.

### Queued mail {#queued-mail}

A queued Mailable or notification is serialized onto the queue. A file that has not been rendered yet cannot be serialized, so build it inside `attachments()` or `toMail()` as above, and pass the data (or a model) to the constructor:

```php
// Works: the worker builds the PDF when it sends the mail
Mail::to($customer)->send(new InvoiceIssued($data));

// Fails when InvoiceFileMail is queued and takes the file in its constructor:
// "Failed to serialize job of type [Illuminate\Mail\SendQueuedMailable]:
//  Serialization of 'Closure' is not allowed"
Mail::to($customer)->send(new InvoiceFileMail(Doc::template('invoice', $data)->pdf()));
```

The [Email an invoice](/recipes/email-invoice) recipe goes through a complete example.

## ZIP files {#zip}

`Doc::zip()` puts several files in one archive. The archive has the same methods as a PDF: `download()`, `stream()`, `save()`, `content()`, and it can be attached to a mail.

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([
    $invoice->pdf('فاتورة-INV-2026-1024.pdf'),
    $invoice->word('فاتورة-INV-2026-1024.docx'),
    'سند-قبض-RV-2026-0315.pdf' => Doc::template('receipt', $receipt)->locale('ar')->pdf(),
], 'order-1024.zip')->download();
```

- Each file keeps its own name, unless an array key gives another. The extension is added when a key leaves it out.
- A name used twice becomes `name (2).pdf`, then `name (3).pdf`.
- Names never contain folders: `/` and `\` become `-`, so an archive cannot unpack outside its folder.
- The archive's name defaults to `documents.zip`.
- `Doc::zip([])` throws `A ZIP file needs at least one file.`, and anything other than a file made by `->pdf()`, `->word()` or `Doc::zip()` is refused.

ZIP files need the PHP `zip` extension. Without it you get `ZIP files need the PHP zip extension (ext-zip).` when the archive is built. The [Monthly payslips in one ZIP](/recipes/payslips-zip) recipe builds one archive per month.

## Render on a queue {#queue}

A queue worker can render and save the file instead of the request:

```php
Doc::template('invoice', $data)->locale('ar')->queue('invoices/INV-2026-1024.pdf', disk: 's3');
```

The extension picks the format: `.pdf` or `.docx`. Any other extension throws an `InvalidArgumentException` at once. Template data is checked before anything is queued, so a mistake shows up in the request, not as a failed job. The same goes for `.docx` with a document that has no Word layout or has a password.

`->queue()` returns Laravel's `PendingDispatch`, so the usual options work:

```php
use App\Jobs\SendInvoiceToCustomer;

Doc::template('invoice', $data)
    ->locale('ar')
    ->queue('invoices/INV-2026-1024.pdf', disk: 's3')
    ->onConnection('redis')
    ->onQueue('documents')
    ->delay(now()->addMinutes(5))
    ->chain([new SendInvoiceToCustomer('INV-2026-1024')]);
```

Jobs in `->chain()` run after the file is saved, so `SendInvoiceToCustomer` can read it from the disk.

### The job {#queue-job}

The job is `BiztechEG\EasyPdfWord\Jobs\SaveDocument`. It carries everything needed to build the document again on the worker: the template, view, HTML or blocks, the data, and every setting (locale, digits, theme, paper, engine, watermark, password). The request's locale is stored with it, so a document without `->locale()` still comes out in the language of the request, not the worker's.

Template data is stored as plain arrays: models and collections become arrays first. Data for your own Blade views is serialized as it is, so it cannot hold closures.

A worker on another server saves to its own local disk, so use a shared disk such as `s3` when workers and web servers are different machines.

### Encryption {#queue-encryption}

`SaveDocument` implements `ShouldBeEncrypted`. Laravel encrypts its payload with your `APP_KEY`, because it holds the document's data and any PDF password. Workers need the same `APP_KEY` as the app that queued the job.

### Size limits {#queue-size}

The job carries the data, so a report with thousands of rows makes a large job. Amazon SQS takes messages up to 1 MB, and Beanstalkd 64 KB by default. Redis and the database driver have no small limit, but large payloads still slow the queue.

For big reports, queue a job of your own that carries only what it needs to load the data, and build the document there:

```php
namespace App\Jobs;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class BuildMonthlySalesReport implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;

    public function __construct(public string $month) {}

    public function handle(): void
    {
        $rows = DB::table('orders')
            ->where('created_at', 'like', $this->month.'%')
            ->get(['number', 'customer', 'total']);

        Doc::template('report', [
            'title'   => 'تقرير مبيعات '.$this->month,
            'columns' => ['number' => 'رقم الطلب', 'customer' => 'العميل', 'total' => ['label' => 'الإجمالي', 'format' => 'number']],
            'rows'    => $rows,
            'sum'     => ['total'],
        ])->locale('ar')->pdf()->save("reports/sales-{$this->month}.pdf", 's3');
    }
}
```

```php
BuildMonthlySalesReport::dispatch('2026-09')->onQueue('documents');
```

### Worker timeouts {#queue-timeouts}

A worker stops a job that runs longer than its `--timeout` (60 seconds by default) and reports `... has timed out.` A document job may need the engine's timeout (60 seconds for Chromium and Gotenberg) plus a second render by the [fallback engine](/guide/engines#fallback), so give the documents queue more time:

```bash
php artisan queue:work redis --queue=documents --timeout=180
```

Keep `retry_after` for that connection in `config/queue.php` larger than the timeout (for example `240`), or another worker picks up the job while the first one is still rendering.

Testing queued saves is covered in [Testing your app](/guide/testing#queue).
