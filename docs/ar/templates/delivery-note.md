# إذن تسليم

قالب `delivery-note` هو إذن التسليم الذي يرافق البضاعة: ما الذي سُلِّم، ولمن، وأين، بلا أسعار. يمكنه أن يعرض الكميات المطلوبة والمتبقية في التسليم الجزئي، وبيانات السائق والسيارة، وينتهي بإقرار المستلم وخانات التوقيع.

<div class="preview">
  <figure><a href="/samples/delivery-note-ar.pdf" target="_blank"><img src="/previews/delivery-note-ar.png" alt="إذن تسليم عربي من قالب delivery-note"></a><figcaption>عربي (PDF)</figcaption></figure>
  <figure><a href="/samples/delivery-note-en.pdf" target="_blank"><img src="/previews/delivery-note-en.png" alt="إذن تسليم إنجليزي من قالب delivery-note"></a><figcaption>إنجليزي (PDF)</figcaption></figure>
</div>

## متى تستخدمه {#when-to-use}

- مخزن يشحن طلباً إلى فرع العميل، ويعود السائق بالإذن موقّعاً.
- يُسلَّم جزء من الطلب الآن والباقي الأسبوع القادم، فيعرض الإذن ما تبقى.
- تنتقل بضاعة بين فروعك أو مخازنك ويوقّع الطرفان.
- فني يسلّم أجهزة في الموقع ويسجل أنها شُغِّلت أمام المستلم.
- عميل يطلب الإذن ملف Word ليحفظه مع سجلات الاستلام لديه.

## مثال سريع {#example}

إذن التسليم الذي في المعاينة أعلاه، وشركتك في رأسه، محفوظاً ملف PDF وملف Word:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$note = Doc::template('delivery-note', [
    'delivery' => [
        'number' => 'DN-2026-0731',
        'date' => '2026-10-08',
        'order_number' => 'SO-2026-0219',
        'invoice_number' => 'INV-2026-1024',
    ],
    'customer' => [
        'name' => 'مؤسسة النور للتجارة',
        'phone' => '+20 122 555 0100',
        'address' => 'مدينة نصر، القاهرة',
    ],
    'ship_to' => [
        'address' => 'فرع مدينة نصر، 22 شارع عباس العقاد، القاهرة',
        'contact' => 'أحمد عبد الرحمن',
        'phone' => '+20 122 555 0101',
    ],
    'items' => [
        ['code' => 'LAP-14-I5', 'description' => 'لابتوب 14 بوصة Core i5', 'unit' => 'جهاز', 'ordered' => 10, 'quantity' => 10],
        ['code' => 'MON-24', 'description' => 'شاشة 24 بوصة', 'unit' => 'جهاز', 'ordered' => 10, 'quantity' => 6, 'notes' => 'الباقي خلال أسبوع'],
        ['code' => 'KB-AR-EN', 'description' => 'لوحة مفاتيح عربي/إنجليزي', 'unit' => 'قطعة', 'ordered' => 10, 'quantity' => 10],
    ],
    'packages' => 14,
    'transport' => [
        'driver' => 'سامح فؤاد',
        'phone' => '+20 111 333 4444',
        'vehicle' => 'ن ق ط 4821',
    ],
    'notes' => 'تم فحص الأجهزة وتشغيلها أمام المستلم.',
])
    ->theme([
        // أو مرة واحدة للتطبيق كله، تحت "theme" في config/easy-pdf-word.php
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'phone' => '+20 100 000 0000',
        ],
    ])
    ->locale('ar');

// ملف PDF
$note->pdf()->save(storage_path('app/delivery-notes/DN-2026-0731.pdf'));

