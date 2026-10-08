# اختبار تطبيقك

اختبر الكود الذي ينشئ المستندات دون إنشائها فعليًا: يسجّل `Doc::fake()` كل ملف PDF وWord يطلبه الكود، وتفحص دوال التحقق ما أُنشئ، وبأي بيانات، وما الذي فُعل به.

## محاكاة المستندات {#fake}

يعمل `Doc::fake()` مثل `Mail::fake()`. استدعه في بداية الاختبار:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::fake();
```

ومن تلك اللحظة:

- تظل القوالب تأخذ قيمها الافتراضية وتشغّل `prepare()` وتخضع للتحقق من البيانات، فترمي البيانات غير الصحيحة `ValidationException` كما في بيئة الإنتاج.
- لا يعمل أي محرك PDF ولا تُستدعى PhpWord. ومحتوى الملف عنصر نائب صغير (`%PDF-1.4 ...` أو `PK ...`).
- لا يكتب `save()` شيئًا، ويعيد `download()` و`stream()` استجابة فيها العنصر النائب.
- يُسجَّل كل ملف في كائن `GeneratedDocument`، وهو ما تفحصه دوال التحقق التالية.

والأصناف التي تُنشأ بعد `Doc::fake()` ويُحقن فيها `BiztechEG\EasyPdfWord\DocFactory` (الصنف الذي تقوم عليه الـ facade) تحصل على المحاكاة أيضًا. وتدوم المحاكاة لاختبار واحد؛ ويبدأ الاختبار التالي بـ `Doc` الحقيقي من جديد.

## دوال التحقق {#assertions}

| الدالة | تنجح حين |
| --- | --- |
| `Doc::assertGenerated(?Closure $callback = null)` | أُنشئ ملف واحد على الأقل، ويطابق الـ callback إن وُجد |
| `Doc::assertNotGenerated(Closure $callback)` | لا يطابق أي ملف الـ callback |
| `Doc::assertGeneratedCount(int $count)` | أُنشئ هذا العدد من الملفات بالضبط |
| `Doc::assertNothingGenerated()` | لم يُنشأ أي ملف |
| `Doc::assertSaved(string\|Closure $path, ?string $disk = null)` | حُفظ ملف في هذا المسار (على هذا الـ disk إن حُدد)، أو طابق ملف محفوظ الـ callback |
| `Doc::assertDownloaded(string\|Closure\|null $filename = null)` | أُرسل ملف للتنزيل، بهذا الاسم أو مطابقًا للـ callback |
| `Doc::assertStreamed(string\|Closure\|null $filename = null)` | عُرض ملف في المتصفح بـ `stream()` أو أُعيد من controller، بهذا الاسم أو مطابقًا للـ callback |

يُعد الملف منشأً حين يستدعي الكود `->pdf()` أو `->word()`. أما بناء مستند دون طلب ملف فلا يُعد. وتتسلم الـ callbacks كائن `GeneratedDocument` وتعيد `true` عند التطابق.

تعيد `Doc::generated()` كل كائنات `GeneratedDocument` المسجلة، وتعيد `Doc::generated($callback)` المطابقة منها، لفحوصك الخاصة.

## المستند المسجل {#generated-document}

يصف `BiztechEG\EasyPdfWord\Testing\GeneratedDocument` ملفًا واحدًا:

| الخاصية أو الدالة | القيمة |
| --- | --- |
| `format` | `'pdf'` أو `'word'` |
| `isPdf()` و`isWord()` | المعلومة نفسها بقيم منطقية |
| `template` | اسم القالب، أو `null` للـ views وHTML و`Doc::make()` |
| `view` | اسم الـ view مع `Doc::view()`، وإلا `null` |
| `locale` | لغة المستند، مثل `'ar'` |
| `direction` | `'rtl'` أو `'ltr'` |
| `numerals` | `'arabic'` أو `'latin'` |
| `driver` | المحرك المطلوب بـ `->driver()`، أو `null` للمحرك الافتراضي |
| `watermark` | نص العلامة المائية لملف PDF، أو `null` |
| `protected` | `true` حين يكون لملف PDF كلمة مرور |
| `filename()` | اسم الملف مع امتداده، كما يأخذه التنزيل أو المرفق |
| `data(?string $key = null, mixed $default = null)` | البيانات التي أُنشئ منها الملف. وللقالب تكون بعد قيمه الافتراضية و`prepare()`، فتوجد فيها المجاميع المحسوبة. ويختار المفتاح بصيغة النقاط قيمة واحدة: `data('totals.total')`. |
| `html()` | الـ HTML الذي سيتسلمه محرك PDF. وليس لملفات Word HTML، فترمي `LogicException`. |
| `contains(string $text)` | هل يحتوي HTML ملف PDF النص، مهرّبًا كما يطبعه الـ view |
| `wasSaved(?string $path = null, ?string $disk = null)` | هل حُفظ الملف (في ذلك المكان إن حُدد) |
| `saves()` | كل عمليات الحفظ، بالصيغة `['path' => ..., 'disk' => ...]` |
| `wasDownloaded(?string $filename = null)` و`wasStreamed(?string $filename = null)` | هل أُرسل الملف للتنزيل أو عُرض في المتصفح |

مع `->numerals('arabic')` يحتوي الـ HTML أرقامًا عربية، فيحتاج `contains()` إلى الأرقام بالعربية أيضًا: `contains('٢٥٬٠٠٠')` أو `contains('٢٥,٠٠٠')` بحسب [الخط](/ar/guide/arabic#separators).

## اختبار كامل {#example}

الكود المراد اختباره:

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

الاختبار بأسلوب PHPUnit:

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

لا يلزم `Storage::fake('s3')` للحفظ، الذي يكتفي `Doc::fake()` بتسجيله، لكنه يمنع الاختبار من لمس bucket حقيقي إن كتب الكود ملفات أخرى.

### Pest {#pest}

يستدعي Pest الدوال الثابتة نفسها:

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

## ملفات ZIP {#zip}

يظل ملف ZIP من `Doc::zip()` يُبنى تحت `Doc::fake()`، من الملفات النائبة، ويُحفظ أو يُرسل كالمعتاد. فافحص الأرشيف نفسه، بـ `Storage::fake()` أو من الاستجابة. هنا يحفظ الـ route قسائم رواتب الشهر (`قسيمة-1001.pdf` و`قسيمة-1002.pdf`) في `payroll/2026-09.zip` على الـ disk المسمى `local`:

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

الملفات داخل الأرشيف تُعد منشأة، فتراها `assertGenerated()` و`assertGeneratedCount()`.

## الحفظ عبر الـ queue {#queue}

مع الـ queue المسمى `sync`، الذي تحدده معظم ملفات `phpunit.xml` (`QUEUE_CONNECTION=sync`)، يعمل الـ job فورًا ويسجّل `Doc::fake()` عملية الحفظ:

```php
Doc::fake();

