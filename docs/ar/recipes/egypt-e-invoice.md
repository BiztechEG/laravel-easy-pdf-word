# طباعة الفاتورة الإلكترونية المصرية

اطبع المستند الذي أرسلته إلى مصلحة الضرائب المصرية باستخدام القالب `eg-invoice`: حوّل مستند المصلحة إلى بيانات القالب مرة واحدة، فتُطبع كل فاتورة وكل إشعار دائن أو مدين بأرقام التسجيل الضريبي وأكواد الأصناف وأنواع الضرائب ورمز QR الخاص ببوابة المصلحة.

## السيناريو {#situation}

شركة بيزتك للحلول البرمجية مسجلة في منظومة الفاتورة الإلكترونية. يبني تطبيق Laravel الخاص بها كل فاتورة مستنداً بصيغة JSON حسب مواصفات مصلحة الضرائب المصرية، ويوقّعه ويرسله إلى واجهة المصلحة البرمجية (API). ومع ذلك يطلب العملاء نسخة مطبوعة أو ملف PDF:

- يعرض بالضبط ما أُرسل: البائع والمشتري، وأكواد الأصناف، والوحدات، والخصومات، وضريبة القيمة المضافة (T1)، والخصم تحت حساب الضريبة (T4)،
- يحمل الرقم الإلكتروني للمستند (UUID) ورمز QR يفتح المستند على بوابة المصلحة،
- يعمل بالطريقة نفسها مع الإشعارات الدائنة والمدينة.

يحفظ التطبيق المستند المرسَل في عمود JSON باسم `eta_document`، ويخزن الرقم الإلكتروني (uuid) والمعرّف الطويل (long ID) اللذين تعيدهما المصلحة.

## الحل {#solution}

### 1. احفظ رد المصلحة

عندما تقبل المصلحة الإرسال، يحمل ردها `uuid` و`longId` لكل مستند. يحتاج رابط البوابة في رمز QR إليهما معاً، فاحفظهما مع الفاتورة:

```php
// In your submission code, after POST /api/v1/documentsubmissions
foreach ($response['acceptedDocuments'] as $accepted) {
    Invoice::where('number', $accepted['internalId'])->update([
        'eta_uuid' => $accepted['uuid'],
        'eta_long_id' => $accepted['longId'],
        'eta_submission_uuid' => $response['submissionId'],
    ]);
}
```

ويحوّل الـ model ‏`Invoice` المستند المحفوظ إلى مصفوفة:

```php
// app/Models/Invoice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// columns: id, number, eta_document (json), eta_uuid, eta_long_id, eta_submission_uuid
class Invoice extends Model
{
    protected function casts(): array
    {
        return ['eta_document' => 'array'];
    }
}
```

### 2. حوّل مستند المصلحة إلى بيانات القالب

يستخدم [قالب الفاتورة الإلكترونية المصرية](/ar/templates/eg-invoice) مفاهيم المصلحة نفسها، فيأتي التحويل قصيراً. هذا الكلاس هو التحويل كله:

