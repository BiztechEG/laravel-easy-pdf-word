# القوالب الجاهزة

تأتي الحزمة باثني عشر قالباً، كل منها بالعربية والإنجليزية وبصيغتي PDF وWord. تشرح هذه الصفحة ما تشترك فيه القوالب كلها: كيف تمرر البيانات، وكيف يجري التحقق منها، ومن أين تأتي بيانات الشركة والتسميات، وكيف تعرض القوالب وتنسخها وتستبدلها. أما حقول كل قالب فتجدها في صفحته داخل [معرض القوالب](/ar/templates/).

## إنشاء مستند من قالب {#usage}

استدعِ `Doc::template()` باسم القالب وبياناتك، واختر اللغة، ثم أنشئ الملف:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$pdf = Doc::template('quotation', [
    'quote' => [
        'number' => 'QT-2026-0088',
        'date' => '2026-10-08',
        'valid_until' => '2026-11-07',
        'currency' => 'EGP',
        'tax_rate' => 14,
    ],
    'customer' => ['name' => 'مؤسسة النور للتجارة', 'phone' => '+20 122 555 0100'],
    'items' => [
        ['description' => 'تطوير النظام (Laravel)', 'unit' => 'مرحلة', 'quantity' => 1, 'unit_price' => 42000],
        ['description' => 'تدريب المستخدمين', 'unit' => 'يوم', 'quantity' => 3, 'unit_price' => 1500],
    ],
])->locale('ar')->pdf();
```

البيانات مصفوفة متداخلة تتبع مفاتيحها حقولَ القالب. تُحوَّل collections الخاصة بـ Laravel ونماذج Eloquent إلى مصفوفات أولاً، فيعمل `'items' => $order->items` ما دامت أسماء الخصائص تطابق الحقول (`description` و`quantity` و`unit_price`).

ويمكنك أيضاً إضافة البيانات على مراحل. تستبدل `->data()` المفاتيح العليا كاملة، وتضبط `->with()` مفتاحاً واحداً:

```php
$invoice = Doc::template('invoice')
    ->data($data)
    ->with('qr', 'zatca');   // رمز QR الخاص بهيئة الزكاة والضريبة والجمارك
```

يعيد `Doc::template()` الكائن نفسه الذي تعيده بقية المستندات، فتعمل كل الإعدادات: `->locale()` و`->numerals()` و`->theme()` و`->paper()` و`->footer()` و`->watermark()` وغيرها. راجع [إعدادات الصفحة](/ar/guide/page-settings).

## القوالب الاثنا عشر {#list-of-templates}

| الاسم | الصفحة | مفاتيح البيانات الرئيسية |
| --- | --- | --- |
| `invoice` | [الفاتورة الضريبية](/ar/templates/invoice) | `invoice` و`seller` و`buyer` و`items` و`qr` |
| `eg-invoice` | [الفاتورة الإلكترونية المصرية](/ar/templates/eg-invoice) | `document` و`issuer` و`receiver` و`lines` |
| `credit-note` | [إشعار دائن ومدين](/ar/templates/credit-note) | `type` و`note` و`invoice` و`reason` و`buyer` و`items` |
| `quotation` | [عرض سعر](/ar/templates/quotation) | `quote` و`customer` و`items` و`terms` و`sender` |
| `purchase-order` | [أمر شراء](/ar/templates/purchase-order) | `order` و`supplier` و`delivery` و`items` و`terms` |
| `delivery-note` | [إذن تسليم](/ar/templates/delivery-note) | `delivery` و`customer` و`items` و`transport` |
| `receipt` | [سند قبض وسند صرف](/ar/templates/receipt) | `type` و`number` و`date` و`amount` و`party` و`for` و`method` |
| `payslip` | [قسيمة راتب](/ar/templates/payslip) | `period` و`employee` و`earnings` و`deductions` |
| `contract` | [عقد](/ar/templates/contract) | `contract` و`parties` و`clauses` و`witnesses` |
| `certificate` | [شهادة](/ar/templates/certificate) | `type` و`gender` و`recipient` و`course` و`signatures` |
| `letter` | [خطاب رسمي](/ar/templates/letter) | `date` و`recipient` و`subject` و`body` و`sender` |
| `report` | [تقرير جدولي](/ar/templates/report) | `title` و`columns` و`rows` و`sum` و`summary` |

## التحقق من البيانات {#validation}

يحدد كل قالب قواعد تحقق Laravel لبياناته تحت المفتاح `fields` في ملف `template.php`. تُفحص البيانات قبل رسم الملف، وقبل وضع الـ job في الـ queue عند استخدام `->queue()`. والبيانات التي لا تجتاز الفحص ترمي `Illuminate\Validation\ValidationException` الخاص بـ Laravel، مع الرسائل المعتادة:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-1024', 'date' => '2026-02-30'],
    'items' => [],
])->pdf();
```

