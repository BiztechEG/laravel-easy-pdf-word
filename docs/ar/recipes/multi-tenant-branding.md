# هوية مختلفة لكل عميل

في تطبيقات SaaS تريد كل شركة عميلة (tenant) أن تحمل مستنداتها شعارها ولونها وبياناتها ولغتها وأرقامها. تحفظ حالة الاستخدام هذه تلك الإعدادات في سجل الشركة، وتطبّقها على أي قالب بدالة واحدة، وتوضح كيف تحصل شركة بعينها على نسختها الخاصة من قالب.

## السيناريو {#situation}

منصة فوترة تستضيف شركات كثيرة. كل شركة تصدر فواتيرها لعملائها من تطبيق Laravel نفسه، ويجب أن تبدو كل فاتورة صادرة من تلك الشركة. ومنها اثنتان:

| | بيزتك (القاهرة) | Gulf Star Trading Co. (الرياض) |
|---|---|---|
| الشعار واللون | أزرق `#1D4ED8` | كهرماني `#B45309` |
| اللغة | العربية، من اليمين إلى اليسار | الإنجليزية، من اليسار إلى اليمين |
| الأرقام | الأرقام العربية (١٢٣) | الأرقام اللاتينية (123) |
| العملة والضريبة | الجنيه المصري، 14% | الريال السعودي، 15% |
| إضافات | | رمز QR لهيئة الزكاة والضريبة والجمارك، وبياناتها البنكية في كل فاتورة |

يجب أن يكون الكود الذي يُنشئ الفاتورة واحداً للجميع، وألا تحتاج إضافة شركة جديدة إلى نشر نسخة جديدة من التطبيق.

## الحل {#solution}

### 1. احفظ الهوية في سجل الشركة

```php
// database/migrations/2026_10_01_000000_create_tenants_table.php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();             // biztech, gulf-star
    $table->string('legal_name');
    $table->string('logo_path')->nullable();      // a PNG or JPEG on the public disk
    $table->string('brand_color', 7)->nullable(); // #1D4ED8
    $table->string('address')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->string('tax_number')->nullable();
    $table->char('country', 2);                   // EG, SA ...
    $table->string('locale', 5)->default('ar');
    $table->string('numerals', 6)->default('latin');
    $table->char('currency', 3);
    $table->decimal('vat_rate', 5, 2);
    $table->timestamps();
});
```

### 2. دالة واحدة تطبّق الهوية على أي مستند

```php
// app/Models/Tenant.php
namespace App\Models;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    /** A document with this tenant's brand, language and digits. */
    public function document(string $template, array $data = []): PendingDocument
    {
        return Doc::template($this->templateName($template), $data)
            ->theme($this->documentTheme())
            ->locale($this->locale)
            ->numerals($this->numerals);
    }

    public function documentTheme(): array
    {
        return [
            'primary' => $this->brand_color,
            'logo' => $this->logo_path ? Storage::disk('public')->path($this->logo_path) : null,
            'company' => [
                'name' => $this->legal_name,
                'address' => $this->address,
                'phone' => $this->phone,
                'email' => $this->email,
                'tax_number' => $this->tax_number,
            ],
        ];
    }

    /** "invoice.gulf-star" when this tenant has its own copy of the template. */
    public function templateName(string $template): string
    {
        $own = "{$template}.{$this->slug}";

        return Doc::templates()->exists($own) ? $own : $template;
    }
}
```

كيف يعمل:

- **`->theme()`** يدمج المصفوفة فوق `theme` في `config/easy-pdf-word.php` مفتاحاً مفتاحاً. والقوالب الجاهزة تقرأ منها اللون والشعار وبيانات الشركة: فالفاتورة تطبع `company` بائعاً إذا لم تمرر `seller`، والعقد يطبع الشعار في أعلاه.
- **الألوان يُتحقق منها.** القيمة التي ليست لوناً (أو `null` عندما لم تختر الشركة لوناً) تعود إلى اللون الافتراضي `#0F766E`، فلا تفسد قيمة خاطئة الصفحة.
- **الشعار** مسار ملف. الملفات داخل `public/` و`storage/app` و`resources/` مسموح بها افتراضياً (`images.paths` في الإعدادات)، والـ disk المسمى `public` موجود داخل `storage/app/public`. استخدم PNG أو JPEG: فشعار SVG يظهر في ملفات PDF لكنه يُستبعد من ملفات Word.
- **`->locale()`** يغيّر الاتجاه وعبارات القالب نفسه (بالعربية "فاتورة ضريبية"، وبالإنجليزية "Tax Invoice"). و**`->numerals()`** يقبل `arabic` أو `latin`.
- **`templateName()`** يختار نسخة الشركة الخاصة من القالب إن وُجدت (الخطوة 4)، وإلا فالقالب المشترك.

يبقى كل إعداد استدعاءً عادياً لدالة، فيمكن لمستند واحد أن يغيّره: `$tenant->document('invoice', $data)->locale('en')`.

### 3. بيانات الفاتورة والـ controller

