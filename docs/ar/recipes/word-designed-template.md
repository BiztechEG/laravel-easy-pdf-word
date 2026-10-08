# قالب مصمم في Word

اترك تصميم المستند لصاحب شكله يصممه في Microsoft Word باستخدام متغيرات مثل `${customer.name}`، ودع تطبيقك يملؤه بالبيانات. وتتضمن حالة الاستخدام هذه ملف بداية من اليمين إلى اليسار يمكنك تنزيله وتسليمه لذلك الشخص.

## السيناريو {#situation}

شركة في الرياض ترسل عروض أسعار إلى عملائها. ومديرة المبيعات تريد التحكم في شكلها: الترويسة والصياغة والجدول والخطوط. وهي تعمل في Microsoft Word لا في Blade، وتريد أن تغيّر التصميم الشهر القادم دون انتظار مطوّر.

يتضمن العرض:

- ترويسة الشركة مع شعارها؛
- رقم العرض والعميل والشخص المسؤول عن التواصل، والتاريخ الميلادي والهجري، وتاريخ انتهاء الصلاحية؛
- جدولاً بصف لكل صنف، ثم الإجماليات مع ضريبة القيمة المضافة 15% والتفقيط (المبلغ كتابةً)؛
- الملاحظات، واسم مندوب المبيعات وتوقيعه.

يحفظ التطبيق عروض الأسعار بالفعل: `Quotation` (`number` و`issued_at` و`valid_until` و`notes`) ولها `customer` و`salesperson` (مستخدم `User` له `job_title`) و`items` (`description` و`quantity` و`unit_price`).

## الحل {#solution}

### 1. ابدأ من ملف البداية

نزّل <a href="/files/recipes-b/word-template-starter.docx" download>word-template-starter.docx</a>: عرض سعر عربي بفقرات وجداول من اليمين إلى اليسار، وفيه كل المتغيرات التي تستخدمها هذه الحالة. سلّمه للمصممة نقطةَ بداية، أو استخدمه لترى كيف يُبنى القالب.

<div class="preview">
  <figure><img src="/images/recipes-b/word-template-starter.png" alt="ملف البداية كما يعرضه Word: عرض سعر عربي فيه متغيرات في الترويسة وبيانات العميل، وجدول فيه صف واحد من متغيرات الأصناف ثم الإجماليات، وخانة التوقيع"><figcaption>ملف البداية كما صُمم في Word</figcaption></figure>
  <figure><img src="/images/recipes-b/word-template-filled.png" alt="العرض نفسه بعد أن ملأه التطبيق: الشعار وبيانات الشركة والعميل والتاريخ الهجري وثلاثة أصناف والإجماليات مع الضريبة والتفقيط وصورة التوقيع"><figcaption>ملف Word الذي يرسله التطبيق</figcaption></figure>
</div>

### 2. المتغيرات

المتغير هو `${name}`، يُكتب نصاً عادياً في أي مكان من الملف: في فقرة أو خلية جدول أو رأس الصفحة أو تذييلها. وتُكتب البيانات المتداخلة بالنقاط.

| المتغير | يُملأ بـ |
| --- | --- |
| `${quote.number}`، `${customer.name}` | بياناتك، والمستويات المتداخلة بالنقاط |
| `${date}` | تاريخ العرض؛ كائن التاريخ يُطبع هكذا `2026/10/08` |
| `${doc.hijri_date}` | التاريخ الهجري للقيمة `date` في المستوى الأعلى (يتطلب `ext-intl`) |
| `${items.description}`، `${items.quantity}` ... في صف جدول واحد | يتكرر ذلك الصف لكل صنف |
| `${items.row_number}` | 1، 2، 3 ... في الصف المتكرر |
| `${totals.total}`، `${totals.in_words}` | قيم يحسبها `template.php` (الخطوة 3) |
| `${theme.company.name}`، `${theme.company.phone}` ... | بيانات الشركة من الهوية |
| `${theme.logo:150:60}`، `${signature:150:60}` | صورة بمقاس 150 × 60 بكسل |

