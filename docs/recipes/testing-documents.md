# Testing document features

Cover the features that make documents with fast feature tests: `Doc::fake()` records which document your code asked for, from what data, and where it went, without rendering anything. A few slower tests then render the real file to check your own templates.

## The situation {#situation}

An invoicing app has four features that make documents:

- a link that downloads an invoice as an Arabic PDF;
- a button that emails the invoice to the customer and keeps a copy on S3, rendered by a queue worker;
- a monthly sales report, built by a queued job that saves the PDF and notifies the user (from [Sales report from a query](/recipes/sales-report));
- a ZIP with one payslip per employee for a month.

The team wants every one of them covered in CI. Rendering a real PDF in every test is slow, needs the PDF engine on the CI machine, and comparing PDF bytes tells you nothing useful. What matters is: was the right template used, with the right data and language, under the right file name, and was the file downloaded, attached or saved where it should be? On top of that, the team keeps its own copy of the invoice template with the bank details added, and a careless edit to it must fail a test.

## The solution {#solution}

### The code under test

::: details Routes, controllers, the Mailable and the models' document data
```php
// routes/web.php
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PayslipsZipController;
use App\Jobs\BuildSalesReport;
use Illuminate\Http\Request;

Route::middleware('auth')->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'download'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::get('/payroll/{month}/payslips.zip', PayslipsZipController::class)->name('payroll.zip');

    Route::post('/reports/sales', function (Request $request) {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        BuildSalesReport::dispatch($request->input('month'), $request->user()->id);

        return back()->with('status', 'نجهّز التقرير الآن، وسيصلك إشعار عندما يكتمل.');
    })->name('reports.sales.queue');
});
```

```php
// app/Http/Controllers/InvoiceController.php
namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function download(Invoice $invoice)
    {
        return Doc::template('invoice', $invoice->toDocumentData())
            ->locale('ar')
            ->pdf()
            ->download("فاتورة-{$invoice->number}.pdf");
    }

    public function send(Invoice $invoice)
    {
        Mail::to($invoice->customer->email)->queue(new InvoiceMail($invoice));

        // A copy for the archive, rendered by a queue worker.
        Doc::template('invoice', $invoice->toDocumentData())
            ->locale('ar')
            ->queue("invoices/{$invoice->number}.pdf", disk: 's3');

        return back()->with('status', 'أُرسلت الفاتورة إلى العميل.');
    }
}
```

```php
// app/Mail/InvoiceMail.php
namespace App\Mail;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "فاتورة رقم {$this->invoice->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice');
    }

    public function attachments(): array
    {
        return [
            Doc::template('invoice', $this->invoice->toDocumentData())
                ->locale('ar')
                ->pdf("فاتورة-{$this->invoice->number}.pdf"),
        ];
    }
}
```

```php
// app/Http/Controllers/PayslipsZipController.php
namespace App\Http\Controllers;

use App\Models\Payslip;
use BiztechEG\EasyPdfWord\Facades\Doc;

class PayslipsZipController extends Controller
{
    public function __invoke(string $month)
    {
        $payslips = Payslip::with('employee')->where('period', $month)->get();

        $files = $payslips->map(fn (Payslip $payslip) => Doc::template('payslip', $payslip->toDocumentData())
            ->locale('ar')
            ->pdf("قسيمة-{$payslip->employee->code}.pdf"));

        return Doc::zip($files->all(), "payslips-{$month}.zip")->download();
    }
}
```

```php
// app/Models/Invoice.php
public function toDocumentData(): array
{
    return [
        'invoice' => ['number' => $this->number, 'date' => $this->issued_at, 'currency' => 'SAR', 'tax_rate' => 15],
        'buyer' => ['name' => $this->customer->name, 'tax_number' => $this->customer->tax_number],
        'items' => $this->items->map->only(['description', 'quantity', 'unit_price']),
    ];
}

// app/Models/Payslip.php
public function toDocumentData(): array
{
    return [
        'period' => $this->period,
        'currency' => 'SAR',
        'employee' => ['name' => $this->employee->name, 'code' => $this->employee->code],
        'earnings' => [['name' => 'الراتب الأساسي', 'amount' => $this->basic], ['name' => 'البدلات', 'amount' => $this->allowances]],
        'deductions' => [['name' => 'التأمينات', 'amount' => $this->deductions]],
    ];
}
```

`BuildSalesReport` and `SalesReportReady` are the job and notification from [Sales report from a query](/recipes/sales-report).
:::

