# اختبار ميزات المستندات

غطِّ الميزات التي تُنشئ مستندات باختبارات ميزات (feature tests) سريعة: يسجّل `Doc::fake()` المستند الذي طلبه الكود، والبيانات التي بُني منها، وأين ذهب، دون إنشاء أي ملف فعلي. ثم تُنشئ اختبارات قليلة أبطأ الملف الحقيقي لتتحقق من قوالبك الخاصة.

## السيناريو {#situation}

في تطبيق فوترة أربع ميزات تُنشئ مستندات:

- رابط ينزّل الفاتورة ملف PDF عربياً؛
- زر يرسل الفاتورة بالبريد إلى العميل ويحفظ نسخة على S3 يُنشئها worker في الـ queue؛
- تقرير مبيعات شهري تبنيه job في الـ queue، تحفظ ملف PDF وترسل إشعاراً للمستخدم (من [تقرير مبيعات من قاعدة البيانات](/ar/recipes/sales-report))؛
- ملف ZIP فيه قسيمة راتب لكل موظف عن شهر.

يريد الفريق أن يغطي كل ميزة منها في CI. لكن إنشاء ملف PDF حقيقي في كل اختبار بطيء، ويحتاج إلى محرك PDF على جهاز CI، ومقارنة بايتات ملف PDF لا تفيد بشيء. المهم هو: هل استُخدم القالب الصحيح، بالبيانات واللغة الصحيحة، وباسم الملف الصحيح، وهل نُزّل الملف أو أُرفق أو حُفظ حيث يجب؟ وفوق ذلك يحتفظ الفريق بنسخته الخاصة من قالب الفاتورة بعد إضافة البيانات البنكية، ويجب أن يفشل اختبار إذا أفسدها تعديل متسرّع.

## الحل {#solution}

### الكود الذي نختبره

::: details الـ routes والـ controllers والـ Mailable وبيانات المستندات في النماذج
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

`BuildSalesReport` و`SalesReportReady` هما الـ job والإشعار من [تقرير مبيعات من قاعدة البيانات](/ar/recipes/sales-report).
:::

تستخدم الاختبارات التالية الـ factories الخاصة بنماذج التطبيق (`User` و`Customer` و`Invoice` و`InvoiceItem` من أجل `hasItems()` و`Employee` و`Payslip`) مع `RefreshDatabase`.

### 1. التنزيل

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

بعد `Doc::fake()` يُسجَّل كل استدعاء لـ `->pdf()` و`->word()` كائناً من نوع `GeneratedDocument` بدلاً من إنشاء الملف. وتظل الاستجابة تعمل: فيها بضعة بايتات بديلة مع الترويسات الحقيقية واسم الملف. ويقرأ `data()` البيانات التي استقبلها القالب، بالنقاط للمستويات المتداخلة، بعد أن يملأ القالب قيمه الافتراضية ويحسب إجمالياته.

::: warning assertDownload() وأسماء الملفات العربية
يفشل `assertDownload('فاتورة-INV-1024.pdf')` في Laravel حتى لو كان الاسم صحيحاً: فهو يقارن الجزء `filename=` العادي من الترويسة، وفيه اسم بديل بحروف ASCII لاتينية (`fator-INV-1024.pdf`)، بينما تستخدم المتصفحات الجزء `filename*=` بترميز UTF-8. استدعِ `assertDownload()` دون اسم لتتأكد أن الاستجابة تنزيل، وتحقق من الاسم بـ `Doc::assertDownloaded()`.
:::

### 2. البيانات غير الصالحة

يتطلب قالب الفاتورة صنفاً واحداً على الأقل. ولأن `Doc::fake()` يظل يتحقق من بيانات القالب، يستطيع الاختبار أن يتأكد أن فاتورة بلا أصناف لا تتحول أبداً إلى ملف PDF:

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

يرمي القالب `ValidationException` الخاص بـ Laravel، فتتحول إلى استجابة 422 فيها الأخطاء، كما يفعل الـ form request.

### 3. بريد فيه ملف PDF، ونسخة عبر الـ queue

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

ما يتحقق منه كل جزء:

