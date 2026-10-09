# فاتورة هيئة الزكاة السعودية

أصدر فاتورة ضريبية مبسطة لمتجر سعودي: ضريبة قيمة مضافة 15% بالريال، ورمز QR للمرحلة الأولى من الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك، والتاريخ الهجري، والأرقام العربية. وتعرض حالة الاستخدام أيضاً كيف تبني رمز QR بنفسك في تصميماتك الخاصة، وماذا تعني المرحلتان الأولى والثانية لتطبيقك.

## السيناريو {#situation}

متجر عطور في الرياض، مؤسسة النور للتجارة، يبيع لزبائن المتجر وللمشترين عبر الإنترنت. كل عملية بيع تحتاج إلى فاتورة ضريبية مبسطة يستطيع العميل طباعتها أو الاحتفاظ بها على هاتفه، وفيها:

- اسم البائع ورقم تسجيله في ضريبة القيمة المضافة وسجله التجاري،
- الأصناف، وضريبة القيمة المضافة 15%، والإجمالي بالريال السعودي مع التفقيط،
- التاريخ الميلادي والتاريخ الهجري، بالأرقام العربية (١٢٣)،
- رمز QR الخاص بالمرحلة الأولى لهيئة الزكاة والضريبة والجمارك: اسم البائع، والرقم الضريبي، ووقت الفاتورة، والإجمالي شاملاً الضريبة، ومبلغ الضريبة.

## الحل {#solution}

### 1. نسخة من قالب الفاتورة بعنوان الفاتورة المبسطة

عنوان [الفاتورة الضريبية](/ar/templates/invoice) المرفقة بالحزمة هو «فاتورة ضريبية». انسخ القالب لمبيعات الأفراد وغيّر العنوان فقط:

```bash
php artisan doc:template invoice --as=simplified-invoice
```

توجد النسخة في `resources/doc-templates/simplified-invoice`. غيّر سطراً واحداً في كل ملف لغة:

```php
// resources/doc-templates/simplified-invoice/lang/ar.php
'title' => 'فاتورة ضريبية مبسطة',

// resources/doc-templates/simplified-invoice/lang/en.php
'title' => 'Simplified Tax Invoice',
```

تعمل الحقول والضريبة ورمز QR كما في الأصل. لا تصل إلى النسخة التعديلات اللاحقة على القالب الأصلي، فاجعل تعديلاتك قليلة؛ انظر [قوالبك الخاصة](/ar/guide/custom-templates).

### 2. البائع في ملف الإعدادات

يقرأ القالب بيانات البائع من بيانات الشركة في الهوية، ويأخذ رمز QR اسم البائع ورقمه الضريبي من هناك. اكتبهما كما سُجّلا لدى الهيئة تماماً:

```php
// config/easy-pdf-word.php
'theme' => [
    // primary, text, muted, border, logo ...
    'company' => [
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'طريق الملك فهد، حي العليا، الرياض',
        'phone' => '+966 11 000 0000',
        'tax_number' => '300000000000003',       // VAT number, 15 digits
        'commercial_register' => '1010123456',
    ],
],
```

### 3. المستند

في المتجر model باسم `Sale` (`number`، `customer_name`، `created_at`) وأسطره `SaleItem` (`name`، `quantity`، `unit_price` قبل الضريبة، بتحويل `decimal:2`). كلاس واحد يحوّل عملية البيع إلى فاتورة:

```php
// app/Documents/SaleInvoice.php
namespace App\Documents;

use App\Models\Sale;
use App\Models\SaleItem;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class SaleInvoice
{
    public static function for(Sale $sale): PendingDocument
    {
        return Doc::template('simplified-invoice', [
            'invoice' => [
                'number' => $sale->number,
                'date' => $sale->created_at->timezone('Asia/Riyadh'),   // date and time, also in the QR
                'currency' => 'SAR',
                'tax_rate' => 15,
            ],
            'buyer' => ['name' => $sale->customer_name ?: 'عميل نقدي'],
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'description' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]),
            'qr' => 'zatca',
        ])
            ->locale('ar')
            ->numerals('arabic');
    }
}
```

