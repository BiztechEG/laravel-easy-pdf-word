# الإخراج والتسليم

تشرح هذه الصفحة ما يمكنك فعله بالمستند بعد تجهيزه: تنزيله، أو عرضه في المتصفح، أو حفظه على disk، أو إرفاقه ببريد إلكتروني، أو جمع عدة ملفات في ملف ZIP واحد، أو إنشاؤه لاحقًا على queue worker.

## كائنات الملفات {#files}

يعيد `->pdf()` كائنًا من النوع `BiztechEG\EasyPdfWord\PdfDocument`، ويعيد `->word()` كائنًا من النوع `BiztechEG\EasyPdfWord\WordDocument`. ولهما الدوال نفسها، وكذلك ملف ZIP الناتج من `Doc::zip()`.

تبدأ أمثلة هذه الصفحة من هذه الفاتورة:

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

الاسم الذي تمرره إلى `->pdf()` أو `->word()` هو اسم الملف عند التنزيل وفي مرفقات البريد. وإن لم تمرر اسمًا، سُمّي الملف باسم القالب (`invoice.pdf` و`invoice.docx`)، أو `document.pdf` للـ views وHTML و`Doc::make()`. ويُضاف الامتداد إن لم يكن موجودًا، ويتحول `/` و`\` في الاسم إلى `-`.

## الدوال {#methods}

| الدالة | تعيد | ما تفعله |
| --- | --- | --- |
| `download(?string $filename = null)` | `Response` | ترسل الملف للتنزيل (`Content-Disposition: attachment`). |
| `stream(?string $filename = null)` | `Response` | تعرض الملف في المتصفح (`inline`). و`inline()` هي الدالة نفسها. |
| `save(string $path, ?string $disk = null)` | `string` | تكتب الملف على disk، أو في مسار مطلق إن لم تحدد disk. وتعيد المسار. |
| `content()` | `string` | محتوى الملف. و`toString()` مثلها. |
| `base64()` | `string` | المحتوى بترميز base64، لواجهة JSON مثلًا. |
| `filename()` | `string` | الاسم المستخدم في التنزيل والمرفقات، مع الامتداد. |
| `engine()` | `string` | من أنشأ الملف: `mpdf` أو `browsershot` (Chromium) أو `gotenberg` أو محركك الخاص لملفات PDF، و`phpword` أو `docx-template` لملفات Word، و`zip` للأرشيف. |
| `mimeType()` | `string` | `application/pdf`، أو نوع Word، أو `application/zip`. |
| `extension()` | `string` | `pdf` أو `docx` أو `zip`. |
| `toMailAttachment()` | `Attachment` | يستدعيها Laravel عند إرفاق الملف ببريد. |

الـ `Response` هنا من النوع `Symfony\Component\HttpFoundation\Response`، ويمكن لأي route في Laravel أن يعيده. والاسم الذي تمرره إلى `download()` أو `stream()` يخص تلك الاستجابة وحدها.

## التنزيل أو العرض في المتصفح {#download-and-stream}

```php
// نافذة الحفظ في المتصفح
return $pdf->download();

// الفتح في عارض PDF في المتصفح باسم آخر
return $pdf->stream('invoice-INV-2026-1024.pdf');

// ملفات Word بالطريقة نفسها
return $word->download();
```

لا مشكلة في أسماء الملفات العربية. تحمل الاستجابة الاسم بترميز UTF-8، ومعه نسخة ASCII للبرامج القديمة يتحول فيها كل حرف غير ASCII إلى شرطات سفلية.

## إعادة الملف من controller {#controllers}

ملف PDF أو Word يعيده route أو controller يُعرض في المتصفح، كأنك استدعيت `->stream()`:

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

وأعد `->pdf()->download()` بدلًا من ذلك إن أردت أن يحفظ المتصفح الملف. وتعرض حالة الاستخدام [تنزيل أو عرض أو حفظ](/ar/recipes/controller-responses) الخيارات الثلاثة جنبًا إلى جنب.

## الحفظ على disk أو في مسار {#save}

```php
// على disk من config/filesystems.php
$pdf->save('invoices/2026/INV-2026-1024.pdf', disk: 's3');

// على الـ disk الافتراضي (FILESYSTEM_DISK)
$pdf->save('invoices/INV-2026-1024.pdf');