```text
Illuminate\Validation\ValidationException
The invoice.date field must be a valid date. (and 2 more errors)
```

وتسرد `$e->errors()` كل مشكلة بحسب الحقل:

```php
[
    'invoice.date' => ['The invoice.date field must be a valid date.'],
    'buyer.name' => ['The buyer.name field is required.'],
    'items' => ['The items field is required.'],
]
```

ولأنه `ValidationException` عادي، يتعامل معه Laravel كما يتعامل مع نموذج إدخال: يحصل طلب API على استجابة JSON برمز 422 فيها هذه الأخطاء، ويُعاد طلب الويب إلى الصفحة السابقة معها. وفي الغالب تأتي البيانات من قاعدة بياناتك لا من نموذج إدخال، فالخطأ هنا يشير إلى خلل في الكود الذي يبني البيانات.

وتفحص القوالب أيضاً ما لا تستطيع القواعد فحصه. ففي الفاتورة وعرض السعر وأمر الشراء والإشعار الدائن والفاتورة الإلكترونية المصرية، يفشل الخصم الأكبر من قيمة البند بخطأ على `items.0.discount` (أو `lines.0.discount`) نصه: "The discount cannot be more than the line amount (quantity × unit price)."

وإذا كنت قد تحققت من البيانات بنفسك، فإن `->withoutValidation()` تتخطى القواعد. لكن القالب قد يفشل حينها عند غياب مفتاح برسالة أقل وضوحاً، فأبقِ التحقق مفعّلاً ما لم يكن لديك سبب لإيقافه.

## القيم الافتراضية والقيم المحسوبة {#defaults}

تملأ القوالب ما تتركه. تُدمج قيمها الافتراضية (`defaults`) تحت بياناتك قبل فحصها. فالفاتورة مثلاً تفترض الجنيه المصري وضريبة قيمة مضافة 14%:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-1025', 'date' => now()],
    'buyer' => ['name' => 'مؤسسة النور للتجارة'],
    'items' => [
        ['description' => 'استشارات تقنية', 'quantity' => 2, 'unit_price' => 1000],
    ],
])->locale('ar')->pdf();   // الجنيه المصري وضريبة 14%: الإجمالي 2,280.00
```

ولفاتورة سعودية، مرّر العملة والنسبة: `'invoice' => ['number' => 'INV-1025', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15]`.

ثم تضيف خطوة `prepare` في القالب القيمَ المحسوبة: إجماليات البنود، والمجموع، والخصم، والضريبة، والإجمالي، وترقيم الصفوف، ورمز QR الخاص بهيئة الزكاة والضريبة والجمارك من `'qr' => 'zatca'`، وغير ذلك. أنت تمرر الكميات والأسعار ولا تمرر الإجماليات أبداً. والقيم التي تضعها تحت مفاتيح يحسبها القالب تُستبدل.

تسرد صفحة كل قالب قيمه الافتراضية وقيمه المحسوبة. وفي الاختبارات، يتيح لك `Doc::fake()` قراءة البيانات بعد تجهيزها، مثل `$doc->data('totals.total')`. راجع [اختبار تطبيقك](/ar/guide/testing).

## البيانات التجريبية {#sample-data}

يحمل كل قالب بيانات تجريبية في ملف `template.php`. تستخدمها [صفحة المعاينة](/ar/guide/preview) والأمر `php artisan doc:sample`، ويمكنك استخدامها أيضاً: فهي أسرع طريقة لرؤية القالب، وتعرض شكل البيانات بدقة.

```php
$sample = Doc::templates()->get('payslip')->sample();