ما يفعله كل جزء:

- `'qr' => 'zatca'` يجعل القالب يبني رمز QR للمرحلة الأولى من بيانات البائع و`invoice.date` والإجماليات التي حسبها للتو، فيطابق الرمز دائماً المبالغ المطبوعة.
- يحمل `invoice.date` وقت البيع أيضاً. يخزّنه رمز QR بتوقيت UTC (`2026-10-08T15:42:10Z`). وتجعل `->timezone('Asia/Riyadh')` التاريخ المطبوع هو التاريخ السعودي حتى لو كان تطبيقك يعمل بتوقيت UTC: عملية بيع في الساعة 01:30 بتوقيت الرياض تُطبع بتاريخ ذلك اليوم.
- يشترط القالب اسم المشتري. وزبون المتجر العابر هو `عميل نقدي`.
- تطبع `->numerals('arabic')` كل الأرقام بالشكل ١٢٣، ومنها رقم الفاتورة والتواريخ. أما محتوى رمز QR فلا يتغير: يبقى بالأرقام اللاتينية كما تتوقع الهيئة.
- يُطبع التاريخ الهجري تحت تاريخ الإصدار في المستندات العربية، بتقويم أم القرى. ويحتاج إلى إضافة `intl` في PHP؛ ومن دونها يُحذف هذا السطر.

### 4. الـ route والـ controller

```php
// routes/web.php
use App\Http\Controllers\SaleInvoiceController;

Route::get('/sales/{sale}/invoice', SaleInvoiceController::class)->name('sales.invoice');
```

```php
// app/Http/Controllers/SaleInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\SaleInvoice;
use App\Models\Sale;

class SaleInvoiceController extends Controller
{
    public function __invoke(Sale $sale)
    {
        return SaleInvoice::for($sale->load('items'))->pdf("فاتورة-{$sale->number}.pdf");
    }
}
```

إعادة ملف PDF من الـ controller تعرضه في المتصفح جاهزاً للطباعة. ضع الـ route خلف نظام الدخول المعتاد في تطبيقك، أو أعطِ العميل رابطاً موقّعاً (signed). وهذه النتيجة لعملية بيع فيها 450.00 و2 × 85.00 و25.00:

<div class="preview">
  <figure><a href="/images/recipes-a/zatca-invoice.png" target="_blank"><img src="/images/recipes-a/zatca-invoice.png" alt="فاتورة ضريبية مبسطة بالعربية بالأرقام العربية والتاريخ الهجري وضريبة 15% بالريال ورمز QR لهيئة الزكاة والضريبة والجمارك"></a><figcaption>المجموع 645.00، الضريبة 96.75، الإجمالي 741.75 ر.س</figcaption></figure>
</div>

## محتوى رمز QR {#qr}

رمز QR للمرحلة الأولى خمسة حقول بصيغة TLV التي تعتمدها الهيئة (الوسم، الطول، القيمة)، مرمّزة بـ base64. لعملية البيع السابقة:

| الوسم | الحقل | القيمة |
| --- | --- | --- |
| 1 | اسم البائع | مؤسسة النور للتجارة |
| 2 | رقم التسجيل في ضريبة القيمة المضافة | 300000000000003 |
| 3 | وقت الفاتورة (UTC) | 2026-10-08T15:42:10Z |
| 4 | الإجمالي شاملاً الضريبة | 741.75 |
| 5 | مبلغ الضريبة | 96.75 |

تُحسب الأطوال بالبايت، فتُرمَّز الأسماء العربية بشكل صحيح. ولا تزيد أي قيمة على 255 بايت؛ واسم البائع الأطول من ذلك يرمي `InvalidArgumentException`. وتُكتب الإجماليات بخانتين عشريتين.

تقرأ `ZatcaQr::decode()` محتوى الرمز وتعيده إلى هذه الحقول، وهذا مفيد في الاختبارات:

```php
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

ZatcaQr::decode($base64);
// [1 => 'مؤسسة النور للتجارة', 2 => '300000000000003', 3 => '2026-10-08T15:42:10Z', 4 => '741.75', 5 => '96.75']
```

## تصميماتك الخاصة: بناء الرمز باستخدام ZatcaQr {#zatca-qr}

إذا كنت تطبع الإيصالات من view خاص بك، أو تبني المستند بالكود، فأنشئ الرمز باستخدام `ZatcaQr`:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

$qr = ZatcaQr::make(
    sellerName: 'مؤسسة النور للتجارة',
    vatNumber: '300000000000003',
    timestamp: $sale->created_at,
    total: 741.75,        // with VAT
    vatTotal: 96.75,
);

$qr->toBase64();    // the QR content, in ZATCA's TLV format
$qr->toDataUri();   // a PNG of the QR as a data URI, for an img tag

return Doc::view('pdf.pos-receipt', ['sale' => $sale, 'zatcaQr' => $qr->toBase64()])
    ->locale('ar')
    ->pdf();
```

يقبل `timestamp` تاريخاً من نوع `Carbon` أو نصاً. والتاريخ الذي ليس فيه وقت، مثل `'2026-10-08'`، يحتفظ بيومه. ويقبل `total` و`vatTotal` نصوصاً منسّقة مثل `'1,150.00'`. مرّر الإجماليات نفسها التي تطبعها؛ فالرمز لا يحسبها.

وفي الـ view، يرسم مكوّن QR الخاص بالحزمة الرمز:

```blade
{{-- resources/views/pdf/pos-receipt.blade.php --}}
<x-doc::layout :doc="$doc">
    <h2>فاتورة ضريبية مبسطة</h2>
    <p>رقم الفاتورة: {{ $doc->ltr($sale->number) }}</p>
    <x-doc::qr :value="$zatcaQr" size="30mm" />
</x-doc::layout>
```

وفي مستند مبني بالكود، أضفه كتلةً. والمستند نفسه يُخرج ملف Word أيضاً:

```php
Doc::make()
    ->heading('فاتورة ضريبية مبسطة')
    ->qr($qr->toBase64(), 30)
    ->locale('ar')
    ->pdf();
