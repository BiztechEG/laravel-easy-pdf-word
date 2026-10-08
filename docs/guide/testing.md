# Testing your app

Test the code that makes documents without rendering them: `Doc::fake()` records every PDF and Word file your code asks for, and the assertions check what was made, with which data, and what was done with it.

## Fake the documents {#fake}

`Doc::fake()` works like `Mail::fake()`. Call it at the start of a test:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::fake();
```

From then on:

- Templates still get their defaults, run their `prepare()` and are validated, so invalid data still throws a `ValidationException`, as in production.
- No PDF engine runs and PhpWord is not called. A file's bytes are a small placeholder (`%PDF-1.4 ...` or `PK ...`).
- `save()` writes nothing; `download()` and `stream()` return a response with the placeholder.
- Each file is recorded as a `GeneratedDocument`, which the assertions below look at.

Classes resolved after `Doc::fake()` that have `BiztechEG\EasyPdfWord\DocFactory` (the class behind the facade) injected get the fake too. The fake lasts for one test; the next test starts with the real `Doc` again.

## Assertions {#assertions}

| Assertion | Passes when |
| --- | --- |
| `Doc::assertGenerated(?Closure $callback = null)` | At least one file was made, and matches the callback when given |
| `Doc::assertNotGenerated(Closure $callback)` | No file matches the callback |
| `Doc::assertGeneratedCount(int $count)` | Exactly this many files were made |
| `Doc::assertNothingGenerated()` | No file was made |
| `Doc::assertSaved(string\|Closure $path, ?string $disk = null)` | A file was saved to this path (on this disk, when given), or a saved file matches the callback |
| `Doc::assertDownloaded(string\|Closure\|null $filename = null)` | A file was sent as a download, with this name or matching the callback |
| `Doc::assertStreamed(string\|Closure\|null $filename = null)` | A file was shown in the browser with `stream()` or returned from a controller, with this name or matching the callback |

A file counts as made when your code calls `->pdf()` or `->word()`. Building a document without asking for a file does not count. Callbacks get a `GeneratedDocument` and return `true` for a match.

`Doc::generated()` returns every recorded `GeneratedDocument`, and `Doc::generated($callback)` the ones that match, for checks of your own.

## The recorded document {#generated-document}

`BiztechEG\EasyPdfWord\Testing\GeneratedDocument` describes one file:

| Property or method | Value |
| --- | --- |
| `format` | `'pdf'` or `'word'` |
| `isPdf()`, `isWord()` | The same, as booleans |
| `template` | The template name, or `null` for views, HTML and `Doc::make()` |
| `view` | The view name for `Doc::view()`, otherwise `null` |
| `locale` | The document's locale, such as `'ar'` |
| `direction` | `'rtl'` or `'ltr'` |
| `numerals` | `'arabic'` or `'latin'` |
| `driver` | The engine asked for with `->driver()`, or `null` for the default |
| `watermark` | The watermark text of a PDF, or `null` |
| `protected` | `true` when the PDF has a password |
| `filename()` | The file name with its extension, as a download or attachment gets it |
| `data(?string $key = null, mixed $default = null)` | The data the file was made from. For a template, after its defaults and `prepare()`, so computed totals are there. A dot-notation key picks one value: `data('totals.total')`. |
| `html()` | The HTML a PDF engine would get. Word files have none and throw a `LogicException`. |
| `contains(string $text)` | Whether the PDF's HTML contains the text, escaped as a view prints it |
| `wasSaved(?string $path = null, ?string $disk = null)` | Whether the file was saved (there, when given) |
| `saves()` | Every save, as `['path' => ..., 'disk' => ...]` |
| `wasDownloaded(?string $filename = null)`, `wasStreamed(?string $filename = null)` | Whether the file was sent as a download or shown in the browser |

With `->numerals('arabic')`, the HTML has Arabic digits, so `contains()` needs the digits in Arabic too: `contains('٢٥٬٠٠٠')` or `contains('٢٥,٠٠٠')`, depending on the [font](/guide/arabic#separators).

## A complete test {#example}

The controller under test:

```php
// routes/web.php
use App\Models\Order;
use BiztechEG\EasyPdfWord\Facades\Doc;

Route::get('/orders/{order}/invoice', function (Order $order) {
    $document = Doc::template('invoice', [
        'invoice' => ['number' => $order->number, 'date' => $order->created_at, 'currency' => 'EGP'],
        'buyer'   => ['name' => $order->customer_name],
        'items'   => [['description' => $order->description, 'quantity' => 1, 'unit_price' => $order->amount]],
    ])->locale('ar');

    $pdf = $document->pdf("فاتورة-{$order->number}.pdf");
    $pdf->save("invoices/{$order->number}.pdf", 's3');

    return $pdf->download();
});
```

The test, in PHPUnit style:

```php
namespace Tests\Feature;