return Doc::template('payslip', $sample)->locale('ar')->pdf()->stream();
```

ويطبع `dd(Doc::templates()->get('invoice')->sample())` مصفوفة فاتورة كاملة وصحيحة تنسخ منها.

## بيانات الشركة والشعار {#theme}

تأخذ القوالب اسم شركتك وعنوانها وشعارها من **الهوية** (theme) لا من البيانات، فتضبطها مرة واحدة. تأتي الهوية من المفتاح `theme` في `config/easy-pdf-word.php`، ويتجاوزها `->theme()` لمستند واحد:

| المفتاح | يُستخدم في | القيمة الافتراضية |
| --- | --- | --- |
| `primary` | العناوين، ورؤوس الجداول، وصف الإجمالي | `#0F766E` |
| `text` | نص المحتوى | `#1F2937` |
| `muted` | التسميات والنص الثانوي | `#6B7280` |
| `border` | حدود الجداول والمربعات | `#E5E7EB` |
| `logo` | الشعار: مسار ملف أو رابط مسموح به أو data URI | لا شيء |
| `company.name` | اسم الشركة | `APP_NAME` |
| `company.address` و`company.phone` و`company.email` و`company.tax_number` | بيانات التواصل والضريبة | لا شيء |

```php
Doc::template('quotation', $data)
    ->theme([
        'primary' => '#B45309',
        'logo' => storage_path('app/tenants/14/logo.png'),
        'company' => [
            'name' => 'مؤسسة النور للتجارة',
            'address' => 'طريق الملك فهد، الرياض',
            'phone' => '+966 11 000 0000',
            'tax_number' => '300000000000003',
        ],
    ])
    ->locale('ar')
    ->pdf();
```

يُدمج `->theme()` مع هوية الإعدادات مفتاحاً بمفتاح: فضبط `company.name` وحده يُبقي العنوان والهاتف القادمين من الإعدادات. ويجب أن تكون الألوان ألواناً صحيحة (`#B45309` أو `rgb(180, 83, 9)` أو `red`)، وأي قيمة أخرى تعود إلى اللون الافتراضي. وإن كنت تنشئ ملفات Word أيضاً فاستخدم الألوان بصيغة hex أو `rgb()` أو `hsl()`، لأن Word يترك أسماء الألوان.

كيف تستخدم القوالب الهوية:

- يطبع عرض السعر وأمر الشراء وإذن التسليم والسند وقسيمة الراتب والتقرير والخطاب بياناتِ `company` في ترويسة المستند.
- تطبع الفاتورة والإشعار الدائن البائعَ من `seller` في بياناتك، وتكمل كل بيان ناقص له من `company`. فإذا كانت الشركة في الإعدادات، يمكنك حذف `seller`.
- الجهة المانحة للشهادة هي اسم الشركة افتراضياً.
- تأخذ الفاتورة الإلكترونية المصرية والعقد أطرافهما من البيانات (`issuer` و`parties`).
- تعرض كل القوالب الشعار `logo` متى ضُبط.

ولتمييز المستندات بهوية لكل عميل أو مستأجر، مرّر هويته مع كل مستند. راجع حالة الاستخدام [هوية مختلفة لكل عميل](/ar/recipes/multi-tenant-branding). لا تُقرأ ملفات الشعار افتراضياً إلا من `public/` و`storage/app` و`resources/`، ولا تُقرأ الروابط إلا من النطاقات التي تسمح بها. راجع [الصور](/ar/guide/images).

## التسميات واللغات {#labels}

الكلمات التي يطبعها القالب (العناوين، ورؤوس الأعمدة، و"الإجمالي المستحق") تأتي من مجلد `lang` الخاص به: `lang/ar.php` و`lang/en.php`. تختار اللغة الملف بحسب لغتها الأساسية، فاللغات `ar` و`ar_EG` و`ar-SA` كلها تستخدم `lang/ar.php`. وأي تسمية ناقصة في هذا الملف تؤخذ من `lang/en.php`.

وتعمل أي لغة أخرى أيضاً. فاستدعاء `->locale('fr')` ينشئ مستنداً من اليسار إلى اليمين بالتسميات الإنجليزية، و`->locale('ur')` مستنداً من اليمين إلى اليسار. ولطباعة تسميات فرنسية، انسخ القالب (انظر أدناه) وأضف `lang/fr.php`.

