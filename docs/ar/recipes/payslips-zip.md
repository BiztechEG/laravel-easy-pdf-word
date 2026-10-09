# قسائم الرواتب الشهرية في ملف ZIP

في نهاية الشهر، تنزّل إدارة الموارد البشرية قسائم رواتب كل الموظفين ملفات PDF داخل ملف ZIP واحد. وفي الشركات الأكبر يبني queue worker ملف ZIP أو يحفظ كل قسيمة على التخزين، فلا ينتظر أي طلب ويب.

## السيناريو {#situation}

تحسب شركة بيزتك للحلول البرمجية الرواتب داخل تطبيق Laravel الخاص بها: سجل `Payslip` واحد لكل موظف في كل شهر، فيه الاستحقاقات والاستقطاعات محسوبة. وفي يوم صرف الرواتب يريد مدير الموارد البشرية:

- كل قسائم الشهر ملفات PDF مسماة بأسماء الموظفين، في ملف ZIP واحد للطباعة والتوقيع والأرشفة،
- قسائم عربية فيها صافي الراتب كتابةً (التفقيط)،
- طريقة تبقى صالحة عندما تكبر الشركة من 40 موظفاً إلى 400.

## الحل {#solution}

### 1. الـ models

يحمل `Employee` بيانات الشخص (`code` و`name` و`job_title` و`department` و`national_id` و`hire_date` و`bank_name` و`bank_account`)، ويحمل `Payslip` شهراً واحداً:

```php
// app/Models/Payslip.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// columns: id, employee_id, period ("2026-09"), number, earnings (json), deductions (json),
//          working_days, present_days, leave_days, overtime_hours, paid_on
class Payslip extends Model
{
    protected function casts(): array
    {
        return ['earnings' => 'array', 'deductions' => 'array', 'paid_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

الحقلان `earnings` و`deductions` قائمتان من `['name' => ..., 'amount' => ...]`، مثل `[['name' => 'الراتب الأساسي', 'amount' => 18000], ['name' => 'بدل سكن', 'amount' => 3000]]`. ويحوّل `Employee` الحقل `hire_date` إلى `date`.

### 2. كلاس واحد يبني القسيمة

```php
// app/Documents/PayslipDocument.php
namespace App\Documents;

use App\Models\Payslip;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class PayslipDocument
{
    public static function for(Payslip $payslip): PendingDocument
    {
        $employee = $payslip->employee;

        return Doc::template('payslip', [
            'period' => $payslip->period,                 // "2026-09"
            'number' => $payslip->number,
            'currency' => 'EGP',
            'employee' => [
                'name' => $employee->name,
                'code' => $employee->code,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'national_id' => $employee->national_id,
                'hire_date' => $employee->hire_date,
                'bank' => $employee->bank_name,
                'bank_account' => $employee->bank_account,
            ],
            'earnings' => $payslip->earnings,             // [['name' => 'الراتب الأساسي', 'amount' => 18000], ...]
            'deductions' => $payslip->deductions,
            'attendance' => [
                'working_days' => $payslip->working_days,
                'present_days' => $payslip->present_days,
                'leave_days' => $payslip->leave_days,
                'overtime_hours' => $payslip->overtime_hours,
            ],
            'payment' => ['method' => 'bank', 'date' => $payslip->paid_on],
        ])->locale('ar');
    }

    /** "EMP-0142 - سارة محمود عبد الله.pdf" */
    public static function filename(Payslip $payslip): string
    {
        return "{$payslip->employee->code} - {$payslip->employee->name}.pdf";
    }
}
```

يجمع [قالب قسيمة الراتب](/ar/templates/payslip) الاستحقاقات والاستقطاعات، ويطبع صافي الراتب بالأرقام وكتابةً، ويأخذ اسم الشركة وعنوانها من الهوية في إعدادات الحزمة. ويجب أن يكون `period` شهراً مكتوباً بالشكل `2026-09`.

### 3. تنزيل ملف ZIP

```php
// routes/web.php
use App\Http\Controllers\PayslipZipController;

Route::middleware('auth')->group(function () {
    Route::get('/hr/payslips/{period}/zip', PayslipZipController::class)
        ->where('period', '[0-9]{4}-[0-9]{2}')
        ->can('manage-payroll');
});
```

```php
// app/Http/Controllers/PayslipZipController.php
namespace App\Http\Controllers;

use App\Documents\PayslipDocument;
use App\Models\Payslip;
use BiztechEG\EasyPdfWord\Facades\Doc;

