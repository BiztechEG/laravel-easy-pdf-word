# عقود من بياناتك

أنشئ عقداً جاهزاً للتوقيع من قاعدة بياناتك: الأطراف والتواريخ والمبالغ من السجل، والبنود من مكتبة بنود يعدّلها فريقك القانوني دون نشر نسخة جديدة من التطبيق. والعقد نفسه يُعرض في المتصفح، ويُرسل بالبريد، ويُنزَّل ملف Word للمراجعة.

## السيناريو {#situation}

شركة لإدارة الأملاك في الرياض تؤجر شققاً نيابة عن ملّاكها. وكل إيجار جديد يحتاج إلى عقد إيجار وحدة سكنية:

- المؤجر والمستأجر، برقم الهوية أو السجل التجاري، من جدول `contacts`؛
- الوحدة وتاريخا البداية والنهاية والأجرة الشهرية (بالأرقام وكتابةً) والتأمين ويوم السداد من سجل الإيجار؛
- البنود المعتادة يكتبها الفريق القانوني، ويغيّر كلمة بين حين وآخر دون أن ينتظر مطوّراً؛
- لبعض العقود شروط خاصة بها، ويوقّع على كل عقد شاهدان.

يراجع الموظفون العقد في المتصفح قبل طباعته، ويراجع الفريق القانوني الحالات غير المعتادة في Word، ويُحفظ ملف PDF النهائي على S3 ويُرسل بالبريد إلى المستأجر.

## الحل {#solution}

### 1. الجداول

```php
// database/migrations/2026_10_01_000000_create_leases_table.php
Schema::create('leases', function (Blueprint $table) {
    $table->id();
    $table->string('number');                        // L-2026-0145
    $table->foreignId('unit_id')->constrained();
    $table->foreignId('landlord_id')->constrained('contacts');
    $table->foreignId('lessee_id')->constrained('contacts');
    $table->date('starts_on');
    $table->date('ends_on');
    $table->decimal('monthly_rent', 12, 2);
    $table->decimal('deposit', 12, 2);
    $table->unsignedTinyInteger('payment_day');
    $table->date('signed_on')->nullable();
    $table->text('special_terms')->nullable();
    $table->json('witnesses')->nullable();
    $table->string('contract_path')->nullable();     // the PDF on S3
    $table->timestamps();
});

Schema::create('contract_clauses', function (Blueprint $table) {
    $table->id();
    $table->string('contract_type');                 // residential-lease, office-lease ...
    $table->unsignedSmallInteger('position');
    $table->string('title');
    $table->text('body');
});
```

لجهة الاتصال (contact) الحقول `name` و`id_label` (مثل "سجل تجاري رقم" أو "هوية وطنية رقم") و`id_number` و`address` و`email`، وللشركات `representative` و`representative_title`. وللوحدة `name` و`building` و`address` و`city`.

نصوص البنود نص عادي فيه علامات يملؤها الكود، مثل:

```text
الأجرة الشهرية :rent ريال (:rent_words).
تُدفع الأجرة مقدماً في اليوم :payment_day من كل شهر ميلادي بالتحويل إلى حساب الطرف الأول.
```

كل سطر من البند يصبح فقرة مستقلة في العقد.

### 2. العقد مبنياً من سجل الإيجار

```php
// app/Models/Lease.php
namespace App\Models;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lease extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'signed_on' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
            'witnesses' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'landlord_id');
    }

    public function lessee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'lessee_id');
    }

    public function contractDocument(): PendingDocument
    {
        $this->loadMissing('unit', 'landlord', 'lessee');

        $values = [
            ':unit' => "{$this->unit->name} في {$this->unit->building}",
            ':address' => $this->unit->address,
            ':start' => $this->starts_on->format('Y/m/d'),
            ':end' => $this->ends_on->format('Y/m/d'),
            ':rent' => number_format((float) $this->monthly_rent, 2),
            ':rent_words' => tafqeet($this->monthly_rent, 'SAR', only: true),
            ':deposit' => number_format((float) $this->deposit, 2),
            ':payment_day' => $this->payment_day,
        ];

        $clauses = ContractClause::query()
            ->where('contract_type', 'residential-lease')
            ->orderBy('position')
            ->get()
            ->map(fn (ContractClause $clause) => [
                'title' => $clause->title,
                'text' => strtr($clause->body, $values),
            ]);

        if ($this->special_terms) {
            $clauses->push(['title' => 'شروط خاصة', 'text' => $this->special_terms]);
        }

        return Doc::template('contract', [
            'contract' => [
                'title' => 'عقد إيجار وحدة سكنية',
                'number' => $this->number,
                'date' => $this->signed_on ?? now(),
                'place' => $this->unit->city,
            ],
            'parties' => [
                $this->party($this->landlord, 'المؤجر'),
                $this->party($this->lessee, 'المستأجر'),
            ],
            'preamble' => "يملك الطرف الأول {$values[':unit']}، ويرغب الطرف الثاني في استئجارها للسكن، وقد عاينها المعاينة النافية للجهالة وقبلها بحالتها الراهنة.",
            'clauses' => $clauses,
            'copies' => 2,
            'witnesses' => $this->witnesses ?? [],
        ])->locale('ar');
    }

    private function party(Contact $contact, string $alias): array
    {
        return [
            'name' => $contact->name,
            'alias' => $alias,
            'id_label' => $contact->id_label,
            'id' => (string) $contact->id_number,
            'address' => $contact->address,
            'represented_by' => $contact->representative,
            'capacity' => $contact->representative_title,
        ];
    }

    public function contractFilename(string $extension = 'pdf'): string
    {
        return "عقد-إيجار-{$this->number}.{$extension}";
    }
}
```