The tests below use the app's model factories (`User`, `Customer`, `Invoice` and `InvoiceItem` for `hasItems()`, `Employee`, `Payslip`) and `RefreshDatabase`.

### 1. A download

```php
// tests/Feature/InvoiceDocumentsTest.php
namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $customer = Customer::factory()->create([
            'name' => 'مؤسسة النور للتجارة',
            'email' => 'accounts@alnoor.example',
        ]);

        return Invoice::factory()->for($customer)->hasItems(2)->create(['number' => 'INV-1024']);
    }

    public function test_the_invoice_downloads_as_an_arabic_pdf(): void
    {
        Doc::fake();
        $invoice = $this->invoice();

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertDownload();

        Doc::assertDownloaded('فاتورة-INV-1024.pdf');
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'invoice'
            && $doc->locale === 'ar'
            && $doc->data('invoice.number') === 'INV-1024'
            && $doc->data('buyer.name') === 'مؤسسة النور للتجارة'
            && count($doc->data('items')) === 2);
    }
}
```

After `Doc::fake()`, every `->pdf()` and `->word()` call is recorded as a `GeneratedDocument` instead of rendered. The response still works: it carries a few placeholder bytes, with the real headers and file name. `data()` reads the data the template received, with dot notation, after the template has filled its defaults and computed its totals.

::: warning assertDownload() and Arabic file names
Laravel's `assertDownload('فاتورة-INV-1024.pdf')` fails even when the name is right: it compares the plain `filename=` part of the header, which holds an ASCII fallback in Latin letters (`fator-INV-1024.pdf`); browsers use the UTF-8 `filename*=` part. Call `assertDownload()` without a name to check that the response is a download, and check the name with `Doc::assertDownloaded()`.
:::

### 2. Invalid data

The invoice template requires at least one item. `Doc::fake()` still checks the template's data, so a test can make sure an empty invoice never turns into a PDF:

```php
public function test_an_invoice_without_items_cannot_be_downloaded(): void
{
    Doc::fake();
    $invoice = Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('invoices.pdf', $invoice))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    Doc::assertNothingGenerated();
}
```

The template throws Laravel's `ValidationException`, which becomes a 422 response with the errors, as a form request would.

### 3. An email with the PDF, and a copy on the queue

```php
public function test_sending_mails_the_pdf_and_archives_a_copy(): void
{
    Doc::fake();
    Mail::fake();
    Queue::fake();
    $invoice = $this->invoice();

    $this->actingAs(User::factory()->create())
        ->post(route('invoices.send', $invoice))
        ->assertRedirect();

    Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail) {
        return $mail->hasTo('accounts@alnoor.example')
            && $mail->attachments()[0]->filename() === 'فاتورة-INV-1024.pdf';
    });

    Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->isPdf()
        && $doc->filename() === 'فاتورة-INV-1024.pdf'
        && $doc->contains('مؤسسة النور للتجارة'));

    Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->path === 'invoices/INV-1024.pdf'
        && $job->disk === 's3'
        && $job->format === 'pdf');
}
```

What each part checks:

- **The mail.** `Mail::fake()` keeps the Mailable without building it. Calling `attachments()` in the check builds the attachment, a recorded file under `Doc::fake()`, so the `Doc::assertGenerated()` that follows sees it.
- **`contains()`** searches the HTML the PDF engine would get, so it proves the customer's name is really printed, not only passed in the data.
- **The archived copy.** `->queue()` dispatches the package's `SaveDocument` job, with public `path`, `disk` and `format` (`pdf` or `word`, from the extension). With `Queue::fake()` the job is only recorded.

### 4. A queued job that saves a report

Test the dispatch and the job separately. The route only queues the job:

```php
public function test_the_sales_report_is_queued(): void
{
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('reports.sales.queue'), ['month' => '2026-09'])
        ->assertRedirect();

    Queue::assertPushed(BuildSalesReport::class, fn (BuildSalesReport $job) => $job->month === '2026-09'
        && $job->userId === $user->id);
}
```

Then run the job's `handle()` directly, as a worker would:

```php
public function test_the_sales_report_job_saves_the_pdf_to_s3(): void
{
    Doc::fake();
    Notification::fake();
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['issued_at' => '2026-09-15 10:00', 'total' => 1150]);
    Invoice::factory()->create(['issued_at' => '2026-10-01 09:00']);

    (new BuildSalesReport('2026-09', $user->id))->handle();

    Doc::assertSaved('reports/sales-2026-09.pdf', disk: 's3');
    Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->template === 'report'
        && count($doc->data('rows')) === 3
        && $doc->data('totals.total') === 3450.0);
    Notification::assertSentTo($user, SalesReportReady::class);
}
```

Add `use App\Jobs\BuildSalesReport;`, `use App\Notifications\SalesReportReady;` and `use Illuminate\Support\Facades\Notification;` to the test file. Nothing is written to S3: `assertSaved()` checks the path and disk the code asked for. The October invoice is left out by the query, and `totals.total` is the sum the report template computed, as a float.

### 5. A ZIP of payslips

```php
public function test_the_payslips_zip_has_one_pdf_per_employee(): void
{
    Doc::fake();

    foreach (['EMP-001', 'EMP-002', 'EMP-003'] as $code) {
        $employee = Employee::factory()->create(['code' => $code]);
        Payslip::factory()->for($employee)->create(['period' => '2026-09']);
    }

    $response = $this->actingAs(User::factory()->create())
        ->get(route('payroll.zip', '2026-09'))
        ->assertOk()
        ->assertDownload('payslips-2026-09.zip');

    Doc::assertGeneratedCount(3);
    Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'payslip'
        && $doc->data('period') === '2026-09'
        && $doc->data('employee.code') === 'EMP-001');

    $path = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($path, $response->getContent());
    $zip = new ZipArchive;
    $zip->open($path);

    $this->assertSame(3, $zip->numFiles);
    $this->assertNotFalse($zip->locateName('قسيمة-EMP-001.pdf'));

    $zip->close();
    unlink($path);
}
```

Add `use App\Models\Employee;`, `use App\Models\Payslip;` and `use ZipArchive;`. Under the fake the ZIP is real and holds the placeholder PDFs under their real names, so you can open it and check them. Here `assertDownload()` with a name works, because the ZIP's name is plain ASCII.

## What Doc::fake() records {#fake-reference}

Assertions on the `Doc` facade after `Doc::fake()`:

| Assertion | Passes when |
|---|---|
| `Doc::assertGenerated($callback = null)` | at least one file was made (that the callback accepts) |
| `Doc::assertNotGenerated($callback)` | no file matches the callback |
| `Doc::assertGeneratedCount(3)` | exactly that many files were made |
| `Doc::assertNothingGenerated()` | no file was made |
| `Doc::assertSaved('reports/x.pdf', disk: 's3')` | a file was saved to that path (on that disk, when given); also takes a callback |
| `Doc::assertDownloaded('name.pdf')` | a file was sent as a download with that name; also takes a callback, or nothing |
| `Doc::assertStreamed('name.pdf')` | a file was shown in the browser with `stream()` or returned from a controller |

`Doc::generated($callback = null)` returns the recorded files themselves. Each one is a `GeneratedDocument` with:

| Member | What it gives |
|---|---|
| `template`, `view` | the template or Blade view name, or `null` |
| `format`, `isPdf()`, `isWord()` | `pdf` or `word` |
| `locale`, `direction`, `numerals` | for example `ar`, `rtl`, `arabic` |
| `driver` | the engine asked for with `->driver()`, or `null` for the default |
| `watermark`, `protected` | the PDF's watermark text, and whether it has a password |
| `filename()` | the file name with its extension |
| `data($key = null)` | the template data after defaults and totals, with dot notation |
| `html()`, `contains($text)` | the HTML a PDF engine would get (PDF only), and whether it contains the text |
| `wasSaved($path, $disk)`, `wasDownloaded($name)`, `wasStreamed($name)` | what was done with the file |