// في مسار مطلق، وتُنشأ المجلدات الناقصة
$pdf->save(storage_path('app/exports/INV-2026-1024.pdf'));
```

المسار الذي يبدأ بـ `/` (أو `C:\` على Windows) يُكتب مباشرة في نظام الملفات إن لم تحدد disk. وأي مسار آخر يذهب إلى disk.

### عند فشل الحفظ {#save-failures}

الحفظ الذي لا يتم يرمي `RuntimeException`، فلا يبدو فشل الكتابة نجاحًا أبدًا:

```text
Could not write [invoices/INV-2026-1024.pdf] to the [s3] disk.
Could not create the folder [/var/www/exports/2026].
Could not write [/var/www/exports/2026/INV-2026-1024.pdf].
```

راجع بيانات اعتماد الـ disk والـ bucket، أو صلاحيات المجلد للمستخدم الذي يعمل به PHP. والـ disk الذي في إعداده `'throw' => true` في `config/filesystems.php` يرمي استثناء Flysystem نفسه بدلًا من ذلك، وهو يقول أكثر غالبًا.

## متى يُنشأ الملف {#when-rendering-happens}

لا يشغّل `->pdf()` و`->word()` المحرك. بل يأخذان نسخة من المستند، فلا تصل إلى الملف أي تغييرات لاحقة على `$document`، ويُنشأ الملف أول مرة يُحتاج فيها إلى محتواه:

- `content()` و`toString()` و`base64()` و`engine()` و`download()` و`stream()` و`save()`؛
- إعادة الملف من controller؛
- إرسال بريد مرفق به الملف؛
- بناء ملف ZIP يحتوي الملف.

ويُحتفظ بالمحتوى، فاستدعاء `download()` بعد `save()` لا ينشئ الملف مرتين.

تُفحص بيانات القالب وفق قواعده عند استدعاء `->pdf()`، فيرمي الخطأ `ValidationException` في ذلك السطر. أما مع `->word()` فيتم الفحص عند إنشاء الملف.

## رؤية HTML: toHtml() {#to-html}

تعيد `toHtml()` الـ HTML النهائي الذي يتسلمه محرك PDF، بعد تطبيق التخطيط والخطوط والأرقام. وهي أسرع طريقة لتتبع مشكلة في التخطيط:

```php
file_put_contents(storage_path('app/invoice-debug.html'), $document->toHtml());

// أو اعرضه في المتصفح
Route::get('/debug/invoice', fn () => response($document->toHtml()));
```

مع mPDF لا يحمل الـ HTML الخطوط، فيعرضه المتصفح بخطوطه هو. استدعِ `->driver('chromium')` أولًا لتحصل على HTML مضمّنة فيه الخطوط، كما يتسلمه Chromium. ويكتب الأمر `php artisan doc:sample invoice --format=html` الـ HTML نفسه لبيانات القالب التجريبية (انظر [أوامر Artisan](/ar/guide/commands)).

## إرفاق الملف ببريد {#mail-attachments}

يمكن إرفاق ملف PDF أو Word ببريد كما هو. في Mailable، أعده من `attachments()`:

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

وفي notification، مرّر الملف إلى `->attach()`:

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

يأخذ المرفق اسم الملف ونوع MIME الخاص به، ويُنشأ الملف عند بناء البريد.

### البريد في الـ queue {#queued-mail}

يُحوَّل الـ Mailable أو الـ notification الذي يمر عبر الـ queue إلى نص مسلسل (serialized). والملف الذي لم يُنشأ بعد لا يمكن تحويله، فأنشئه داخل `attachments()` أو `toMail()` كما في المثالين السابقين، ومرّر البيانات (أو model) إلى الـ constructor:

```php
// يعمل: ينشئ الـ worker ملف PDF عند إرسال البريد
Mail::to($customer)->send(new InvoiceIssued($data));

// يفشل حين يمر InvoiceFileMail عبر الـ queue ويأخذ الملف في الـ constructor:
// "Failed to serialize job of type [Illuminate\Mail\SendQueuedMailable]:
//  Serialization of 'Closure' is not allowed"
Mail::to($customer)->send(new InvoiceFileMail(Doc::template('invoice', $data)->pdf()));
```

وتشرح حالة الاستخدام [إرسال فاتورة بالبريد](/ar/recipes/email-invoice) مثالًا كاملًا.

## ملفات ZIP {#zip}

يجمع `Doc::zip()` عدة ملفات في أرشيف واحد. وللأرشيف دوال ملف PDF نفسها: `download()` و`stream()` و`save()` و`content()`، ويمكن إرفاقه ببريد.

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([
    $invoice->pdf('فاتورة-INV-2026-1024.pdf'),
    $invoice->word('فاتورة-INV-2026-1024.docx'),
    'سند-قبض-RV-2026-0315.pdf' => Doc::template('receipt', $receipt)->locale('ar')->pdf(),
], 'order-1024.zip')->download();
```