```php
// app/Documents/EtaInvoicePrint.php
namespace App\Documents;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class EtaInvoicePrint
{
    public static function for(Invoice $invoice): PendingDocument
    {
        $eta = $invoice->eta_document;   // the document you submitted to ETA

        return Doc::template('eg-invoice', [
            'document' => [
                'type' => $eta['documentType'],                 // I, C or D
                'internal_id' => $eta['internalID'],
                'issued_at' => $eta['dateTimeIssued'],          // UTC, printed in the app's time zone
                'uuid' => $invoice->eta_uuid,
                'long_id' => $invoice->eta_long_id,
                'submission_uuid' => $invoice->eta_submission_uuid,
                'purchase_order' => $eta['purchaseOrderReference'] ?? null,
                'currency' => 'EGP',
            ],
            'issuer' => [
                'name' => $eta['issuer']['name'],
                'rin' => $eta['issuer']['id'],
                'branch_id' => $eta['issuer']['address']['branchID'] ?? null,
                'activity_code' => $eta['taxpayerActivityCode'],
                'address' => self::address($eta['issuer']['address'] ?? []),
            ],
            'receiver' => [
                'type' => $eta['receiver']['type'],             // B, P or F
                'id' => $eta['receiver']['id'] ?? null,
                'name' => $eta['receiver']['name'],
                'address' => self::address($eta['receiver']['address'] ?? []),
            ],
            'lines' => array_map(fn (array $line) => [
                'description' => $line['description'],
                'item_type' => $line['itemType'],
                'item_code' => $line['itemCode'],
                'unit' => $line['unitType'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unitValue']['amountEGP'],
                'discount' => $line['discount']['amount'] ?? 0,
                'taxes' => array_map(fn (array $tax) => [
                    'type' => $tax['taxType'],
                    'subtype' => $tax['subType'] ?? null,
                    'rate' => $tax['rate'] ?? null,
                    'amount' => $tax['amount'],
                ], $line['taxableItems'] ?? []),
            ], $eta['invoiceLines']),
            'extra_discount' => $eta['extraDiscountAmount'] ?? 0,
            'notes' => self::references($eta),
        ])->locale('ar');
    }

    /** "15 شارع التحرير، الدقي، الجيزة" from ETA's address fields. */
    private static function address(array $address): ?string
    {
        $street = trim(($address['buildingNumber'] ?? '').' '.($address['street'] ?? ''));

        return implode('، ', array_filter([$street, $address['regionCity'] ?? null, $address['governate'] ?? null])) ?: null;
    }

    /** A credit or debit note names the invoices it corrects. */
    private static function references(array $eta): ?string
    {
        if (empty($eta['references'])) {
            return null;
        }

        $numbers = Invoice::whereIn('eta_uuid', $eta['references'])->pluck('number');

        return 'عن الفاتورة رقم '.$numbers->implode('، ');
    }
}
```

كيف تتقابل الحقول:

| مستند المصلحة | حقل القالب |
| --- | --- |
| `documentType` (‏`I` أو `C` أو `D`) | `document.type`: يصبح العنوان فاتورة ضريبية إلكترونية أو إشعار دائن أو إشعار مدين |
| `internalID` و`dateTimeIssued` و`purchaseOrderReference` | `document.internal_id` و`document.issued_at` و`document.purchase_order` |
| `issuer.id` و`issuer.address.branchID` و`taxpayerActivityCode` | `issuer.rin` و`issuer.branch_id` و`issuer.activity_code` |
| `receiver.type` (‏`B` أو `P` أو `F`) و`receiver.id` | `receiver.type` و`receiver.id`: يُطبع رقم تسجيل ضريبي أو رقماً قومياً أو رقم جواز سفر |
| `itemType` و`itemCode` و`unitType` | `item_type` (‏`EGS` أو `GS1`) و`item_code` و`unit` |
| `unitValue.amountEGP` و`discount.amount` | `unit_price` و`discount` (قبل الضريبة) |
| `taxableItems[]` | `taxes[]`: ‏`type` و`subtype` و`rate` و`amount` |
| `extraDiscountAmount` | `extra_discount` |

تُمرَّر كل ضريبة بقيمتها `amount` كما في مستند المصلحة. وإذا كان للضريبة `amount` استخدمه القالب كما هو، فتعرض النسخة المطبوعة المبالغ التي قبلتها المصلحة بالضبط. ومن دون `amount` يحسب القالب كل ضريبة من نسبتها `rate` كما تفعل المصلحة: مثلاً ضريبة القيمة المضافة (T1) على صافي المبلغ مضافاً إليه ضريبة الجدول والرسوم الخاضعة، والخصم تحت حساب الضريبة (T4) على صافي المبلغ ويُطرح من الإجمالي.

يطبع القالب `dateTimeIssued`، وهو بتوقيت UTC، بالمنطقة الزمنية لتطبيقك (في `config/app.php`، مثلاً `'timezone' => 'Africa/Cairo'`): يُطبع `2026-10-08T09:45:00Z` على أنه `2026/10/08 12:45`.

### 3. الـ route والـ controller

