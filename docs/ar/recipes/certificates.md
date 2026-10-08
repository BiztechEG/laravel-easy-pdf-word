# شهادات دورة تدريبية دفعة واحدة

عند انتهاء دورة تدريبية، أصدر شهادة لكل مشارك اجتازها: صياغة عربية تتبع جنس كل شخص، ورمز QR يفتح صفحة تحقق في تطبيقك، وكل ملف PDF يحفظه queue worker على التخزين ثم يُرسل بالبريد إلى صاحبه.

## السيناريو {#situation}

أنهت أكاديمية بيزتك للتدريب لتوها دورة مدتها 40 ساعة بعنوان «تطوير تطبيقات الويب باستخدام Laravel». وفي صفحة الدورة في لوحة التحكم زر **إصدار الشهادات**. يجب أن يؤدي الضغط عليه إلى:

- منح كل مشارك اجتاز الدورة شهادة إتمام مرقّمة مصاغة له: «لإتمامه بنجاح» للرجل، و«لإتمامها بنجاح» للمرأة،
- طباعة رمز QR على كل شهادة يمسحه أصحاب العمل للتأكد من صحتها،
- حفظ كل شهادة على التخزين لتنزيلها مرة أخرى بعد سنوات،
- إرسال شهادة كل مشارك إليه بالبريد،
- العودة فوراً، حتى لو كان في الدورة مائة مشارك.

## الحل {#solution}

### 1. الـ models

```php
// app/Models/Participant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// columns: id, course_id, name, email, gender ("male" or "female"), grade, passed,
//          certificate_number, certificate_code, certificate_issued_at
class Participant extends Model
{
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'certificate_issued_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificatePath(): string
    {
        return "certificates/{$this->course_id}/{$this->certificate_number}.pdf";
    }
}
```

لدى `Course` الحقول `title` و`starts_on` و`ends_on` (بتحويل `date`) و`hours` و`trainer_name`، وعلاقة `participants()`. يُطبع رقم الشهادة على الشهادة، أما `certificate_code` فنص عشوائي طويل لا يُستخدم إلا في رابط التحقق، فلا يستطيع أحد تخمين شهادات الآخرين بعدّ الأرقام.

### 2. كلاس واحد يبني الشهادة

```php
// app/Documents/CertificateDocument.php
namespace App\Documents;

use App\Models\Participant;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class CertificateDocument
{
    public static function for(Participant $participant): PendingDocument
    {
        $course = $participant->course;

        return Doc::template('certificate', [
            'type' => 'completion',
            'gender' => $participant->gender,              // "male" or "female"
            'number' => $participant->certificate_number,
            'date' => $participant->certificate_issued_at,
            'recipient' => $participant->name,
            'course' => $course->title,
            'from' => $course->starts_on,
            'to' => $course->ends_on,
            'hours' => $course->hours,
            'grade' => $participant->grade,
            'signatures' => [
                ['name' => $course->trainer_name, 'title' => 'المدرب'],
                ['name' => 'عمرو محمد', 'title' => 'مدير الأكاديمية'],
            ],
            'verify_url' => route('certificates.verify', $participant->certificate_code),
        ])->locale('ar');
    }
}
```

يتولى [قالب الشهادة](/ar/templates/certificate) القواعد العربية عنك:

- يختار `gender` الصياغة: «تُمنح هذه الشهادة إلى ... لإتمامها بنجاح» مع `female`، و«لإتمامه بنجاح» مع `male`. وإذا كان تطبيقك يخزن الجنس بطريقة أخرى فحوّله هنا، مثلاً `$participant->sex === 'f' ? 'female' : 'male'`.
- تُعدّ `hours` على الطريقة العربية: ساعة تدريبية واحدة، ساعتين تدريبيتين، 6 ساعات تدريبية، 40 ساعة تدريبية.
- يُطبع `from` و`to` بصيغة «خلال الفترة من ... إلى ...»، ومع أحدهما فقط بصيغة «بتاريخ ...».
- يُرسم `verify_url` رمز QR في زاوية الشهادة، وتحته عبارة «امسح الرمز للتحقق من الشهادة».
- يأتي اسم الأكاديمية أعلى الشهادة من `issuer`، أو افتراضياً من اسم الشركة في الهوية (`theme.company.name` في إعدادات الحزمة).