- يحتفظ كل ملف باسمه، إلا إن أعطاه مفتاح المصفوفة اسمًا آخر. ويُضاف الامتداد إن أغفله المفتاح.
- الاسم المكرر يصبح `name (2).pdf`، ثم `name (3).pdf`.
- لا تحتوي الأسماء مجلدات أبدًا: يتحول `/` و`\` إلى `-`، فلا يمكن فك الأرشيف خارج مجلده.
- اسم الأرشيف الافتراضي `documents.zip`.
- يرمي `Doc::zip([])` الرسالة `A ZIP file needs at least one file.`، ويُرفض أي شيء غير ملف أنشأه `->pdf()` أو `->word()` أو `Doc::zip()`.

تحتاج ملفات ZIP إلى إضافة `zip` في PHP. وبدونها تظهر الرسالة `ZIP files need the PHP zip extension (ext-zip).` عند بناء الأرشيف. وتبني حالة الاستخدام [قسائم الرواتب الشهرية في ملف ZIP](/ar/recipes/payslips-zip) أرشيفًا لكل شهر.

## الإنشاء عبر الـ queue {#queue}

يمكن أن يتولى queue worker إنشاء الملف وحفظه بدلًا من الطلب نفسه:

```php
Doc::template('invoice', $data)->locale('ar')->queue('invoices/INV-2026-1024.pdf', disk: 's3');
```

الامتداد يحدد الصيغة: `.pdf` أو `.docx`. وأي امتداد آخر يرمي `InvalidArgumentException` فورًا. وتُفحص بيانات القالب قبل وضع أي شيء في الـ queue، فيظهر الخطأ في الطلب وليس في job فاشل. وكذلك الحال مع `.docx` لمستند ليس له تخطيط Word أو له كلمة مرور.

يعيد `->queue()` الكائن `PendingDispatch` من Laravel، فتعمل الخيارات المعتادة:

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

تعمل الـ jobs الموجودة في `->chain()` بعد حفظ الملف، فيستطيع `SendInvoiceToCustomer` قراءته من الـ disk.

### الـ job {#queue-job}

الـ job هو `BiztechEG\EasyPdfWord\Jobs\SaveDocument`. ويحمل كل ما يلزم لبناء المستند من جديد على الـ worker: القالب أو الـ view أو الـ HTML أو الكتل، والبيانات، وكل الإعدادات (اللغة، والأرقام، والهوية، والورق، والمحرك، والعلامة المائية، وكلمة المرور). وتُحفظ معه لغة الطلب، فيخرج المستند الذي لم تُستدعَ له `->locale()` بلغة الطلب لا بلغة الـ worker.

تُخزَّن بيانات القالب مصفوفات عادية: تتحول الـ models والـ collections إلى مصفوفات أولًا. أما بيانات الـ Blade views الخاصة بك فتُخزَّن كما هي، فلا يجوز أن تحتوي closures.

الـ worker على خادم آخر يحفظ على الـ disk المحلي لذلك الخادم، فاستخدم disk مشتركًا مثل `s3` حين يكون الـ workers وخوادم الويب أجهزة مختلفة.

### التشفير {#queue-encryption}

يطبّق `SaveDocument` الواجهة `ShouldBeEncrypted`، فيشفّر Laravel محتوى الـ job بمفتاح `APP_KEY`، لأنه يحمل بيانات المستند وأي كلمة مرور للـ PDF. ويحتاج الـ workers إلى `APP_KEY` نفسه الذي في التطبيق الذي وضع الـ job في الـ queue.

### حدود الحجم {#queue-size}

يحمل الـ job البيانات، فالتقرير الذي فيه آلاف الصفوف يصنع job كبيرًا. يقبل Amazon SQS رسائل حتى 1 MB، وBeanstalkd حتى 64 KB افتراضيًا. وليس لـ Redis ولا لمشغل قاعدة البيانات حد صغير، لكن المحتوى الكبير يبطئ الـ queue.

للتقارير الكبيرة، ضع في الـ queue job خاصًا بك يحمل فقط ما يلزم لتحميل البيانات، وابنِ المستند داخله:

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

### مهلة الـ worker {#queue-timeouts}

يوقف الـ worker أي job يعمل أطول من `--timeout` (60 ثانية افتراضيًا) ويبلّغ `... has timed out.`. وقد يحتاج job المستندات إلى مهلة المحرك (60 ثانية لـ Chromium وGotenberg) ثم إنشاء ثانٍ بواسطة [المحرك الاحتياطي](/ar/guide/engines#fallback)، فامنح queue المستندات وقتًا أطول:

```bash
php artisan queue:work redis --queue=documents --timeout=180
```

اجعل `retry_after` لذلك الاتصال في `config/queue.php` أكبر من المهلة (مثل `240`)، وإلا التقط worker آخر الـ job بينما الأول ما زال ينشئ الملف.

يشرح اختبار الحفظ عبر الـ queue في صفحة [اختبار تطبيقك](/ar/guide/testing#queue).