ما يفعله القالب `contract` بهذه البيانات:

- **الأطراف** تُطبع بالترتيب "الطرف الأول" و"الطرف الثاني"، وتحت كل منهما `alias`، ومع الشركة ممثلها وصفته. ويحتاج القالب إلى طرفين على الأقل، ويجب أن يكون `id` نصاً، ولهذا التحويل: فرقم الهوية المحفوظ عدداً يفشل في التحقق من البيانات في القالب.
- **البنود** تُرقَّم تلقائياً: "البند الأول: محل العقد"، "البند الثاني: مدة العقد" وهكذا. وكل سطر في `text` يبدأ فقرة جديدة، والبند الذي ليس له `title` يأخذ رقمه فقط.
- **`strtr()`** يملأ كل العلامات في مرور واحد ويبدأ بأطولها، فلا تُقرأ `:rent_words` على أنها `:rent` ثم `_words`. وتعيد `tafqeet(4500, 'SAR', only: true)` النص "فقط أربعة آلاف وخمسمائة ريال لا غير".
- **التاريخ** يمكن أن يكون كائن Carbon، ويصبح سطر الافتتاح: "إنه في يوم الخميس الموافق 2026/10/08 تحرر هذا العقد في الرياض بين كل من:".
- **`copies`** (القيمة الافتراضية 2) يكتب جملة الختام عن عدد النسخ، يليها مربع توقيع لكل طرف وسطر لكل شاهد.
- تنتهي كل صفحة في ملف PDF بسطر يوقّع عليه كل طرف تلك الصفحة ("توقيع الطرف الأول: ....")، ثم عنوان العقد ورقمه و"صفحة 1 من 2". وإذا كان في الهوية شعار طُبع في أعلى الصفحة في المنتصف.

ولأن البنود صفوف في جدول، يعدّلها الفريق القانوني من شاشة إدارة، ويستخدم العقد التالي الصياغة الجديدة. أما العقود المرسلة فتحتفظ بنصها لأن ملف PDF محفوظ (الخطوة 3).

<div class="preview">
  <figure><img src="/images/recipes-b/lease-contract.png" alt="الصفحة الأولى من عقد إيجار سكني عربي: العنوان والرقم، وسطر الافتتاح بالتاريخ والمدينة، والطرفان، والتمهيد، والبنود المرقمة"><figcaption>الصفحة الأولى من العقد L-2026-0145</figcaption></figure>
</div>

### 3. الـ routes والـ controller

```php
// routes/web.php
use App\Http\Controllers\LeaseContractController;

Route::middleware('auth')->group(function () {
    Route::get('/leases/{lease}/contract', [LeaseContractController::class, 'show'])->name('leases.contract');
    Route::get('/leases/{lease}/contract.docx', [LeaseContractController::class, 'word'])->name('leases.contract.word');
    Route::post('/leases/{lease}/contract/send', [LeaseContractController::class, 'send'])->name('leases.contract.send');
});
```

```php
// app/Http/Controllers/LeaseContractController.php
namespace App\Http\Controllers;

use App\Mail\LeaseContractMail;
use App\Models\Lease;
use Illuminate\Support\Facades\Mail;

class LeaseContractController extends Controller
{
    /** Opens in the browser for a last check before printing. */
    public function show(Lease $lease)
    {
        return $lease->contractDocument()->pdf()->stream($lease->contractFilename());
    }

    public function word(Lease $lease)
    {
        return $lease->contractDocument()->word()->download($lease->contractFilename('docx'));
    }

    /** Keeps the final PDF on S3 and emails it to the lessee. */
    public function send(Lease $lease)
    {
        $path = $lease->contractDocument()->pdf()->save("leases/{$lease->number}/contract.pdf", 's3');
        $lease->update(['contract_path' => $path]);

        Mail::to($lease->lessee->email)->queue(new LeaseContractMail($lease));

        return back()->with('status', 'أُرسل العقد إلى المستأجر.');
    }
}
```