- **البريد.** يحتفظ `Mail::fake()` بالـ Mailable دون بنائه. واستدعاء `attachments()` داخل الفحص يبني المرفق، فيُسجَّل ملفاً تحت `Doc::fake()`، ويراه `Doc::assertGenerated()` الذي يليه.
- **`contains()`** يبحث في HTML الذي سيصل إلى محرك PDF، فيثبت أن اسم العميل يُطبع فعلاً، لا أنه مرّ في البيانات فقط.
- **النسخة المؤرشفة.** يرسل `->queue()` الـ job الخاصة بالحزمة `SaveDocument`، وفيها الخصائص العامة `path` و`disk` و`format` (`pdf` أو `word` بحسب الامتداد). ومع `Queue::fake()` تُسجَّل الـ job فقط.

### 4. job في الـ queue تحفظ تقريراً

اختبر الإرسال والـ job كلاً على حدة. فالـ route يكتفي بوضع الـ job في الـ queue:

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

ثم شغّل `handle()` في الـ job مباشرة كما يفعل الـ worker:

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

أضف `use App\Jobs\BuildSalesReport;` و`use App\Notifications\SalesReportReady;` و`use Illuminate\Support\Facades\Notification;` إلى ملف الاختبار. لا يُكتب شيء على S3: فـ `assertSaved()` يتحقق من المسار والـ disk اللذين طلبهما الكود. ويستبعد الاستعلام فاتورة أكتوبر، و`totals.total` هو المجموع الذي حسبه قالب التقرير، عدداً عشرياً (float).

### 5. ملف ZIP لقسائم الرواتب

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

أضف `use App\Models\Employee;` و`use App\Models\Payslip;` و`use ZipArchive;`. وتحت `Doc::fake()` يكون ملف ZIP حقيقياً وفيه ملفات PDF البديلة بأسمائها الحقيقية، فيمكنك فتحه والتحقق منها. وهنا ينجح `assertDownload()` مع الاسم، لأن اسم ملف ZIP بحروف ASCII فقط.

## ما يسجّله Doc::fake() {#fake-reference}

دوال التحقق على الـ facade المسمى `Doc` بعد `Doc::fake()`:

| دالة التحقق | تنجح عندما |
|---|---|
| `Doc::assertGenerated($callback = null)` | أُنشئ ملف واحد على الأقل (يقبله الـ callback) |
| `Doc::assertNotGenerated($callback)` | لا يوجد ملف يطابق الـ callback |
| `Doc::assertGeneratedCount(3)` | أُنشئ هذا العدد من الملفات بالضبط |
| `Doc::assertNothingGenerated()` | لم يُنشأ أي ملف |
| `Doc::assertSaved('reports/x.pdf', disk: 's3')` | حُفظ ملف في هذا المسار (وعلى هذا الـ disk إن حُدد)، وتقبل أيضاً callback |
| `Doc::assertDownloaded('name.pdf')` | أُرسل ملف تنزيلاً بهذا الاسم، وتقبل أيضاً callback أو لا شيء |
| `Doc::assertStreamed('name.pdf')` | عُرض ملف في المتصفح بـ `stream()` أو أعاده controller |

وتعيد `Doc::generated($callback = null)` الملفات المسجلة نفسها، وكل منها كائن `GeneratedDocument` فيه:

| العضو | ما يعطيه |
|---|---|
| `template`، `view` | اسم القالب أو ملف Blade، أو `null` |
| `format`، `isPdf()`، `isWord()` | `pdf` أو `word` |
| `locale`، `direction`، `numerals` | مثل `ar` و`rtl` و`arabic` |
| `driver` | المحرك المطلوب بـ `->driver()`، أو `null` للمحرك الافتراضي |
| `watermark`، `protected` | نص العلامة المائية في ملف PDF، وهل له كلمة مرور |
| `filename()` | اسم الملف مع امتداده |
| `data($key = null)` | بيانات القالب بعد القيم الافتراضية والإجماليات، بالنقاط للمستويات المتداخلة |
| `html()`، `contains($text)` | HTML الذي سيصل إلى محرك PDF (لملفات PDF فقط)، وهل يحتوي النص |
| `wasSaved($path, $disk)`، `wasDownloaded($name)`، `wasStreamed($name)` | ما حدث للملف |