<div class="preview">
  <figure><a href="/images/recipes-a/certificate.png" target="_blank"><img src="/images/recipes-a/certificate.png" alt="شهادة إتمام عربية باسم سارة محمود عبد الله فيها اسم الدورة والتواريخ والساعات والتقدير وتوقيعان ورمز QR للتحقق"></a><figcaption>شهادة مشاركة اجتازت الدورة</figcaption></figure>
</div>

### 3. الإصدار والحفظ والإرسال عبر الـ queue

```php
// app/Http/Controllers/IssueCertificatesController.php
namespace App\Http\Controllers;

use App\Documents\CertificateDocument;
use App\Jobs\SendCertificate;
use App\Models\Course;
use Illuminate\Support\Str;

class IssueCertificatesController extends Controller
{
    public function __invoke(Course $course)
    {
        $participants = $course->participants()
            ->where('passed', true)
            ->whereNull('certificate_issued_at')
            ->get();

        foreach ($participants as $participant) {
            $participant->update([
                'certificate_number' => sprintf('CRT-%d-%05d', now()->year, $participant->id),
                'certificate_code' => Str::random(32),
                'certificate_issued_at' => now(),
            ]);

            CertificateDocument::for($participant)
                ->queue($participant->certificatePath(), 's3')
                ->chain([new SendCertificate($participant)]);
        }

        return back()->with('status', 'جارٍ إصدار الشهادات وإرسالها إلى المشاركين.');
    }
}
```

لكل مشارك ترسل `->queue()` مهمة تولّد ملف PDF وتحفظه في `certificates/{course}/CRT-2026-00001.pdf` على S3. وتضيف `->chain()` مهمة ثانية لا تعمل إلا بعد حفظ الملف؛ فإذا فشل الحفظ لا تخرج رسالة بمرفق مفقود. ويُتخطى المشاركون الذين صدرت لهم شهادة من قبل، فالضغط على الزر مرتين لا يرسل شيئاً مرتين.

وترسل المهمة الثانية الرسالة:

```php
// app/Jobs/SendCertificate.php
namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\Participant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCertificate implements ShouldQueue
{
    use Queueable;

    public function __construct(public Participant $participant) {}

    public function handle(): void
    {
        Mail::to($this->participant)->send(new CertificateMail($this->participant));
    }
}
```

ويرفق الـ Mailable الملف المحفوظ، فيتلقى المشارك الشهادة نفسها المحفوظة على التخزين:

```php
// app/Mail/CertificateMail.php
namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "شهادة دورة {$this->participant->course->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.certificates.issued');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('s3', $this->participant->certificatePath())
                ->as("شهادة-{$this->participant->certificate_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

```blade
{{-- resources/views/mail/certificates/issued.blade.php --}}
<x-mail::message>
# تهانينا {{ $participant->name }}

يسعدنا أن نرسل إليك شهادة دورة «{{ $participant->course->title }}»، وتجدها مرفقة بهذه الرسالة.

يمكن لأي جهة التحقق من الشهادة بمسح رمز QR المطبوع عليها.

مع التحية،<br>
{{ config('app.name') }}
</x-mail::message>
```

تحمل كل مهمة ما تحتاجه فقط: بيانات الشهادة في الأولى (مشفّرة بمفتاح التطبيق)، ورقم المشارك في الثانية. والـ worker الذي يعمل على خادم آخر يحفظ على S3 الذي يقرأ منه خادم الويب أيضاً؛ أما disk من نوع `local` فيترك الملفات على جهاز الـ worker.

### 4. صفحة التحقق

```php
// routes/web.php
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\IssueCertificatesController;

Route::post('/courses/{course}/certificates', IssueCertificatesController::class)
    ->middleware('auth')->name('courses.certificates');