تُطبع بياناتك كما تعطيها: تترجم الحزمة التسميات، لا أسماء أصنافك.

## عرض قائمة القوالب {#doc-templates}

```bash
php artisan doc:templates
```

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| my-invoice     | Tax invoice        | ar, en  | PDF, Word | project |
| certificate    | Certificate        | ar, en  | PDF, Word | package |
| contract       | Contract           | ar, en  | PDF, Word | package |
| credit-note    | Credit note        | ar, en  | PDF, Word | package |
| delivery-note  | Delivery note      | ar, en  | PDF, Word | package |
| eg-invoice     | Egyptian e-invoice | ar, en  | PDF, Word | package |
| invoice        | Tax invoice        | ar, en  | PDF, Word | package |
| letter         | Official letter    | ar, en  | PDF, Word | package |
| payslip        | Payslip            | ar, en  | PDF, Word | package |
| purchase-order | Purchase order     | ar, en  | PDF, Word | package |
| quotation      | Price quotation    | ar, en  | PDF, Word | package |
| receipt        | Receipt voucher    | ar, en  | PDF, Word | package |
| report         | Table report       | ar, en  | PDF, Word | package |
+----------------+--------------------+---------+-----------+---------+
```

يبين العمود `Source` هل القالب من الحزمة أم من تطبيقك (وهو هنا نسخة باسم `my-invoice`). وتظهر قوالب المشروع أولاً. وفي الكود، تعيد `Doc::templates()->all()` القائمة نفسها.

## نسخ قالب لتعديله {#copy}

لتعديل قالب مرفق، انسخه إلى تطبيقك باسم جديد:

```bash
php artisan doc:template invoice --as=my-invoice
```

ينسخ هذا الأمر المجلد كله إلى `resources/doc-templates/my-invoice/` (أول مجلد في `templates.paths`): ‏`template.php` و`pdf.blade.php` و`word.php` و`footer.blade.php` و`lang/`. عدّل ما شئت منها، ثم استخدم الاسم الجديد:

```php
Doc::template('my-invoice', $data)->locale('ar')->pdf();
```

تشرح صفحة [قوالبك الخاصة](/ar/guide/custom-templates) كل ملف في المجلد.

## استبدال قالب مرفق {#override}

انسخ القالب باسمه نفسه، فتحل نسختك محل الأصل في التطبيق كله: في كل استدعاء `Doc::template('invoice')`، وفي صفحة المعاينة، وفي `doc:sample`.

```bash
php artisan doc:template invoice
```

فمثلاً، لتغيير العنوان العربي لكل الفواتير، عدّل `resources/doc-templates/invoice/lang/ar.php` وغيّر سطراً واحداً، مع إبقاء البقية:

```php
'title' => 'فاتورة ضريبية مبسطة',
```

إذا شغّلت الأمر مرة أخرى يتوقف بالرسالة "already exists. Use --force to overwrite it.". ومع `--force` ينسخ ملفات الأصل الموجود في الحزمة فوق نسختك من جديد، وتبقى الملفات التي أضفتها أنت إلى المجلد. وللعودة إلى القالب المرفق نهائياً، احذف المجلد.

::: tip ملاحظة
لا تتلقى النسخة المنسوخة من القالب الإصلاحات التي تأتي مع تحديثات الحزمة. فلا تنسخ إلا القوالب التي تعدّلها فعلاً، وراجع [سجل التغييرات](https://github.com/BiztechEG/laravel-easy-pdf-word/blob/main/CHANGELOG.md) عند تحديث الحزمة.
:::

## معرض القوالب {#gallery}

يعرض [معرض القوالب](/ar/templates/) كل قالب بمعاينة عربية وأخرى إنجليزية. وتسرد صفحة كل قالب حقوله كلها، والمطلوب منها، وقيمها الافتراضية، والقيم المحسوبة، والتسميات، ومثالاً كاملاً. ولتجربتها بإعداداتك، افتح [صفحة المعاينة](/ar/guide/preview) على `/doc-preview` في بيئتك المحلية.