```php
// routes/web.php
use App\Http\Controllers\EtaInvoiceController;

Route::get('/invoices/{invoice}/eta-print', EtaInvoiceController::class)->name('invoices.eta-print');
```

```php
// app/Http/Controllers/EtaInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\EtaInvoicePrint;
use App\Models\Invoice;

class EtaInvoiceController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        abort_if($invoice->eta_uuid === null, 404);

        return EtaInvoicePrint::for($invoice)->pdf("فاتورة-{$invoice->number}.pdf");
    }
}
```

لا يطبع الـ controller إلا المستندات التي قبلتها المصلحة: فقبل ذلك لا يوجد رقم إلكتروني ولا رابط على البوابة. وعند وجود الرقم الإلكتروني والمعرّف الطويل يبني القالب رمز QR بالرابط `https://invoicing.eta.gov.eg/documents/{uuid}/share/{longId}`، ويطبع الرقم الإلكتروني ورقم الإرسال أعلى الصفحة. يُفتح ملف PDF في المتصفح، وتعرض [صفحة القالب](/ar/templates/eg-invoice) شكل الفاتورة الكاملة.

::: details مستند المصلحة كما يقرؤه التحويل
لا تظهر هنا إلا المفاتيح التي يستخدمها التحويل. والمستند الكامل فيه أيضاً `documentTypeVersion` وإجماليات الأسطر والمستند والتوقيعات.

```php
$eta = [
    'issuer' => [
        'type' => 'B',
        'id' => '123456789',
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => ['branchID' => '0', 'country' => 'EG', 'governate' => 'الجيزة', 'regionCity' => 'الدقي', 'street' => 'شارع التحرير', 'buildingNumber' => '15'],
    ],
    'receiver' => [
        'type' => 'B',
        'id' => '987654321',
        'name' => 'مؤسسة النور للتجارة',
        'address' => ['country' => 'EG', 'governate' => 'القاهرة', 'regionCity' => 'مدينة نصر', 'street' => 'شارع عباس العقاد', 'buildingNumber' => '22'],
    ],
    'documentType' => 'I',
    'dateTimeIssued' => '2026-10-08T09:45:00Z',
    'taxpayerActivityCode' => '6201',
    'internalID' => 'INV-2026-1024',
    'purchaseOrderReference' => 'PO-7781',
    'invoiceLines' => [
        [
            'description' => 'تطوير نظام إدارة المخزون',
            'itemType' => 'EGS', 'itemCode' => 'EG-123456789-1001', 'unitType' => 'EA',
            'quantity' => 1,
            'unitValue' => ['currencySold' => 'EGP', 'amountEGP' => 25000],
            'discount' => ['rate' => 0, 'amount' => 0],
            'taxableItems' => [
                ['taxType' => 'T1', 'amount' => 3500, 'subType' => 'V009', 'rate' => 14],
                ['taxType' => 'T4', 'amount' => 750, 'subType' => 'W010', 'rate' => 3],
            ],
        ],
        [
            'description' => 'استضافة سحابية - اشتراك شهري',
            'itemType' => 'EGS', 'itemCode' => 'EG-123456789-2002', 'unitType' => 'MON',
            'quantity' => 12,
            'unitValue' => ['currencySold' => 'EGP', 'amountEGP' => 450],
            'discount' => ['rate' => 0, 'amount' => 400],
            'taxableItems' => [
                ['taxType' => 'T1', 'amount' => 700, 'subType' => 'V009', 'rate' => 14],
            ],
        ],
    ],
    'extraDiscountAmount' => 0,
];
```

يُطبع هذا المستند بإجمالي مبيعات 30,400.00، وخصومات 400.00، وصافي 30,000.00، وضريبة قيمة مضافة (T1) ‏4,200.00، وخصم تحت حساب الضريبة (T4) ‏-750.00، وإجمالي 33,450.00، وهو نفس `totalAmount` لدى المصلحة.
:::

