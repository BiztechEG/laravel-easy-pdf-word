# تقرير مبيعات من قاعدة البيانات

حوّل استعلام Eloquent إلى تقرير مبيعات شهري بصيغة PDF أو Word: أولاً بالقالب الجاهز `report`، ثم بـ `Doc::make()` عندما تريد تصميمك الخاص. ويتناول الجزء الأخير التقارير التي تضم آلاف الصفوف.

## السيناريو {#situation}

شركة تجارية في الرياض لها ثلاثة فروع، تصدر فواتيرها من تطبيق Laravel. وفي أول يوم عمل من كل شهر يطلب المدير المالي مبيعات الشهر السابق في ملف:

- كل فواتير الشهر بالتاريخ والعميل والفرع والمبلغ قبل الضريبة والضريبة والإجمالي، دون الفواتير الملغاة؛
- الإجماليات في الأسفل، وأربعة أرقام في الأعلى: إجمالي المبيعات وعدد الفواتير ومتوسط الفاتورة وأعلى فرع؛
- سبعة أعمدة، لذلك تكون الصفحات بالعرض؛
- ملف PDF من رابط في لوحة التحكم، ونسخة Word لمن يريد التعديل عليها.

الفواتير موجودة بالفعل في جدول `invoices`، وتُقرأ من خلال النموذج `Invoice`:

```php
// app/Models/Invoice.php (the parts this recipe uses)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

وفي الجدول أيضاً الأعمدة `number` و`branch` و`status` (`paid` و`cancelled` ...).

## الحل {#solution}

### 1. صنف واحد يبني التقرير

اجمع الاستعلام والمستند في صنف صغير، فيستطيع الـ controller والـ job في الـ queue والمهمة المجدولة أن تُنشئ التقرير نفسه.

```php
// app/Reports/MonthlySalesReport.php
namespace App\Reports;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class MonthlySalesReport
{
    public function __construct(private CarbonImmutable $month) {}

    public function document(): PendingDocument
    {
        $invoices = $this->invoices();

        $topBranch = $invoices->groupBy('branch')
            ->map(fn ($branchInvoices) => $branchInvoices->sum('total'))
            ->sortDesc()
            ->keys()
            ->first();

        return Doc::template('report', [
            'title' => 'تقرير المبيعات الشهري',
            'subtitle' => 'فواتير شهر '.$this->month->locale('ar')->translatedFormat('F Y'),
            'columns' => [
                ['key' => 'number', 'label' => 'رقم الفاتورة'],
                ['key' => 'date', 'label' => 'التاريخ', 'format' => 'date'],
                ['key' => 'customer', 'label' => 'العميل'],
                ['key' => 'branch', 'label' => 'الفرع'],
                ['key' => 'subtotal', 'label' => 'قبل الضريبة', 'format' => 'money'],
                ['key' => 'vat', 'label' => 'الضريبة', 'format' => 'money'],
                ['key' => 'total', 'label' => 'الإجمالي (ر.س)', 'format' => 'money'],
            ],
            'rows' => $invoices->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'date' => $invoice->issued_at,
                'customer' => $invoice->customer->name,
                'branch' => $invoice->branch,
                'subtotal' => $invoice->subtotal,
                'vat' => $invoice->vat,
                'total' => $invoice->total,
            ]),
            'sum' => ['subtotal', 'vat', 'total'],
            'summary' => [
                'إجمالي المبيعات' => number_format($invoices->sum('total'), 2).' ر.س',
                'عدد الفواتير' => $invoices->count(),
                'متوسط الفاتورة' => number_format($invoices->avg('total') ?? 0, 2).' ر.س',
                'أعلى فرع' => $topBranch ?? '-',
            ],
        ])
            ->locale('ar')
            ->landscape();
    }

    public function filename(string $extension = 'pdf'): string
    {
        return 'sales-'.$this->month->format('Y-m').'.'.$extension;
    }

    private function invoices(): Collection
    {
        return Invoice::query()
            ->with('customer:id,name')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$this->month->startOfMonth(), $this->month->endOfMonth()])
            ->orderBy('issued_at')
            ->get();
    }
}
```

لماذا كُتب بهذه الطريقة:

- **استعلام واحد بلا مشكلة N+1.** يحمّل `with('customer:id,name')` العملاء في استعلام إضافي واحد مهما بلغ عدد فواتير الشهر.
- **الصفوف مصفوفات بسيطة.** تحويل كل فاتورة إلى مصفوفة يحدد بدقة ما يظهر في التقرير. ويقبل القالب النماذج نفسها أيضاً (انظر [تنويعات](#variations)).
- **التنسيقات تتولى الأرقام.** يطبع `money` و`number` فواصل الآلاف مع عدد من الخانات العشرية يحدده `decimals` (القيمة الافتراضية 2) ويحاذيان العمود إلى نهاية السطر، ويطبع `date` التاريخ بصيغة `Y/m/d`. وتعطي تحويلات `decimal:2` نصوصاً مثل `"2437.00"` يقرؤها القالب أرقاماً.
- **`sum` يضيف صف الإجماليات** للمفاتيح المذكورة، و`summary` يضيف البطاقات فوق الجدول. تُطبع قيم البطاقات كما تمررها، فنسّقها بنفسك.
- **`->landscape()`** يجعل صفحة A4 في القالب بالعرض. يتكرر رأس الجدول في كل صفحة، ويطبع تذييل الصفحة في القالب العنوان و"صفحة 1 من 2".

يأتي اسم الشركة في أعلى اليمين من الهوية (`theme.company.name` في `config/easy-pdf-word.php`، وقيمته الافتراضية `APP_NAME`)، ويُضاف وقت إنشاء التقرير تلقائياً.

### 2. الـ route والـ controller

```php
// routes/web.php
use App\Http\Controllers\SalesReportController;