```php
// app/Models/Invoice.php (the parts this recipe uses)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected function casts(): array
    {
        return ['issued_at' => 'date', 'due_at' => 'date'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function toDocumentData(): array
    {
        return [
            'invoice' => [
                'number' => $this->number,
                'date' => $this->issued_at,
                'due_date' => $this->due_at,
                'currency' => $this->tenant->currency,
                'tax_rate' => $this->tenant->vat_rate,
            ],
            'buyer' => [
                'name' => $this->customer->name,
                'address' => $this->customer->address,
                'tax_number' => $this->customer->tax_number,
            ],
            'items' => $this->items->map->only(['description', 'quantity', 'unit_price']),
            'qr' => $this->tenant->country === 'SA' ? 'zatca' : null,
        ];
    }
}
```

لا يوجد مفتاح `seller`: فالبائع يأتي من هوية الشركة. و`qr => 'zatca'` يجعل قالب الفاتورة يبني رمز QR الخاص بهيئة الزكاة والضريبة والجمارك من البائع والرقم الضريبي والإجماليات، للشركات في السعودية فقط.

```php
// routes/web.php
use App\Http\Controllers\InvoicePdfController;

Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])
    ->middleware('auth')
    ->name('invoices.pdf');
```

```php
// app/Http/Controllers/InvoicePdfController.php
namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController extends Controller
{
    public function show(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);

        return $invoice->tenant
            ->document('invoice', $invoice->toDocumentData())
            ->pdf()
            ->download("{$invoice->number}.pdf");
    }
}
```

```php
// app/Policies/InvoicePolicy.php
namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->tenant_id === $invoice->tenant_id;
    }
}
```

أرقام الفواتير سهلة التخمين، لذلك تتأكد الـ policy أن المستخدم لا ينزّل إلا فواتير شركته، ومن عداه يحصل على 403.

### 4. شركة واحدة بقالبها الخاص {#own-template}

تريد Gulf Star بياناتها البنكية تحت الإجماليات. انسخ قالب الفاتورة باسم ينتهي بالمعرّف المختصر (slug) للشركة:

```bash
php artisan doc:template invoice --as=invoice.gulf-star
```

يُنسخ القالب إلى `resources/doc-templates/invoice.gulf-star/`. أضف المربع إلى `pdf.html.php` قبل الملاحظات مباشرة:

```php
<div style="margin-top: 5mm; border: 1px solid <?= $doc->e($border) ?>; padding: 3mm 4mm;">
    <strong style="color: <?= $doc->e($primary) ?>;">Bank details</strong><br>
    Al Rajhi Bank, Gulf Star Trading Co.<br>
    IBAN: <?= $doc->ltr('SA03 8000 0000 6080 1016 7519') ?>
</div>

<?php if (! empty($invoice['notes'])) { ?>
```

ملف Word يبنيه `word.php` في المجلد نفسه، فأضف المربع هناك أيضاً، قبل `if (! empty($invoice['notes'])) {`:

```php
    $word->spacer(3);
    $word->table([[[
        'lines' => [
            ['text' => 'Bank details', 'bold' => true, 'color' => $primary],
            ['text' => 'Al Rajhi Bank, Gulf Star Trading Co.'],
            ['text' => 'IBAN: SA03 8000 0000 6080 1016 7519', 'ltr' => true],
        ],
        'border' => $border,
    ]]], ['borders' => false, 'font_size' => 10]);
```

لا شيء آخر يتغير. فالدالة `templateName('invoice')` تعيد الآن `invoice.gulf-star` لـ Gulf Star و`invoice` لبقية الشركات، وتظل النسخة تأخذ ألوانها وشعارها من الهوية.

<div class="preview">
  <figure><img src="/images/recipes-b/tenant-biztech.png" alt="فاتورة ضريبية عربية باللون الأزرق مع شعار بيزتك والأرقام العربية والتفقيط"><figcaption>بيزتك: العربية والأرقام العربية واللون الأزرق</figcaption></figure>
  <figure><img src="/images/recipes-b/tenant-gulf-star.png" alt="فاتورة ضريبية إنجليزية باللون الكهرماني مع شعار Gulf Star ورمز QR لهيئة الزكاة ومربع البيانات البنكية"><figcaption>Gulf Star: الإنجليزية ورمز QR وقالبها الخاص</figcaption></figure>
</div>

الفاتورتان من الـ controller نفسه ومن `toDocumentData()` نفسها.

::: warning اختر القالب بالاسم لا بتغيير المسارات
قد يبدو مغرياً أن توجّه `easy-pdf-word.templates.paths` إلى مجلد خاص بالشركة أثناء الطلب. لا تفعل: فقائمة القوالب تُقرأ مرة واحدة في كل عملية، فيتجاهل الـ worker طويل التشغيل (Octane وworkers الـ queue) أي تغيير لاحق في `config()`، والمستند المرسل إلى الـ queue يُنشئه worker لا يعرف إلا اسم القالب. أما اسم مثل `invoice.gulf-star` فيعمل بالطريقة نفسها في كل مكان.
:::