Doc::template('invoice', $data)->locale('ar')->queue('invoices/INV-2026-1024.pdf', disk: 's3');

Doc::assertSaved('invoices/INV-2026-1024.pdf', disk: 's3');
Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->locale === 'ar' && $doc->filename() === 'INV-2026-1024.pdf');
```

أما تحت `Queue::fake()` فلا يعمل شيء، فافحص الـ job بدلًا من ذلك:

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

قيمة `$job->format` هي `'pdf'` أو `'word'`، ويحمل `$job->document['settings']` إعدادات المستند، مثل `locale`.

## مرفقات البريد {#mail}

تحت `Mail::fake()` يُسجَّل البريد دون أن يُبنى، فلا تُنشأ مرفقاته حتى تنظر إليها. افحصها داخل الـ callback الخاص بـ `assertQueued()` أو `assertSent()`:

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

`InvoiceIssued` هو الـ Mailable الذي يمر عبر الـ queue من صفحة [الإخراج والتسليم](/ar/guide/output#mail-attachments)، لذلك يُفحص بـ `assertQueued()`. واستخدم `assertSent()` بالطريقة نفسها لبريد لا يمر عبر الـ queue.

وتجد أمثلة أخرى، كاختبار العلامات المائية وكلمات المرور وملفات Word، في حالة الاستخدام [اختبار ميزات المستندات](/ar/recipes/testing-documents).