::: warning تنبيه: الخصم بعد الضريبة
في القالب حقول للخصم قبل الضريبة (`discount.amount` في السطر) وللخصم الإضافي على المستند `extraDiscountAmount`. أما `itemsDiscount` لدى المصلحة (خصم على السطر بعد الضريبة) و`valueDifference` فليس لهما حقل، فالمستند الذي يستخدمهما يُطبع بإجمالي مختلف. إذا كنت تستخدمهما فقارن الإجمالي المطبوع بـ `totalAmount` قبل الاعتماد على النسخة المطبوعة.
:::

## الإشعارات الدائنة والمدينة {#notes}

يمر الإشعار الدائن (`documentType` بقيمة `C`) أو المدين (`D`) عبر الكلاس نفسه. يتغير العنوان إلى إشعار دائن أو إشعار مدين، ويكرره تذييل الصفحة مع رقم الإشعار. تربط المصلحة الإشعار بالفواتير التي يصححها عبر `references` (أرقامها الإلكترونية)؛ وليس في القالب حقل لها، لذلك تبحث `references()` في الكلاس السابق عن أرقام تلك الفواتير وتطبعها ملاحظةً: `عن الفاتورة رقم INV-2026-1024`.

<div class="preview">
  <figure><a href="/images/recipes-a/eta-credit-note.png" target="_blank"><img src="/images/recipes-a/eta-credit-note.png" alt="إشعار دائن إلكتروني مصري بالعربية فيه الرقم الإلكتروني وأكواد الأصناف وضريبة القيمة المضافة ورمز QR لبوابة المصلحة"></a><figcaption>إشعار دائن عن شهرين من الاستضافة، مرتبط بالفاتورة INV-2026-1024</figcaption></figure>
</div>

للإشعارات خارج منظومة المصلحة، أو للإشعارات السعودية برمز QR الخاص بهيئة الزكاة، استخدم [قالب إشعار دائن ومدين](/ar/templates/credit-note).

## تنويعات {#variations}

### حفظ النسخة المطبوعة عند قبول المصلحة للمستند

ولّد ملف PDF على queue worker مباشرة بعد حفظ رد المصلحة، واحتفظ به مع الفاتورة:

```php
EtaInvoicePrint::for($invoice)->queue("eta/{$invoice->eta_uuid}.pdf", 's3');
```

يجري التحقق من البيانات بقواعد القالب قبل وضع المهمة في الـ queue، فيظهر أي خطأ في التحويل داخل كود الإرسال لا على الـ worker.

### نسخة Word

يُخرج القالب `eg-invoice` ملفات Word أيضاً (يحتاج إلى `phpoffice/phpword`):

```php
return EtaInvoicePrint::for($invoice)->word("فاتورة-{$invoice->number}.docx")->download();
```

### نسخة إنجليزية لعميل أجنبي

للمشتري من النوع `F` اطبع بالإنجليزية. يُكتب المبلغ عندها بالكلمات عبر إضافة `intl` في PHP (thirty-three thousand four hundred fifty EGP only):

```php
return EtaInvoicePrint::for($invoice)->locale('en')->pdf();
```

### الاختبار على بيئة ما قبل الإنتاج

يشير رمز QR إلى بوابة الإنتاج. وأثناء الاختبار على بيئة ما قبل الإنتاج لدى المصلحة، وجّهه إلى بوابتها عبر الحقل `qr` الذي يحل محل الرابط المبني تلقائياً:

```php
EtaInvoicePrint::for($invoice)
    ->with('qr', "https://preprod.invoicing.eta.gov.eg/documents/{$invoice->eta_uuid}/share/{$invoice->eta_long_id}")
    ->pdf();
```

## صفحات ذات صلة {#related}

- [الفاتورة الإلكترونية المصرية](/ar/templates/eg-invoice): كل حقول القالب، مع أنواع الضرائب من T1 إلى T20.
- [إشعار دائن ومدين](/ar/templates/credit-note): الإشعارات خارج منظومة المصلحة.
- [الإخراج والتسليم](/ar/guide/output): التنزيل والحفظ على disk والحفظ عبر الـ queue.
- [تنزيل أو عرض أو حفظ](/ar/recipes/controller-responses): طرق أخرى لإرسال ملف PDF من controller.