class PayslipZipController extends Controller
{
    public function __invoke(string $period)
    {
        $payslips = Payslip::with('employee')->where('period', $period)->get();

        abort_if($payslips->isEmpty(), 404);

        $files = $payslips->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)->pdf(PayslipDocument::filename($payslip)));

        return Doc::zip($files->all(), "قسائم-الرواتب-{$period}.zip")->download();
    }
}
```

يحدد gate باسم `manage-payroll` (أو policy) من يحق له تنزيل الرواتب. ويُنزّل الرابط `/hr/payslips/2026-09/zip` عندها الملف `قسائم-الرواتب-2026-09.zip`، وفيه ملف لكل موظف:

```text
EMP-0141 - سارة محمود عبد الله.pdf
EMP-0142 - محمد أحمد علي.pdf
EMP-0143 - منى خالد حسن.pdf
...
```

كيف يعمل ذلك:

- تتحقق `->pdf()` من بيانات كل قسيمة بقواعد القالب وتجهّز الملف دون توليده، ثم يولّد `Doc::zip()` الملفات واحداً تلو الآخر أثناء كتابة الأرشيف.
- ولأن البيانات تُفحص أولاً، فإن قسيمة ينقصها اسم أو فيها مبلغ سالب توقف الطلب بخطأ تحقق يذكر الحقل، قبل توليد أي ملف PDF.
- يحتفظ كل ملف بالاسم المعطى لـ `->pdf()`. ولو تطابق كود موظفين واسماهما لحصلا على `name.pdf` و`name (2).pdf`، دون أن يضيع ملف. ولا يمكن أن تحتوي الأسماء على مجلدات، فيُفك الأرشيف دائماً في مجلد واحد.
- يعلّم الأرشيف الأسماء بأنها بترميز UTF-8، فتعرضها أدوات Windows وmacOS وLinux الحالية بالعربية.
- تحتاج ملفات ZIP إلى إضافة `zip` في PHP ‏(`ext-zip`).

## الذاكرة والوقت {#limits}

يُبنى ملف ZIP داخل عملية PHP واحدة، فهو محكوم بحدودها. هذه قياسات بمحرك mPDF على جهاز مطوّر (قد يكون خادمك أبطأ):

| عدد القسائم | الوقت | الذاكرة الإضافية | حجم ملف ZIP |
| --- | --- | --- | --- |
| 50 | 4.2 ثانية | نحو 30 MB | 1.4 MB |
| 200 | 16 ثانية | نحو 90 MB | 5.6 MB |

تستغرق القسيمة الواحدة نحو 0.08 ثانية وحجمها نحو 33 KB. ويزداد الوقت والذاكرة بزيادة عدد الموظفين، لأن كل ملفات PDF تبقى في الذاكرة حتى يُكتب الأرشيف. قارن ذلك بحدودك:

- قيمة `max_execution_time` في PHP لطلبات الويب (30 ثانية غالباً)، ومهلة خادم الويب أو الـ proxy (60 ثانية في إعداد nginx الافتراضي).
- قيمة `memory_limit` في PHP، وهي 128 MB افتراضياً، يستهلك Laravel نفسه جزءاً منها.

طلب الويب مريح لبضع عشرات من الموظفين. وبعد نحو مائة موظف ابنِ ملف ZIP على queue worker كما يلي. وعند تشغيل الـ worker أعطِ PHP ذاكرة أكبر، مثلاً `php -d memory_limit=512M artisan queue:work`؛ أما الخيار `--memory` الخاص بالـ worker فيحدد متى يعيد تشغيل نفسه فقط، ولا يرفع حد PHP.

## تنويعات {#variations}

### بناء ملف ZIP على queue worker

يضغط مدير الموارد البشرية زراً، فيعود الطلب فوراً، وتبني مهمة (job) الأرشيف وتحفظه وترسل إليه رابطاً بالبريد:

```php
// app/Jobs/BuildPayslipsZip.php
namespace App\Jobs;

use App\Documents\PayslipDocument;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\PayslipsReady;
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildPayslipsZip implements ShouldQueue
{
    use Queueable;

    /** Seconds; keep retry_after in config/queue.php above this. */
    public $timeout = 900;

    public $tries = 1;

    public function __construct(public string $period, public User $requestedBy) {}

    public function handle(): void
    {
        $path = "payslips/{$this->period}.zip";

        $files = Payslip::with('employee')->where('period', $this->period)->get()
            ->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)->pdf(PayslipDocument::filename($payslip)));

        Doc::zip($files->all(), "قسائم-الرواتب-{$this->period}.zip")->save($path, 's3');

        $this->requestedBy->notify(new PayslipsReady($this->period, $path));
    }
}
```

```php
// app/Notifications/PayslipsReady.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PayslipsReady extends Notification
{
    use Queueable;

    public function __construct(public string $period, public string $path) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("قسائم رواتب {$this->period} جاهزة")
            ->line('تم تجهيز قسائم الرواتب لكل الموظفين في ملف واحد.')
            ->action('تنزيل الملف', Storage::disk('s3')->temporaryUrl($this->path, now()->addDay()));
    }
}
```

```php
// app/Http/Controllers/QueuePayslipsZipController.php
namespace App\Http\Controllers;