When the `SaveDocument` job of `->queue()` runs during a test, its save is recorded too (see [Variations](#variations)).

## Test the real file {#real-file}

The fake proves your code asks for the right document. It does not prove the template prints it right. For your own templates, render the real PDF in a few tests and read its text with `pdftotext` (from poppler-utils):

```php
// tests/Feature/InvoiceTemplateTest.php
namespace Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Support\Facades\Process;
use Normalizer;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class InvoiceTemplateTest extends TestCase
{
    public function test_our_invoice_template_prints_the_bank_details(): void
    {
        if ((new ExecutableFinder)->find('pdftotext') === null) {
            $this->markTestSkipped('pdftotext (poppler-utils) is not installed.');
        }

        $data = Doc::templates()->get('invoice')->sample();

        $text = $this->pdfText(Doc::template('invoice', $data)->locale('ar')->pdf()->content());

        $this->assertStringContainsString('الحساب البنكي', $text);
        $this->assertStringContainsString('بنك مصر', $text);
        $this->assertStringContainsString('EG38 0019 0005 0000 0000 2631 8000 2', $text);
        $this->assertStringContainsString('INV-2026-1024', $text);
    }

    /** The PDF's text, with Arabic letters in their plain form. */
    protected function pdfText(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, $pdf);

        try {
            $text = Process::run(['pdftotext', $file, '-'])->throw()->output();
        } finally {
            unlink($file);
        }

        // pdftotext gives Arabic in its joined letter forms, with direction marks.
        return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', Normalizer::normalize($text, Normalizer::FORM_KC));
    }
}
```

- **`sample()`** returns the template's own sample data, so the test does not need a database. Here `invoice` is the project's copy of the template (made with `php artisan doc:template invoice`), with a bank details line added; a project copy with the same name replaces the package's template.
- **Arabic text from `pdftotext`** comes out in the joined letter shapes a PDF stores, with invisible direction marks. `Normalizer::FORM_KC` (from `ext-intl`) turns the shapes back into plain letters, and the `preg_replace()` removes the marks, so a plain Arabic string can be found.
- **The test skips** where `pdftotext` is missing. On Ubuntu (GitHub Actions runners included), install it with:

```bash
sudo apt-get install -y poppler-utils
```

::: tip What pdftotext cannot give back
A watermark is drawn at an angle and comes out of `pdftotext` in pieces, so search for other words on a page with one. The word الله comes out as one character (ﷲ), which `Normalizer::FORM_KC` turns back into its letters, so "فهد بن عبدالله السبيعي" is found as written.
:::

## Variations {#variations}

### Let the queue run

Laravel's `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so without `Queue::fake()` the `SaveDocument` job runs at once. Under `Doc::fake()` its save is recorded like any other:

```php
public function test_the_archived_copy_is_saved(): void
{
    Doc::fake();
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('invoices.send', $this->invoice()));

    Doc::assertSaved('invoices/INV-1024.pdf', disk: 's3');
}
```

Without `Doc::fake()`, and with `Storage::fake('s3')`, the worker renders the real PDF into the fake disk:

```php
Storage::fake('s3');
Mail::fake();

$this->actingAs(User::factory()->create())
    ->post(route('invoices.send', $this->invoice()));

Storage::disk('s3')->assertExists('invoices/INV-1024.pdf');
$this->assertStringStartsWith('%PDF', Storage::disk('s3')->get('invoices/INV-1024.pdf'));
```

### Check a Word file

A `.docx` file is a ZIP; the text is in `word/document.xml`:

```php
$data = Doc::templates()->get('invoice')->sample();

$docx = Doc::template('invoice', $data)->locale('ar')->word()->content();

$file = tempnam(sys_get_temp_dir(), 'docx');
file_put_contents($file, $docx);
$zip = new ZipArchive;
$zip->open($file);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
unlink($file);

$this->assertStringContainsString('<w:bidi/>', $xml);
$this->assertStringContainsString('مؤسسة النور للتجارة', $xml);
$this->assertStringContainsString('INV-2026-1024', $xml);
```

`<w:bidi/>` marks right-to-left paragraphs. Search for short pieces such as a name or a number: Word may split a sentence into several runs.

### Drafts, passwords, digits and engines

The recorded file keeps the settings your code chose:

```php
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->watermark === 'مسودة');
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->protected);
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->numerals === 'arabic' && $doc->direction === 'rtl' && $doc->driver === 'chromium');
Doc::assertNotGenerated(fn (GeneratedDocument $doc) => $doc->isWord());
```

With Arabic digits, `contains()` must look for the digits as printed: `$doc->contains('INV-٢٠٢٦-١٠٢٤')`.

## Related pages {#related}

- [Testing your app](/guide/testing): `Doc::fake()` and every assertion in detail.
- [Output and delivery](/guide/output): downloads, streams, saves, mail attachments, ZIP files and queued rendering.
- [Ready-made templates](/guide/templates): template data, validation and sample data.
- [Your own templates](/guide/custom-templates): copying a template into your project.
- [Word files](/guide/word): what a Word file contains.
- [Sales report from a query](/recipes/sales-report): the job tested in step 4.