- `stream()` يعرض ملف PDF في تبويب المتصفح (`inline`)، و`download()` ينزّله (`attachment`). ويُرسل اسم الملف العربي `عقد-إيجار-L-2026-0145.pdf` بصيغة UTF-8 التي تقرؤها المتصفحات، مع اسم بديل بحروف ASCII للبرامج القديمة.
- `save()` يعيد المسار الذي كتب فيه، فيُحفظ مباشرة في `contract_path`.
- يُحفظ ملف PDF قبل وضع البريد في الـ queue، ثم يُرفق البريد الملف المحفوظ، وهو بالضبط ما يوقّعه المستأجر، حتى لو تغيّر بند قبل أن تُرسله الـ queue.

### 4. الـ Mailable

```php
// app/Mail/LeaseContractMail.php
namespace App\Mail;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaseContractMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lease $lease) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "عقد إيجار الوحدة رقم {$this->lease->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lease-contract');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('s3', $this->lease->contract_path)
                ->as($this->lease->contractFilename())
                ->withMime('application/pdf'),
        ];
    }
}
```

لا تحمل الـ job إلا رقم سجل الإيجار (`SerializesModels`)، ويقرأ الـ worker ملف PDF المحفوظ من S3، فيبقى حجم الـ job صغيراً.

## تنويعات {#variations}

### نسخة Word للفريق القانوني، ومسودة معلَّمة

عندما يتضمن عقد شروطاً خاصة غير معتادة، أرسل للفريق القانوني ملف Word قابلاً للتعديل وملف PDF عليه علامة مسودة، حتى لا يوقّع أحد المسودة خطأً. ويمكن إعادة الملف الناتج من `attachments()` كما هو:

```php
// app/Mail/LegalReviewMail.php
public function attachments(): array
{
    $contract = $this->lease->contractDocument();

    return [
        $contract->word($this->lease->contractFilename('docx')),
        $contract->watermark('مسودة')->pdf("عقد-إيجار-{$this->lease->number}-مسودة.pdf"),
    ];
}
```

ملف Word فيه الأطراف نفسها والبنود المرقمة ومربعات التوقيع، من اليمين إلى اليسار. وتُرسم العلامة المائية مائلة على كل صفحة من ملف PDF، أما ملفات Word فلا علامة مائية فيها.

### ثلاث نسخ، أو جملة ختام من صياغتك

إذا احتفظ مكتب عقاري بنسخة ثالثة فيجب أن تذكر جملة الختام ذلك. تستبدل `->data()` المفاتيح العليا من البيانات التي مررتها، فيبقى باقي العقد كما بناه `contractDocument()`:

```php
$lease->contractDocument()
    ->data([
        'copies' => 3,
        'closing' => 'حُرر هذا العقد من ثلاث نسخ، بيد كل طرف نسخة، وتُحفظ الثالثة لدى مكتب الوساطة العقارية.',
    ])
    ->pdf();
```

مع `copies` وحده تقول جملة القالب "ثلاث نسخ"، أما `closing` فيستبدل الجملة كلها.

### أنواع أخرى من العقود

إيجار مكتب أو عقد تقديم خدمات أو عقد عمل يستخدم القالب نفسه: غيّر `contract_type` في استعلام البنود، والعنوان، وصفة كل طرف (مثل "صاحب العمل" و"الموظف"). واجعل لكل نوع عقد دالة في نموذجه، لتملأ كل واحدة علاماتها.

## صفحات ذات صلة {#related}

- [عقد](/ar/templates/contract): كل حقول القالب `contract` مع نماذج.
- [دعم اللغة العربية](/ar/guide/arabic): `tafqeet()` والأرقام العربية والنص من اليمين إلى اليسار.
- [الإخراج والتسليم](/ar/guide/output): `stream()` و`download()` و`save()` ومرفقات البريد.
- [ملفات Word](/ar/guide/word): ما تحتويه نسخة Word من القالب.
- [إعدادات الصفحة](/ar/guide/page-settings): العلامة المائية وكلمة المرور.
- [إرسال فاتورة بالبريد](/ar/recipes/email-invoice): إرفاق مستند يُنشأ عند إرسال البريد.