use App\Jobs\BuildPayslipsZip;
use Illuminate\Http\Request;

class QueuePayslipsZipController extends Controller
{
    public function __invoke(Request $request, string $period)
    {
        BuildPayslipsZip::dispatch($period, $request->user());

        return back()->with('status', 'جارٍ تجهيز القسائم، وستصلك رسالة بالرابط عند الانتهاء.');
    }
}
```

اربطه بـ `Route::post('/hr/payslips/{period}/zip', QueuePayslipsZipController::class)` مع `where` و`can` نفسيهما في رابط التنزيل.

سبب هذه الإعدادات: يسمح `$timeout` للمهمة بالعمل 15 دقيقة. ويجب أن تكون قيمة `retry_after` في `config/queue.php` (‏90 ثانية افتراضياً) أكبر منها، وإلا سلّمت الـ queue المهمة نفسها إلى worker ثانٍ بينما الأول ما زال يبني الأرشيف. و`$tries = 1` لأن المهمة الفاشلة تستحق الفحص لا التكرار 15 دقيقة أخرى. ولا تحمل المهمة إلا الشهر ورقم المستخدم، فيبقى حجمها صغيراً مهما كان عدد الموظفين.

### ملف لكل موظف يحفظه الـ queue

إذا كان الموظفون ينزّلون قسائمهم بأنفسهم من صفحة خدمة ذاتية، فاحفظ كل قسيمة على التخزين بالحفظ عبر الـ queue الذي توفره الحزمة. تصبح كل قسيمة مهمة صغيرة، ويستطيع عدة workers توليدها بالتوازي:

```php
$period = '2026-09';

foreach (Payslip::with('employee')->where('period', $period)->lazyById() as $payslip) {
    PayslipDocument::for($payslip)
        ->queue("payslips/{$period}/{$payslip->employee->code}.pdf", 's3')
        ->onQueue('documents');
}
```

تقرأ `lazyById()` القسائم على دفعات، فتستهلك الحلقة ذاكرة قليلة. ويجري التحقق من البيانات قبل وضع كل مهمة في الـ queue، فيظهر الخطأ في هذه الحلقة لا على الـ worker. وتحمل المهمة بيانات القسيمة، ويشفّرها Laravel بمفتاح التطبيق. شغّل worker لهذه الـ queue: ‏`php artisan queue:work --queue=documents`.

ثم ترسل صفحة الموظف الملف المحفوظ:

```php
// app/Http/Controllers/MyPayslipController.php
namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MyPayslipController extends Controller
{
    public function __invoke(Request $request, string $period)
    {
        $payslip = Payslip::with('employee')
            ->where('employee_id', $request->user()->employee_id)
            ->where('period', $period)
            ->firstOrFail();

        return Storage::disk('s3')->download(
            "payslips/{$period}/{$payslip->employee->code}.pdf",
            "قسيمة-راتب-{$period}.pdf",
        );
    }
}
```

### حماية كل قسيمة بكلمة مرور

الرواتب سرية. اقفل كل ملف PDF بالرقم القومي للموظف، فهو يعرفه وزملاؤه لا يعرفونه عادة:

```php
$files = $payslips->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)
    ->password($payslip->employee->national_id)
    ->pdf(PayslipDocument::filename($payslip)));
```

تطلب برامج قراءة PDF كلمة المرور قبل فتح الملف. والتشفير RC4 بطول 128 بت، وهو أقوى ما يوفره mPDF: يمنع فتح الملف بالصدفة، لكنه لا يصمد أمام من يتعمد كسره. ولا تقبل ملفات Word كلمة مرور، وترفض `->word()` أي مستند عليه كلمة مرور.

## صفحات ذات صلة {#related}

- [قسيمة راتب](/ar/templates/payslip): كل حقول القالب.
- [الإخراج والتسليم](/ar/guide/output): ملفات ZIP والحفظ عبر الـ queue وخياراته.
- [محركات PDF](/ar/guide/engines): استهلاك mPDF وChromium للذاكرة في المستندات الكبيرة.
- [الإعدادات](/ar/guide/configuration): اسم الشركة وعنوانها في الهوية.
- [تقرير مبيعات من قاعدة البيانات](/ar/recipes/sales-report): مستند آخر مبني من صفوف كثيرة.