لإبقاء نسخ الشركات منفصلة عن قوالبك، أضف مجلداً ثانياً إلى الإعدادات وانقل النسخ إليه:

```php
// config/easy-pdf-word.php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
        resource_path('doc-templates/tenants'),
    ],
],
```

يُبحث في المجلدات بالترتيب، وأول قالب بالاسم المطلوب هو المستخدم. ولأن `doc:template` ينسخ دائماً إلى المجلد الأول، انقل النسخة الجديدة إلى `tenants/` بعد ذلك.

## المستندات في الـ queue تحتفظ بالهوية {#queue}

أرشفة كل فاتورة على S3 تعمل أفضل عبر الـ queue:

```php
$tenant->document('invoice', $invoice->toDocumentData())
    ->queue("tenants/{$tenant->id}/invoices/{$invoice->number}.pdf", disk: 's3');
```

تحمل الـ job اسم القالب (`invoice.gulf-star`) والهوية واللغة والأرقام، فيُنشئ الـ worker الفاتورة نفسها التي ينزّلها المستخدم. أما الشعار فينتقل مساراً، فيجب أن يستطيع الـ worker قراءة ذلك الملف، وهذا متحقق إذا كان يعمل على الخادم نفسه. وإذا كانت الـ workers على خوادم أخرى فاحفظ الشعارات على S3 ومرّرها بيانات (القسم التالي).

ولأن الهوية جزء من كل مستند، فلا شيء يحتاج إلى إرجاعه بعد إنشاء المستند، ويستطيع worker واحد أن يُنشئ مستندات شركات كثيرة واحدة بعد الأخرى.

## تنويعات {#variations}

### الشعارات على S3

يجب أن يكون شعار الهوية ملفاً محلياً أو data URI أو رابطاً مسموحاً به. فإذا كانت الشعارات على S3 فاقرأ الملف ومرّره data URI:

```php
'logo' => $this->logo_path
    ? 'data:'.Storage::disk('s3')->mimeType($this->logo_path).';base64,'.base64_encode(Storage::disk('s3')->get($this->logo_path))
    : null,
```

اجعل الشعارات صغيرة (بضع عشرات من الكيلوبايت)، لأن الـ data URI يدخل في كل job في الـ queue.

### دع كل شركة تضبط هويتها

تأتي القيم من نموذج إعدادات. تحقق منها بحيث تعمل في ملفات PDF وWord معاً، فملفات Word تترك أسماء الألوان مثل `navy`، لذا اقبل الألوان بصيغة hex:

```php
// app/Http/Controllers/BrandingController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function update(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg', 'max:1024'],
            'locale' => ['required', 'in:ar,en'],
            'numerals' => ['required', 'in:arabic,latin'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store("logos/{$tenant->id}", 'public');
        }

        unset($validated['logo']);
        $tenant->update($validated);

        return back()->with('status', 'Branding saved.');
    }
}
```

### لغة العميل لا لغة الشركة

قد تصدر شركة سعودية فواتير بعض عملائها بالعربية وبعضهم بالإنجليزية. الاستدعاء الأخير هو الذي يسري، فغيّر اللغة بعد `document()`:

```php
$invoice->tenant
    ->document('invoice', $invoice->toDocumentData())
    ->locale($invoice->customer->preferred_locale ?? $invoice->tenant->locale)
    ->pdf();
```

### خط لكل شركة

أضف `->font('tajawal')` (أو `cairo` أو `naskh` أو خطاً سجّلته) بعد `document()`، أو احفظ عموداً `font` في سجل الشركة ومرّره هناك. انظر [الخطوط](/ar/guide/fonts).

### الهوية في قوالبك الخاصة

تحصل قوالبك الخاصة على الهوية نفسها. في صفحة PDF استخدم `$doc->theme('primary')` و`$doc->theme('company.name')`، وفي ملف `word.docx` المصمم في Word استخدم المتغيرين `${theme.company.name}` و`${theme.logo:150:60}`. وهكذا يطبّق `$tenant->document('price-offer', $data)` الهوية على قالب صممته بنفسك أيضاً.

## صفحات ذات صلة {#related}

- [الفاتورة الضريبية](/ar/templates/invoice): حقول الفاتورة والبائع ورمز QR لهيئة الزكاة والضريبة والجمارك.
- [قوالبك الخاصة](/ar/guide/custom-templates): نسخ القوالب وترتيب البحث في مجلدات القوالب.
- [الإعدادات](/ar/guide/configuration): إعدادات `theme` و`images` و`templates`.
- [الصور](/ar/guide/images): مسارات الشعار وصيغه المسموح بها.
- [دعم اللغة العربية](/ar/guide/arabic): الاتجاه والأرقام العربية والتفقيط.
- [الخطوط](/ar/guide/fonts): الخطوط المرفقة وإضافة خطوطك.
- [الإخراج والتسليم](/ar/guide/output): الإنشاء عبر الـ queue والحفظ على الـ disks.
- [قالب مصمم في Word](/ar/recipes/word-designed-template): متغيرات الهوية في قالب `.docx`.