```

وفي قالب مصمم في Word، مرّر نص base64 في الحقل `qr` وضع `${doc.qr}` حيث تريد الصورة؛ انظر [قالب مصمم في Word](/ar/recipes/word-designed-template).

## المرحلة الأولى والمرحلة الثانية {#phases}

للفوترة الإلكترونية لدى الهيئة مرحلتان:

- **المرحلة الأولى، مرحلة الإصدار** (سارية منذ 4 ديسمبر 2021): تصدر الفواتير من نظام إلكتروني لا باليد، وبالحقول المطلوبة، وتحمل الفواتير المبسطة رمز QR السابق. وهذا ما تغطيه الحزمة: الفاتورة المطبوعة ورمز QR للمرحلة الأولى.
- **المرحلة الثانية، مرحلة الربط والتكامل** (تُطبَّق على دفعات منذ 1 يناير 2023): كل فاتورة هي أيضاً ملف XML بصيغة UBL 2.1، موقّع بختم تشفيري ومرتبط بما قبله بقيمة hash، ويُرسل إلى منصة «فاتورة» التابعة للهيئة: الفواتير الضريبية القياسية تُعتمد (clearance) قبل وصولها إلى المشتري، والفواتير المبسطة يُبلَّغ عنها (reporting) خلال 24 ساعة. ورمز QR في المرحلة الثانية فيه حقول أكثر، مثل hash الفاتورة والتوقيع.

::: warning تنبيه: المرحلة الثانية ليست من مهام الحزمة
لا تُنشئ الحزمة ملف XML ولا توقّعه ولا تتصل بمنصة «فاتورة». إذا كانت منشأتك ضمن إحدى دفعات المرحلة الثانية، فاستخدم لذلك حلاً معتمداً لدى الهيئة (حزمة SDK الخاصة بالهيئة أو مزود خدمة). يعطيك هذا الحل محتوى رمز QR لكل فاتورة: مرّره في الحقل `qr` بدلاً من `'zatca'`، فيطبعه القالب كما هو.

```php
'qr' => $phase2Qr,   // base64 text from your phase 2 solution
```
:::

## تنويعات {#variations}

### أسعار شاملة الضريبة

أسعار الرفوف في المتاجر السعودية تشمل الضريبة عادةً، أما القالب فيأخذ الأسعار قبل الضريبة. اقسم على 1.15 ودع القالب يقرّب كل سطر:

```php
'unit_price' => $item->price_with_vat / 1.15,
```

::: warning تنبيه: راجع الإجمالي
يقرّب القالب كل سطر إلى الهللة، ويحسب الضريبة على إجمالي الفاتورة. معظم السلال تعود إلى سعر الرف، لكن ليس كلها: ثلاثة أصناف منفصلة سعر كل منها 10.00 ريالات تعطي أسطراً بقيمة 8.70، وضريبة 3.92، وإجمالياً 30.02. إذا كان نظامك يخزن الأسعار شاملة الضريبة، فقارن إجمالي الفاتورة بالمبلغ الذي حصّلته، أو خزّن الأسعار قبل الضريبة.
:::

### فاتورة ضريبية قياسية لعميل منشأة

للمشتري المسجّل في ضريبة القيمة المضافة، استخدم القالب `invoice` المرفق بالحزمة، وعنوانه «فاتورة ضريبية»، وأضف عنوان المشتري ورقمه الضريبي. رمز QR إلزامي في الفواتير المبسطة، ويمكنك إبقاؤه في القياسية:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-2026-000093', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15],
    'buyer' => [
        'name' => 'شركة الأفق للمقاولات',
        'address' => 'حي الملقا، الرياض',
        'tax_number' => '311111111100003',
    ],
    'items' => [
        ['description' => 'عطور ضيافة للمكاتب', 'quantity' => 10, 'unit_price' => 120],
    ],
    'qr' => 'zatca',
])->locale('ar')->numerals('arabic')->pdf();
```

### مرتجع: إشعار دائن

يحتاج استرداد المبلغ إلى إشعار دائن مرتبط بالفاتورة الأصلية. ويبني [قالب إشعار دائن ومدين](/ar/templates/credit-note) رمز QR نفسه للمرحلة الأولى:

```php
Doc::template('credit-note', [
    'type' => 'credit',
    'note' => ['number' => 'CN-2026-000012', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15],
    'invoice' => ['number' => $sale->number, 'date' => $sale->created_at],
    'reason' => 'إرجاع منتج',
    'buyer' => ['name' => 'عميل نقدي'],
    'items' => [
        ['description' => 'بخور معطر', 'quantity' => 1, 'unit_price' => 85],
    ],
    'qr' => 'zatca',
])->locale('ar')->numerals('arabic')->pdf();
```

### إرسالها بالبريد أو حفظها

الفاتورة مستند عادي: أرفقها برسالة بريد أو احفظها على disk كما في [إرسال فاتورة بالبريد](/ar/recipes/email-invoice) و[تنزيل أو عرض أو حفظ](/ar/recipes/controller-responses).

## صفحات ذات صلة {#related}

- [الفاتورة الضريبية](/ar/templates/invoice) و[إشعار دائن ومدين](/ar/templates/credit-note): القالبان وكل حقولهما.
- [دعم اللغة العربية](/ar/guide/arabic): الأرقام العربية والتاريخ الهجري والتفقيط.
- [قوالبك الخاصة](/ar/guide/custom-templates): نسخ قالب وتغيير نصوصه.
- [أدوات القوالب](/ar/reference/template-helpers): `$doc->ltr()` و`$doc->hijri()` ومكوّن QR.