// الإذن نفسه ملف Word
$note->word()->save(storage_path('app/delivery-notes/DN-2026-0731.docx'));
```

في جدول الأصناف أعمدة الكود والصنف والوحدة والمطلوب والمسلَّم والمتبقي والملاحظات؛ وسطر الشاشات يعرض 6 مسلَّمة و4 متبقية. وتحته: 3 أصناف، وإجمالي الكمية المسلَّمة 26، وعدد الطرود 14، ثم بيانات النقل والملاحظات، وجملة «أقر أنا الموقِّع أدناه باستلام الأصناف الموضحة أعلاه كاملة وبحالة جيدة.» وخانات «أمين المخزن» و«السائق» و«المستلم».

وفي الـ controller يرسل `return $note->pdf()->download('DN-2026-0731.pdf');` الملف إلى المتصفح بدلاً من حفظه. انظر [الإخراج والتسليم](/ar/guide/output).

## الحقول {#fields}

| الحقل | إلزامي | النوع / القيم | القيمة الافتراضية | الوظيفة |
| --- | --- | --- | --- | --- |
| `delivery.number` | نعم | نص أو رقم | | رقم الإذن، أعلى الصفحة وفي تذييل الصفحة. |
| `delivery.date` | نعم | تاريخ | | تاريخ التسليم. |
| `delivery.order_number` | لا | نص | | أمر البيع، ويُطبع بعد «رقم أمر البيع» / "Sales order". |
| `delivery.invoice_number` | لا | نص | | رقم الفاتورة المرتبطة. |
| `customer.name` | نعم | نص | | العميل، في خانة «العميل» / "Customer". |
| `customer.phone` | لا | نص | | هاتف العميل، ويُكتب من اليسار إلى اليمين. |
| `customer.address` | لا | نص | | عنوان العميل. |
| `ship_to.address` | لا | نص | `customer.address` | مكان تسليم البضاعة، في خانة «مكان التسليم» / "Delivered to". |
| `ship_to.contact` | لا | نص | | من استلم البضاعة هناك، ويُطبع بعد «المستلم» / "Receiver". |
| `ship_to.phone` | لا | نص | | الهاتف في مكان التسليم. |
| `items` | نعم | مصفوفة، عنصر واحد على الأقل | | الأصناف المسلَّمة، مرقمة 1، 2، 3. |
| `items.*.code` | لا | نص | | كود الصنف؛ ويظهر عمود الكود فقط عندما يكون لصنف ما كود. |
| `items.*.description` | نعم | نص | | الصنف. |
| `items.*.unit` | لا | نص | | الوحدة، مثل «جهاز» أو «قطعة». |
| `items.*.quantity` | نعم | رقم، صفر أو أكثر | | الكمية المسلَّمة الآن، بخط عريض. |
| `items.*.ordered` | لا | رقم، صفر أو أكثر | | الكمية المطلوبة. عند وجودها يظهر عمودا المطلوب والمتبقي. |
| `items.*.notes` | لا | نص | | ملاحظة قصيرة على السطر؛ ويظهر عمود الملاحظات فقط عندما يكون لسطر ما ملاحظة. |
| `packages` | لا | عدد صحيح، صفر أو أكثر | | عدد الطرود، ويُطبع تحت الإجماليات. |
| `transport.driver` | لا | نص | | اسم السائق. |
| `transport.phone` | لا | نص | | هاتف السائق. |
| `transport.vehicle` | لا | نص | | رقم لوحة السيارة. |
| `notes` | لا | نص | | ملاحظات قبل الإقرار. |
| `signatures` | لا | مصفوفة نصوص | `storekeeper` و `driver` و `receiver` | خانات التوقيع بترتيبها. انظر [التوقيعات](#signatures). |

### القيم المحسوبة {#computed}

| القيمة | طريقة حسابها |
| --- | --- |
| `items.*.remaining` | `ordered` ناقص `quantity`، ولا تقل عن صفر؛ فقط للأسطر التي فيها `ordered` |
| `totals.items` | عدد الأسطر: «عدد الأصناف» / "Items" |
| `totals.quantity` | مجموع الكميات المسلَّمة: «إجمالي الكمية المسلَّمة» / "Total delivered" |
| `signatures` | الخانات الثلاث الافتراضية إن لم تمرر شيئاً |

### قيم الهوية {#theme}

| مفتاح الهوية | استخدامه |
| --- | --- |
| `company.name` و `company.address` و `company.phone` | شركتك أعلى الصفحة |
| `logo` | فوق اسم الشركة |
| `primary` | اسم الشركة والعنوان ورأس الجدول |
| `muted` | العنوان البريدي والعناوين الصغيرة وملاحظات الأسطر وخطوط التوقيع المنقطة |
| `border` | خانات العميل ومكان التسليم وبيانات النقل |

## الأشكال والخيارات {#options}

### التسليم الجزئي {#partial}

أعطِ كل سطر الكمية المطلوبة `ordered` فيضيف الإذن عمودي «المطلوب» / "Ordered" و«المتبقي» / "Remaining". وإن لم يكن `ordered` في أي سطر، يعرض الجدول المسلَّم فقط. والأمر نفسه لعمودي الكود والملاحظات: يظهر كل منهما فقط عندما يستخدمه سطر ما. إذن تسليم مختصر:

```php
$note = Doc::template('delivery-note', [
    'delivery' => ['number' => 'DN-2026-0732', 'date' => '2026-10-09'],
    'customer' => ['name' => 'مؤسسة النور للتجارة', 'address' => 'مدينة نصر، القاهرة'],
    'items' => [
        ['description' => 'شاشة 24 بوصة', 'unit' => 'جهاز', 'quantity' => 4],
    ],
    'signatures' => ['storekeeper', 'receiver', 'مندوب المبيعات'],
])->locale('ar');
```

هنا تكرر خانة «مكان التسليم» عنوان العميل، ولا يوجد قسم للنقل ولا سطر للطرود، وخانات التوقيع هي أمين المخزن والمستلم و«مندوب المبيعات».

### النقل {#transport}

عند وجود أي من `transport.driver` أو `transport.phone` أو `transport.vehicle` يظهر قسم «بيانات النقل» / "Transport" بالقيم التي مررتها.

### التوقيعات {#signatures}

يحدد `signatures` الخانات في أسفل الإذن. في كل خانة الدور، وسطر «الاسم» / "Name"، وسطر للتوقيع. ثلاثة أدوار تُترجم، وأي نص آخر يُطبع كما هو:

| القيمة | بالعربية | بالإنجليزية |
| --- | --- | --- |
| `storekeeper` | أمين المخزن | Storekeeper |
| `driver` | السائق | Driver |
| `receiver` | المستلم | Received by |

مرّر `'signatures' => []` لإذن بلا خانات توقيع. أما جملة الإقرار فتُطبع دائماً.

### بلا أسعار {#no-prices}

ليس في القالب أي حقل للأسعار، فيمكن أن يرافق الإذن البضاعة. اطبع الأسعار في [الفاتورة الضريبية](/ar/templates/invoice) وأشر إليها بـ `delivery.invoice_number`.

## ملف Word {#word}

يبني `layout.php` ملف PDF وملف Word معاً، فلهما المحتوى نفسه بالترتيب نفسه. ويحتفظ تذييل الصفحة بالعنوان ورقم الإذن و«صفحة X من Y» بحقول أرقام صفحات Word.

ملف Word بلا علامة مائية، ويرفض `->word()` أي مستند عليه `->password()`. ملفات Word تحتاج الحزمة `phpoffice/phpword`؛ انظر [ملفات Word](/ar/guide/word).

## خصّصه {#customise}

```bash
php artisan doc:template delivery-note --as=my-delivery-note
```

ينسخ هذا الأمر القالب إلى `resources/doc-templates/my-delivery-note`. استخدمه بـ `Doc::template('my-delivery-note', $data)` وعدّل:

- `lang/ar.php` و `lang/en.php` للعناوين: جملة الإقرار تحت `acknowledgement`، والجملة الافتتاحية تحت `intro`، والأدوار تحت `signatures`.
- `template.php` للحقول وحساب الكميات المتبقية والتوقيعات الافتراضية في `prepare()`.
- `layout.php` لتخطيط الصيغتين، مثلاً لإضافة عمود للوزن.
- `footer.html.php` لتذييل الصفحة.

انظر [قوالبك الخاصة](/ar/guide/custom-templates).

## صفحات ذات صلة {#related}

- القوالب: [أمر شراء](/ar/templates/purchase-order)، [الفاتورة الضريبية](/ar/templates/invoice)، [سند قبض وسند صرف](/ar/templates/receipt)
- حالات الاستخدام: [تنزيل أو عرض أو حفظ](/ar/recipes/controller-responses)، [هوية مختلفة لكل عميل](/ar/recipes/multi-tenant-branding)، [اختبار ميزات المستندات](/ar/recipes/testing-documents)