Route::get('/certificates/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/certificates/{code}/download', [CertificateController::class, 'download'])->name('certificates.download');
```

```php
// app/Http/Controllers/CertificateController.php
namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /** The page the QR code opens. */
    public function verify(string $code)
    {
        $participant = Participant::with('course')->where('certificate_code', $code)->firstOrFail();

        return view('certificates.verify', ['participant' => $participant]);
    }

    /** A copy of the stored certificate, linked from the verification page. */
    public function download(string $code)
    {
        $participant = Participant::where('certificate_code', $code)->firstOrFail();

        return Storage::disk('s3')->download($participant->certificatePath(), "شهادة-{$participant->certificate_number}.pdf");
    }
}
```

```blade
{{-- resources/views/certificates/verify.blade.php --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>التحقق من شهادة</title>
</head>
<body>
    <h1>شهادة صحيحة</h1>
    <p>رقم الشهادة: {{ $participant->certificate_number }}</p>
    <p>
        صدرت إلى <strong>{{ $participant->name }}</strong>
        عن دورة «{{ $participant->course->title }}»
        بتاريخ {{ $participant->certificate_issued_at->format('Y/m/d') }}.
    </p>
    <a href="{{ route('certificates.download', $participant->certificate_code) }}">تنزيل نسخة من الشهادة</a>
</body>
</html>
```

روابط التحقق عامة عن قصد: من يمسح الرمز ليس عادة مستخدماً في تطبيقك. والرمز غير المعروف يعيد 404. وفي تطبيقك سيرث هذا الـ view القالب العام لصفحاتك.

::: warning تنبيه: استخدم نطاقك العام في الرابط
تكتب `route()` رابطاً كاملاً من نطاق الطلب الحالي، أو من `APP_URL` على queue worker أو في أمر Artisan. والرابط ثابت داخل ملف PDF لسنوات، فأصدر الشهادات من نطاقك العام (لا من `localhost` ولا من خادم تجريبي)، وأبقِ مسار الـ route ثابتاً.
:::

## تنويعات {#variations}

### شهادات بالإنجليزية

للمشاركين الذين يفضلون الإنجليزية، غيّر اللغة. في القالب صياغة إنجليزية لكل الأنواع:

```php
CertificateDocument::for($participant)->locale('en')->pdf();
```

خزّن اللغة المفضلة مع المشارك واستدعِ `->locale($participant->locale)` في `CertificateDocument`. ومسميات التوقيعات في ذلك الكلاس نصوص خاصة بك، فترجمها أيضاً، مثلاً باستخدام `__()`.

### ورشة عمل ليوم واحد بشهادة حضور

استخدم النوع `attendance` (شهادة حضور ... لحضوره / لحضورها) وأعطِ تاريخاً واحداً:

```php
CertificateDocument::for($participant)
    ->with(['type' => 'attendance', 'from' => '2026-10-15', 'to' => null, 'hours' => 6, 'grade' => null])
    ->pdf();
```

تستبدل `->with()` المفاتيح المعطاة من البيانات، فيبقى باقي الكلاس كما هو. يُطبع هنا «بتاريخ 2026/10/15» و«بعدد 6 ساعات تدريبية». والنوعان الآخران هما `participation` و`appreciation` (شهادة شكر وتقدير).

### نسخة Word للتعديل اليدوي

يُخرج قالب الشهادة ملف Word أيضاً، للشهادة النادرة التي يريد أحد تعديلها يدوياً (يحتاج إلى `phpoffice/phpword`):

```php
return CertificateDocument::for($participant)->word("شهادة-{$participant->certificate_number}.docx")->download();
```

### إعادة الإصدار بعد تصحيح الاسم

إذا كُتب اسم مشارك خطأً، فصحّحه وضع شهادته في الـ queue مرة أخرى على المسار نفسه. يحل الملف الجديد محل القديم، ويبقى رابط QR كما هو لأن الرمز لا يتغير:

```php
$participant->update(['name' => 'سارة محمود عبد الله']);

CertificateDocument::for($participant)
    ->queue($participant->certificatePath(), 's3')
    ->chain([new SendCertificate($participant)]);
```

## اختبار الحل {#testing}

باستخدام `Queue::fake()` تستطيع فحص ما يضعه الزر في الـ queue دون توليد أي ملف:

```php
use App\Jobs\SendCertificate;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use Illuminate\Support\Facades\Queue;

Queue::fake();

$this->actingAs($admin)->post("/courses/{$course->id}/certificates");

Queue::assertPushedWithChain(SaveDocument::class, [SendCertificate::class]);
Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->path === 'certificates/1/CRT-2026-00001.pdf' && $job->disk === 's3');
```

المزيد في [اختبار ميزات المستندات](/ar/recipes/testing-documents).

## صفحات ذات صلة {#related}

- [شهادة](/ar/templates/certificate): كل الحقول والأنواع الأربعة والصياغة.
- [الإخراج والتسليم](/ar/guide/output): الحفظ عبر الـ queue و`->onQueue()` و`->chain()`.
- [دعم اللغة العربية](/ar/guide/arabic): كيف تُكتب الأعداد والتواريخ بالعربية.
- [إرسال فاتورة بالبريد](/ar/recipes/email-invoice): إرفاق ملف PDF مولَّد للتو بدلاً من ملف محفوظ.