Route::get('/reports/sales', SalesReportController::class)
    ->middleware('auth')
    ->name('reports.sales');
```

```php
// app/Http/Controllers/SalesReportController.php
namespace App\Http\Controllers;

use App\Reports\MonthlySalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $report = new MonthlySalesReport(CarbonImmutable::parse($request->input('month').'-01'));

        return $request->query('format') === 'docx'
            ? $report->document()->word()->download($report->filename('docx'))
            : $report->document()->pdf()->download($report->filename());
    }
}
```

الرابط `/reports/sales?month=2026-09` ينزّل الملف `sales-2026-09.pdf`، وبإضافة `&format=docx` تُنزَّل نسخة Word التي يبنيها تصميم Word في القالب من البيانات نفسها. يُتحقق من الشهر قبل تشغيل الاستعلام، ويُبنى التاريخ من `"2026-09-01"`: فالدالة `createFromFormat('Y-m', ...)` تأخذ يوم اليوم الحالي، وفي يوم 31 تقفز إلى الشهر التالي.

<div class="preview">
  <figure><img src="/images/recipes-b/sales-report.png" alt="تقرير مبيعات عربي بالعرض: العنوان والشهر وأربع بطاقات ملخص، ثم جدول الفواتير بالرقم والتاريخ والعميل والفرع والمبلغ قبل الضريبة والضريبة والإجمالي"><figcaption>الصفحة الأولى من تقرير سبتمبر (PDF)</figcaption></figure>
</div>

## تحكم كامل مع Doc::make() {#builder}

يعطيك القالب جدولاً واحداً. فإذا أراد المدير الفواتير مجمّعة حسب الفرع، لكل مجموعة إجماليها، فابنِ المستند بالكود. أضف هذه الدالة إلى `MonthlySalesReport`:

```php
public function detailedDocument(): PendingDocument
{
    $invoices = $this->invoices();
    $money = fn ($amount) => number_format((float) $amount, 2);
    $card = fn (string $label, string $value) => [
        'lines' => [
            ['text' => $label, 'color' => '#6B7280', 'size' => 9],
            ['text' => $value, 'bold' => true, 'size' => 13, 'color' => '#0F766E'],
        ],
        'border' => '#E5E7EB',
    ];

    $doc = Doc::make()
        ->heading('تقرير المبيعات حسب الفرع')
        ->paragraph([
            ['text' => 'الفترة: ', 'bold' => true],
            $this->month->startOfMonth()->format('Y/m/d').' - '.$this->month->endOfMonth()->format('Y/m/d'),
        ])
        ->table([[
            $card('إجمالي المبيعات', $money($invoices->sum('total')).' ر.س'),
            $card('ضريبة القيمة المضافة', $money($invoices->sum('vat')).' ر.س'),
            $card('عدد الفواتير', (string) $invoices->count()),
        ]], ['borders' => false]);

    foreach ($invoices->groupBy('branch') as $branch => $branchInvoices) {
        $rows = [['رقم الفاتورة', 'التاريخ', 'العميل', 'قبل الضريبة', 'الضريبة', 'الإجمالي']];

        foreach ($branchInvoices as $invoice) {
            $rows[] = [
                ['text' => $invoice->number, 'ltr' => true],
                $invoice->issued_at->format('Y/m/d'),
                $invoice->customer->name,
                $money($invoice->subtotal),
                $money($invoice->vat),
                $money($invoice->total),
            ];
        }

        $rows[] = [
            ['text' => 'إجمالي الفرع', 'colspan' => 3],
            $money($branchInvoices->sum('subtotal')),
            $money($branchInvoices->sum('vat')),
            $money($branchInvoices->sum('total')),
        ];

        $doc->heading("فرع {$branch}", 2)->table($rows, [
            'header' => true,
            'footer' => true,
            'striped' => '#F9FAFB',
            'font_size' => 9.5,
            'columns' => [18, 13, 27, ['width' => 14, 'align' => 'end'], ['width' => 12, 'align' => 'end'], ['width' => 16, 'align' => 'end']],
        ]);
    }

    return $doc
        ->paragraph('أُعد هذا التقرير آلياً من نظام المبيعات.', ['color' => '#6B7280', 'size' => 9])
        ->title('تقرير المبيعات '.$this->month->format('Y-m'))
        ->locale('ar')
        ->landscape()
        ->margins(12)
        ->footer('<div style="text-align: center;">صفحة {page} من {pages}</div>');
}
```

ما يفعله كل جزء:

- الجدول قائمة من الصفوف، والصف قائمة من الخلايا. والخلية نص، أو مصفوفة فيها `text` أو `lines` مع التنسيقات (`bold` و`color` و`size` و`ltr` و`colspan` و`border`).
- `header` يجعل الصف الأول رأساً للجدول يتكرر في كل صفحة، و`footer` يجعل الصف الأخير عريضاً، و`striped` يظلل صفاً ويترك صفاً.
- `columns` يحدد العرض بالنسبة المئوية، ولأعمدة المبالغ `'align' => 'end'`، وهو الجانب الأيسر في المستند العربي.
- `'ltr' => true` يحفظ ترتيب `INV-2026-1004` داخل نص من اليمين إلى اليسار.
- يبقى العنوان في الصفحة نفسها مع الجدول الذي يليه، فلا يبقى عنوان فرع وحده في أسفل صفحة.
- `->footer()` يأخذ HTML فيه `{page}` و`{pages}`، وفي ملفات Word يتحولان إلى حقول أرقام صفحات حقيقية.

يعطيك `PendingDocument` نفسه الصيغتين: `$report->detailedDocument()->pdf()` و`$report->detailedDocument()->word()`.

<div class="preview">
  <figure><img src="/images/recipes-b/sales-report-builder.png" alt="تقرير عربي بالعرض مبني بالكود: ثلاث بطاقات ملخص، ثم جدول لكل فرع ينتهي بصف إجمالي الفرع بخط عريض"><figcaption>الشهر نفسه مجمّعاً حسب الفرع مع Doc::make()</figcaption></figure>
</div>

## التقارير الكبيرة {#large-reports}

قد يضم شهر واحد في متجر مزدحم آلاف الفواتير، وعندها تتغير ثلاثة أمور.

**الذاكرة.** يحتفظ mPDF بالجدول كاملاً في الذاكرة أثناء ترتيبه، نحو 85 كيلوبايت لكل صف. فتقرير من 1,000 صف يحتاج أكثر من الحد الافتراضي لـ PHP وهو `memory_limit` بقيمة 128 ميجابايت، ونفاد الذاكرة ينهي العملية دون أن يتدخل المحرك الاحتياطي. ارفع الحد للكود الذي يُنشئ التقرير، أو أنشئه بـ Chromium (`->driver('chromium')`) الذي يُنشئ 2,000 صف في نحو 55 ميجابايت. وانظر [محركات PDF](/ar/guide/engines) لإعداد Chromium.

**الوقت.** إنشاء آلاف الصفوف يستغرق أطول مما ينبغي لطلب ويب. اترك المهمة لـ worker في الـ queue وأخبر المستخدم عندما يجهز الملف.

**حجم الـ job.** تضع `->queue()` بيانات المستند داخل الـ job، وتقرير بآلاف الصفوف يعني حمولة كبيرة (يقبل SQS حتى 1 ميجابايت، وBeanstalkd يقبل 64 كيلوبايت افتراضياً). لذلك ضع في الـ queue مهمة خاصة بك لا تحمل إلا ما يلزم لإعادة تشغيل الاستعلام، وهو هنا الشهر والمستخدم الذي سيصله الإشعار:

```php
// app/Jobs/BuildSalesReport.php
namespace App\Jobs;