وعندما تعمل الـ job المسماة `SaveDocument` الخاصة بـ `->queue()` أثناء الاختبار، يُسجَّل حفظها أيضاً (انظر [تنويعات](#variations)).

## اختبار الملف الحقيقي {#real-file}

يثبت الـ fake أن الكود يطلب المستند الصحيح، لكنه لا يثبت أن القالب يطبعه بشكل صحيح. لذلك أنشئ لقوالبك الخاصة ملف PDF حقيقياً في اختبارات قليلة، واقرأ نصه بـ `pdftotext` (من حزمة poppler-utils):

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

- **`sample()`** تعيد البيانات التجريبية للقالب نفسه، فلا يحتاج الاختبار إلى قاعدة بيانات. والقالب `invoice` هنا هو نسخة المشروع (المنشأة بـ `php artisan doc:template invoice`) بعد إضافة سطر البيانات البنكية، والنسخة التي في المشروع بالاسم نفسه تحل محل قالب الحزمة.
- **النص العربي من `pdftotext`** يخرج بأشكال الحروف المتصلة كما يحفظها ملف PDF، مع علامات اتجاه غير مرئية. يعيد `Normalizer::FORM_KC` (من `ext-intl`) الأشكال إلى حروف عادية، ويحذف `preg_replace()` العلامات، فيمكن العثور على نص عربي عادي.
- **يُتخطى الاختبار** حيث لا يوجد `pdftotext`. وعلى Ubuntu (ومنها أجهزة GitHub Actions) ثبّته بهذا الأمر:

```bash
sudo apt-get install -y poppler-utils
```

::: tip ما لا يستطيع pdftotext استرجاعه
العلامة المائية تُرسم مائلة فتخرج من `pdftotext` أجزاءً متفرقة، فابحث عن كلمات أخرى في الصفحة التي تحملها. أما كلمة الله فتخرج حرفاً واحداً (ﷲ)، ويعيدها `Normalizer::FORM_KC` إلى حروفها، فيُعثر على "فهد بن عبدالله السبيعي" كما كُتب.
:::

## تنويعات {#variations}

### دع الـ queue تعمل

يضبط ملف `phpunit.xml` في Laravel القيمة `QUEUE_CONNECTION=sync`، فدون `Queue::fake()` تعمل الـ job المسماة `SaveDocument` فوراً، وتحت `Doc::fake()` يُسجَّل حفظها مثل أي حفظ آخر:

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

ودون `Doc::fake()`، ومع `Storage::fake('s3')`، يُنشئ الـ worker ملف PDF حقيقياً على الـ disk الوهمي:

```php
Storage::fake('s3');
Mail::fake();

$this->actingAs(User::factory()->create())
    ->post(route('invoices.send', $this->invoice()));

Storage::disk('s3')->assertExists('invoices/INV-1024.pdf');
$this->assertStringStartsWith('%PDF', Storage::disk('s3')->get('invoices/INV-1024.pdf'));
```

### التحقق من ملف Word

ملف `.docx` هو ملف ZIP، والنص فيه داخل `word/document.xml`:

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

يدل `<w:bidi/>` على الفقرات التي اتجاهها من اليمين إلى اليسار. وابحث عن أجزاء قصيرة كاسم أو رقم، لأن Word قد يقسم الجملة الواحدة إلى عدة أجزاء (runs).

### المسودات وكلمات المرور والأرقام والمحركات

يحتفظ الملف المسجل بالإعدادات التي اختارها الكود:

```php
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->watermark === 'مسودة');
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->protected);
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->numerals === 'arabic' && $doc->direction === 'rtl' && $doc->driver === 'chromium');
Doc::assertNotGenerated(fn (GeneratedDocument $doc) => $doc->isWord());
```

ومع الأرقام العربية يجب أن يبحث `contains()` عن الأرقام كما تُطبع: `$doc->contains('INV-٢٠٢٦-١٠٢٤')`.

## صفحات ذات صلة {#related}

- [اختبار تطبيقك](/ar/guide/testing): `Doc::fake()` وكل دوال التحقق بالتفصيل.
- [الإخراج والتسليم](/ar/guide/output): التنزيل والعرض في المتصفح والحفظ ومرفقات البريد وملفات ZIP والإنشاء عبر الـ queue.
- [القوالب الجاهزة](/ar/guide/templates): بيانات القوالب والتحقق منها والبيانات التجريبية.
- [قوالبك الخاصة](/ar/guide/custom-templates): نسخ قالب إلى مشروعك.
- [ملفات Word](/ar/guide/word): ما يحتويه ملف Word.
- [تقرير مبيعات من قاعدة البيانات](/ar/recipes/sales-report): الـ job التي تختبرها الخطوة 4.