ومتغيرات أخرى تملؤها الحزمة: `${doc.today}` (تاريخ اليوم)، و`${doc.qr}` (صورة رمز QR للقيمة `qr` في المستوى الأعلى)، و`${t.key}` (عبارة من ملفات `lang` في القالب، انظر [تنويعات](#variations)).

أخبر المصممة بهذه القواعد:

- **اكتبي كل متغير دفعة واحدة وبتنسيق واحد**، أو الصقيه نصاً عادياً. فالمتغير الذي يتغير تنسيقه في منتصفه يحفظه Word أجزاءً متفرقة، وتعيد الحزمة تجميع أغلبها، لكن راجعي القائمة قبل اعتماد الملف (انظر [راجع التصميم قبل اعتماده](#check)).
- **صف واحد لكل قائمة.** ضعي كل متغيرات `${items.*}` في صف جدول واحد. تنسخ الحزمة هذا الصف لكل صنف، وتبقى الصفوف التي تليه (الإجماليات هنا) كما هي.
- **الاتجاه من اليمين إلى اليسار يُضبط في Word.** حدّدي النص واستخدمي زر "اتجاه النص من اليمين إلى اليسار" (الصفحة الرئيسية > فقرة)، واجعلي اتجاه كل جدول من اليمين إلى اليسار في خصائص الجدول. ويحتفظ الملف بعد ملئه بهذه الإعدادات.
- **يعرض Word المتغيرات بشكل غريب في الفقرات العربية.** ففي الفقرة التي اتجاهها من اليمين إلى اليسار يظهر `${customer.name}` بهذا الشكل `{customer.name}$`، لكن الملف يحفظه `${customer.name}` ويُملأ بشكل صحيح.
- **متغير الصورة يكون وحده** في فقرته أو خليته، ومقاسه بالبكسل بعد النقطتين. أما الصورة التي لا تتغير، كشعار ثابت، فيمكن إدراجها صورةً عادية.
- **استخدمي خطوطاً موجودة لدى القرّاء.** لا يضمّن Word الخطوط في الملف، والخطوط Arial وTahoma وSakkal Majalla وSimplified Arabic آمنة للعربية.

### 3. مجلد القالب

ضع الملف في مجلد قالب باسم `word.docx`، بجوار `template.php` الذي يتحقق من البيانات ويحسب الإجماليات:

```text
resources/doc-templates/price-offer/
    word.docx       the file designed in Word
    template.php    fields (validation) and the totals
```

```php
<?php
// resources/doc-templates/price-offer/template.php

use BiztechEG\EasyPdfWord\Arabic\Arabic;

/*
| Price offer designed in Word by the sales team (word.docx in this folder).
| This file checks the data and adds the totals before Word is filled.
*/

return [
    'title' => 'Price offer',
    'description' => 'Price offer designed in Microsoft Word.',

    'fields' => [
        'quote.number' => ['required', 'string'],
        'quote.valid_until' => ['required', 'date'],
        'date' => ['required', 'date'],
        'customer.name' => ['required', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:1'],
        'items.*.unit_price' => ['required', 'numeric', 'min:0'],
    ],

    'prepare' => function (array $data): array {
        $subtotal = 0.0;

        foreach ($data['items'] as $i => $item) {
            // Floats are printed as amounts: 1,450.00
            $data['items'][$i]['unit_price'] = (float) $item['unit_price'];
            $data['items'][$i]['total'] = round($item['quantity'] * $item['unit_price'], 2);
            $subtotal += $data['items'][$i]['total'];
        }

        $vat = round($subtotal * 0.15, 2);
        $total = round($subtotal + $vat, 2);

        $data['totals'] = [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'in_words' => Arabic::tafqeet($total, 'SAR', only: true),
        ];

        return $data;
    },
];
```

كيف تُطبع القيم في ملف Word:

- **الأعداد العشرية (float)** تُطبع بفواصل الآلاف وخانتين عشريتين: `1450.0` تصبح `1,450.00`. ومع قيمة `currency` في المستوى الأعلى مثل `KWD` تأخذ عدد الخانات العشرية لتلك العملة.
- **الأعداد الصحيحة والنصوص** تُطبع كما هي: الكمية `4` تبقى `4`، والسعر المحفوظ `1450` أو `"1450.00"` يُطبع دون فواصل. لهذا يحوّل `prepare` القيمة `unit_price` إلى عدد عشري.
- **التواريخ** (`Carbon` وغيرها من كائنات التاريخ) تُطبع بصيغة `Y/m/d`، والتاريخ المكتوب نصاً يُطبع كما تمرره.
- تتبع الأرقام `->numerals()`، وتُهرَّب كل قيمة (escaping)، والقيمة التي تحتوي `${...}` تبقى نصاً عادياً.

لا يحتوي المجلد على `pdf.blade.php`، فهذا القالب يُنتج ملفات Word فقط. وتجده الحزمة كأي قالب آخر: يُبحث في `resources/doc-templates` أولاً ثم في القوالب المرفقة بالحزمة.

### 4. املأه من controller

```php
// routes/web.php
use App\Http\Controllers\QuotationWordController;

Route::get('/quotations/{quotation}/word', [QuotationWordController::class, 'show'])
    ->middleware('auth')
    ->name('quotations.word');
```

```php
// app/Http/Controllers/QuotationWordController.php
namespace App\Http\Controllers;

use App\Models\Quotation;
use BiztechEG\EasyPdfWord\Facades\Doc;

class QuotationWordController extends Controller
{
    public function show(Quotation $quotation)
    {
        $quotation->load('customer', 'items', 'salesperson');
        $signature = storage_path("app/signatures/{$quotation->salesperson_id}.png");

        return Doc::template('price-offer', [
            'date' => $quotation->issued_at,
            'quote' => [
                'number' => $quotation->number,
                'valid_until' => $quotation->valid_until,
                'notes' => $quotation->notes,
            ],
            'customer' => [
                'name' => $quotation->customer->name,
                'contact' => $quotation->customer->contact_name,
            ],
            'items' => $quotation->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]),
            'salesperson' => [
                'name' => $quotation->salesperson->name,
                'title' => $quotation->salesperson->job_title,
            ],
            'signature' => is_file($signature) ? $signature : null,
        ])
            ->locale('ar')
            ->word()
            ->download("عرض-سعر-{$quotation->number}.docx");
    }
}
```

ملاحظات على البيانات:

- `date` في المستوى الأعلى لأن `${doc.hijri_date}` يُحسب منه. و`issued_at` و`valid_until` محوّلان إلى تواريخ في النموذج، فيُطبعان هكذا `2026/10/08`.
- يأتي `${theme.company.*}` و`${theme.logo}` في الترويسة من الهوية في `config/easy-pdf-word.php`، ومرّر `->theme([...])` لتغييرهما لمستند واحد.
- التوقيع مسار ملف. تُقرأ الصور افتراضياً من `public/` و`storage/app` و`resources/` فقط، وبشرط أن تكون صوراً فعلية. تدخل PNG وJPEG وGIF كما هي، وتتحول WebP وBMP إلى PNG، أما SVG فتترك المتغير فارغاً.
- مرّر `null` عندما لا يوجد توقيع، كما يفعل فحص `is_file()`: فالقيمة `null` تترك المتغير فارغاً، أما المسار الذي لا تستطيع الحزمة قراءته (ملف غير موجود، أو خارج المجلدات المسموح بها) فيُطبع في المستند نصاً.
- تتطلب ملفات Word الحزمة `phpoffice/phpword`.

## راجع التصميم قبل اعتماده {#check}

عندما ترسل المديرة ملف `word.docx` جديداً، اعرض قائمة المتغيرات التي حفظها Word كاملة:

```bash
php artisan tinker
> (new \PhpOffice\PhpWord\TemplateProcessor(resource_path('doc-templates/price-offer/word.docx')))->getVariables();
```

المتغير الغائب عن القائمة، أو الذي يظهر مقسوماً، أفسده Word (التصحيح التلقائي مثلاً)، فأعد كتابته دفعة واحدة. ثم احتفظ باختبار يملأ الملف ويفشل إذا بقي أي متغير دون ملء:

```php
// tests/Feature/PriceOfferTemplateTest.php
namespace Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Tests\TestCase;
use ZipArchive;

class PriceOfferTemplateTest extends TestCase
{
    public function test_the_price_offer_fills_every_placeholder(): void
    {
        $docx = Doc::template('price-offer', [
            'date' => '2026-10-08',
            'quote' => ['number' => 'QT-2026-0088', 'valid_until' => '2026-10-22', 'notes' => 'التوريد خلال 10 أيام عمل.'],
            'customer' => ['name' => 'شركة الأفق للمقاولات', 'contact' => 'م. خالد العتيبي'],
            'items' => [['description' => 'طابعة فواتير حرارية', 'quantity' => 4, 'unit_price' => 1450]],
            'salesperson' => ['name' => 'سارة القحطاني', 'title' => 'مديرة المبيعات'],
        ])->locale('ar')->word()->content();

        $file = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, $docx);
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        $this->assertStringNotContainsString('${', $xml);
        $this->assertStringContainsString('6,670.00', $xml);   // 4 × 1,450 + 15% VAT
    }
}
```

يحتوي `word/document.xml` على متن الملف، أما رأس الصفحة وتذييلها ففي `word/header1.xml` و`word/footer1.xml`.

## تنويعات {#variations}

### تصميم Word خاص بك لقالب مرفق بالحزمة

احتفظ بملف PDF الخاص بالفاتورة الضريبية كما هو، وأرسل ملف Word بتصميمك. انسخ القالب إلى مشروعك، ثم أضف ملف `word.docx` الخاص بك إلى النسخة:

```bash
php artisan doc:template invoice
# then save your design as resources/doc-templates/invoice/word.docx
```

الآن يملأ `->word()` ملفك، ويستمر `->pdf()` في استخدام تصميم PDF في القالب. والمتغيرات هي بيانات الفاتورة بعد `prepare()`: `${invoice.number}` و`${seller.name}` و`${buyer.name}`، و`${items.description}` و`${items.total}` في صف جدول، و`${totals.total}`، و`${doc.qr:120:120}` لرمز QR. وتسرد صفحة [الفاتورة الضريبية](/ar/templates/invoice) كل الحقول.

### ملف PDF من المجلد نفسه

يفشل `->pdf()` على القالب `price-offer` برسالة "Template [price-offer] has no pdf.blade.php."، لأن ملفات Word لا تتحول إلى PDF. أضف `pdf.blade.php` أو `layout.php` إلى المجلد من أجل PDF، ويبقى `word.docx` مسؤولاً عن ملف Word. انظر [قوالبك الخاصة](/ar/guide/custom-templates).

### عبارات بلغتين

ضع العبارات في `lang/ar.php` و`lang/en.php` داخل مجلد القالب، واكتب `${t.key}` في Word:

```php
<?php
// resources/doc-templates/price-offer/lang/en.php

return [
    'title' => 'Price offer',
    'valid_until' => 'Valid until',
];
```

يطبع `${t.title}` العبارة "Price offer" مع `->locale('en')`، والعبارة العربية مع `->locale('ar')`. واحتفظ بتصميمين `.docx` (في مجلدي قالبين) إذا احتاجت اللغتان تخطيطين مختلفين.

### الأرقام العربية

أضف `->numerals('arabic')`: فتصبح `6,670.00` هكذا `٦,٦٧٠.٠٠`، والتواريخ `٢٠٢٦/١٠/٠٨`. وتحتفظ ملفات Word بالفاصلين `,` و`.`، لأن الخط يُختار على جهاز القارئ.

## صفحات ذات صلة {#related}

- [ملفات Word](/ar/guide/word): إخراج Word والخطوط وإعدادات الاتجاه من اليمين إلى اليسار.
- [قوالبك الخاصة](/ar/guide/custom-templates): `template.php` و`prepare()` وأي ملف يُنتج أي صيغة.
- [الصور](/ar/guide/images): المجلدات المسموح بها والصور البعيدة والصيغ.
- [دعم اللغة العربية](/ar/guide/arabic): التفقيط (المبلغ كتابةً) والتاريخ الهجري والأرقام.
- [عرض سعر](/ar/templates/quotation): قالب عرض السعر المرفق بالحزمة، إن كان التصميم بالكود كافياً.