use App\Models\User;
use App\Notifications\SalesReportReady;
use App\Reports\MonthlySalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildSalesReport implements ShouldQueue
{
    use Queueable;

    // Longer than the PDF engine's timeout plus a render by the fallback engine.
    public int $timeout = 300;

    public function __construct(public string $month, public int $userId) {}

    public function handle(): void
    {
        // mPDF keeps the whole table in memory while it lays it out.
        ini_set('memory_limit', '1024M');

        $report = new MonthlySalesReport(CarbonImmutable::parse($this->month.'-01'));
        $path = 'reports/'.$report->filename();

        $report->document()->pdf()->save($path, 's3');

        User::findOrFail($this->userId)->notify(new SalesReportReady($path));
    }
}
```

ينفّذ الـ worker الاستعلام بنفسه، فلا تمر الصفوف عبر الـ queue. واجعل `retry_after` في `config/queue.php` أكبر من `$timeout` للـ job، حتى لا يبدأ تقرير بطيء مرتين.

أما الطلب فيكتفي بإرسال الـ job:

```php
// routes/web.php
use App\Jobs\BuildSalesReport;
use Illuminate\Http\Request;

Route::post('/reports/sales', function (Request $request) {
    $request->validate(['month' => ['required', 'date_format:Y-m']]);

    BuildSalesReport::dispatch($request->input('month'), $request->user()->id);

    return back()->with('status', 'نجهّز التقرير الآن، وسيصلك إشعار عندما يكتمل.');
})->middleware('auth');
```

ويرسل الإشعار رابطاً صالحاً ليوم واحد:

```php
// app/Notifications/SalesReportReady.php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SalesReportReady extends Notification
{
    public function __construct(public string $path) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تقرير المبيعات جاهز')
            ->line('اكتمل تقرير المبيعات الشهري، ويمكنك تنزيله خلال 24 ساعة.')
            ->action('تنزيل التقرير', Storage::disk('s3')->temporaryUrl($this->path, now()->addDay()));
    }
}
```

::: tip Chromium للتقارير الكبيرة
لاستخدام Chromium لهذا التقرير وحده، أضف `->driver('chromium')` بعد `->landscape()` في `document()`. وإذا فشل Chromium أنشأت الحزمة الملف بالمحرك الاحتياطي (mPDF افتراضياً) وسجّلت تحذيراً، فأبقِ رفع حد الذاكرة احتياطاً.
:::

## تنويعات {#variations}

### تمرير النماذج صفوفاً

يقبل القالب مجموعة (collection) من النماذج، والمفاتيح المنقوطة تقرأ العلاقات المحمّلة:

```php
'columns' => [
    ['key' => 'number', 'label' => 'رقم الفاتورة'],
    ['key' => 'customer.name', 'label' => 'العميل'],
    ['key' => 'total', 'label' => 'الإجمالي', 'format' => 'money'],
],
'rows' => $invoices,   // loaded with ->with('customer:id,name')
```

يتحول كل نموذج إلى ناتج `toArray()`، فتصل التواريخ نصوصاً كما يُخرجها النموذج، وتُستبعد الحقول المخفية. حوّل الصفوف بنفسك، كما في الحل، عندما تحتاج قيماً محسوبة أو تحكماً دقيقاً في التواريخ.

### الأرقام العربية، أو عناوين إنجليزية

أضف `->numerals('arabic')` لتُطبع `١١٢,٩٨٢.٩٠`. ومع `->locale('en')` تصبح الصفحة من اليسار إلى اليمين وتتحول كلمات القالب نفسه ("Total" و"Generated" و"Page 1 of 2") إلى الإنجليزية. أما عناوين الأعمدة والملخص فهي من بياناتك، فمرّرها بالإنجليزية أيضاً، بـ `__()` مثلاً.

### كل شهر تلقائياً

اجعل المجدول (scheduler) يضع تقرير الشهر الماضي في الـ queue أول كل شهر:

```php
// routes/console.php
use App\Jobs\BuildSalesReport;
use App\Models\User;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => BuildSalesReport::dispatch(
    now()->subMonth()->format('Y-m'),
    User::where('email', 'finance@alnoor.example')->value('id'),
))->monthlyOn(1, '06:00');
```

## صفحات ذات صلة {#related}

- [تقرير جدولي](/ar/templates/report): كل حقول القالب `report`.
- [بناء المستند بالكود](/ar/guide/builder): كل الكتل والخلايا والتنسيقات في `Doc::make()`.
- [إعدادات الصفحة](/ar/guide/page-settings): مقاس الورق والاتجاه والهوامش ورأس الصفحة وتذييلها.
- [محركات PDF](/ar/guide/engines): mPDF وChromium والمحرك الاحتياطي.
- [الإخراج والتسليم](/ar/guide/output): التنزيل والحفظ على الـ disks والإنشاء عبر الـ queue.