use App\Models\Order;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invoice_is_saved_and_downloaded(): void
    {
        Doc::fake();
        Storage::fake('s3');

        $order = Order::create([
            'number' => 'INV-2026-1024',
            'customer_name' => 'مؤسسة النور للتجارة',
            'description' => 'تطوير نظام إدارة المخزون',
            'amount' => 25000,
        ]);

        $this->get("/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        Doc::assertGeneratedCount(1);
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'invoice'
            && $doc->locale === 'ar'
            && $doc->direction === 'rtl'
            && $doc->data('invoice.number') === 'INV-2026-1024'
            && $doc->data('totals.total') == 28500
            && $doc->contains('مؤسسة النور للتجارة'));
        Doc::assertSaved('invoices/INV-2026-1024.pdf', disk: 's3');
        Doc::assertDownloaded('فاتورة-INV-2026-1024.pdf');
    }

    public function test_invalid_data_is_refused(): void
    {
        Doc::fake();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        Doc::template('invoice', ['invoice' => ['number' => 'INV-1', 'date' => '2026-10-08']])->pdf();
    }
}
```

`Storage::fake('s3')` is not needed for the save, which `Doc::fake()` only records, but it keeps a test from touching a real bucket if the code writes other files.

### Pest {#pest}

Pest calls the same static methods:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;

it('downloads the invoice in Arabic', function () {
    Doc::fake();

    $order = Order::factory()->create(['number' => 'INV-2026-1024']);

    $this->get("/orders/{$order->id}/invoice")->assertOk();

    Doc::assertDownloaded(fn (GeneratedDocument $doc) => $doc->locale === 'ar'
        && $doc->filename() === 'فاتورة-INV-2026-1024.pdf');
});
```

## ZIP files {#zip}

A ZIP from `Doc::zip()` is still built under `Doc::fake()`, from the placeholder files, and saved or sent as usual. So check the archive itself, with `Storage::fake()` or the response. Here the route saves the month's payslips (`قسيمة-1001.pdf`, `قسيمة-1002.pdf`) as `payroll/2026-09.zip` on the `local` disk:

```php
use Illuminate\Support\Facades\Storage;

Doc::fake();
Storage::fake('local');

$this->post('/payroll/2026-09/export')->assertOk();

Doc::assertGeneratedCount(2);

$zip = new ZipArchive;
$zip->open(Storage::disk('local')->path('payroll/2026-09.zip'));
$this->assertNotFalse($zip->locateName('قسيمة-1001.pdf'));
$this->assertNotFalse($zip->locateName('قسيمة-1002.pdf'));
$zip->close();
```

The files inside the archive count as made, so `assertGenerated()` and `assertGeneratedCount()` see them.

## Queued saves {#queue}

With the `sync` queue, which most `phpunit.xml` files set (`QUEUE_CONNECTION=sync`), the job runs at once and `Doc::fake()` records its save:

```php
Doc::fake();

Doc::template('invoice', $data)->locale('ar')->queue('invoices/INV-2026-1024.pdf', disk: 's3');

Doc::assertSaved('invoices/INV-2026-1024.pdf', disk: 's3');
Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->locale === 'ar' && $doc->filename() === 'INV-2026-1024.pdf');
```

Under `Queue::fake()`, nothing runs, so check the job instead:

```php
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use Illuminate\Support\Facades\Queue;

Queue::fake();

$this->post('/invoices/1024/archive')->assertOk();

Queue::assertPushedOn('documents', SaveDocument::class, fn (SaveDocument $job) => $job->path === 'invoices/INV-2026-1024.pdf'
    && $job->disk === 's3'
    && $job->format === 'pdf'
    && $job->document['template'] === 'invoice');
```

`$job->format` is `'pdf'` or `'word'`, and `$job->document['settings']` holds the document's settings, such as `locale`.

## Mail attachments {#mail}

Under `Mail::fake()`, a mail is recorded without being built, so its attachments are not made until you look at them. Check them in the `assertQueued()` or `assertSent()` callback:

```php
use App\Mail\InvoiceIssued;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PdfDocument;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use Illuminate\Support\Facades\Mail;

Doc::fake();
Mail::fake();

$this->post('/invoices/1024/send')->assertOk();

Mail::assertQueued(InvoiceIssued::class, function (InvoiceIssued $mail) {
    $file = $mail->attachments()[0];

    return $mail->hasTo('accounts@alnoor.example')
        && $file instanceof PdfDocument
        && $file->filename() === 'فاتورة-INV-2026-1024.pdf';
});

Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->data('invoice.number') === 'INV-2026-1024');
```

`InvoiceIssued` is the queued Mailable from [Output and delivery](/guide/output#mail-attachments), so it is checked with `assertQueued()`. Use `assertSent()` the same way for a mail that is not queued.

More examples, such as testing watermarks, passwords and Word files, are in the [Testing document features](/recipes/testing-documents) recipe.
