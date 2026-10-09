# بناء المستند بالكود

يصف `Doc::make()` المستند عنصراً بعد عنصر: عناوين وفقرات وجداول وصور ورموز QR. والوصف نفسه ينتج ملف PDF وملف Word، فيناسب المستندات التي تُجمع من بياناتك، مثل كشوف الحساب والتقارير وقوائم الأسعار. تسرد هذه الصفحة كل عنصر وخيار وتنسيق.

## مستند أول {#first}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$report = Doc::make()
    ->heading('تقرير المبيعات')
    ->paragraph([['text' => 'الفترة: ', 'bold' => true], 'سبتمبر 2026'])
    ->table([
        ['الفرع', 'الطلبات', 'الإيرادات'],
        ['القاهرة', '1,240', '486,500.75'],
        ['الجيزة', '980', '371,200.00'],
    ], ['header' => true, 'columns' => [50, 20, ['width' => 30, 'align' => 'end']]])
    ->locale('ar');

$report->pdf()->download('تقرير-المبيعات.pdf');
$report->word()->download('تقرير-المبيعات.docx');
```

تُرسم العناصر بالترتيب الذي تضيفها به. ويمكن أن تأتي الإعدادات مثل `->locale()` في أي موضع من السلسلة. وتُهرَّب كل قيمة، فطباعة البيانات القادمة من المستخدمين آمنة.

## مثال كامل {#example}

كشف حساب عميل فيه شعار، وكتلة ترويسة، وجدول حركات برصيد افتتاحي وصف للإجماليات، ومربع ملخص فيه المبلغ كتابةً ورمز QR، وملاحظة، وتذييل صفحة بأرقام الصفحات:

<div class="preview">
  <figure><img src="/images/guide-a/builder-statement-ar.png" alt="كشف حساب عميل بالعربية مبني بـ Doc::make()"><figcaption>ملف PDF الذي ينتجه هذا الكود</figcaption></figure>
</div>

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;
use BiztechEG\EasyPdfWord\Facades\Doc;

$transactions = [
    ['date' => '2026/09/03', 'description' => 'فاتورة مبيعات', 'reference' => 'INV-2026-0981', 'debit' => 8550.00, 'credit' => 0],
    ['date' => '2026/09/10', 'description' => 'تحصيل نقدي', 'reference' => 'RCT-2026-0412', 'debit' => 0, 'credit' => 10000.00],
    ['date' => '2026/09/18', 'description' => 'فاتورة مبيعات', 'reference' => 'INV-2026-1003', 'debit' => 4275.50, 'credit' => 0],
    ['date' => '2026/09/25', 'description' => 'إشعار دائن', 'reference' => 'CN-2026-0033', 'debit' => 0, 'credit' => 1500.00],
    ['date' => '2026/09/28', 'description' => 'تحويل بنكي', 'reference' => 'TRF-77120', 'debit' => 0, 'credit' => 6000.00],
];

$amount = fn (float $value) => $value > 0 ? number_format($value, 2) : '-';
$balance = 12500.00;

// The header row, then the opening balance across five columns.
$rows = [
    ['التاريخ', 'البيان', 'المرجع', 'مدين', 'دائن', 'الرصيد'],
    [['text' => 'رصيد أول المدة', 'colspan' => 5, 'italic' => true], number_format($balance, 2)],
];

foreach ($transactions as $row) {
    $balance += $row['debit'] - $row['credit'];

    $rows[] = [
        $row['date'],
        $row['description'],
        ['text' => $row['reference'], 'ltr' => true],
        $amount($row['debit']),
        $amount($row['credit']),
        number_format($balance, 2),
    ];
}

// The last row: totals, printed bold with 'footer' => true.
$rows[] = [
    ['text' => 'الإجمالي', 'colspan' => 3],
    number_format(array_sum(array_column($transactions, 'debit')), 2),
    number_format(array_sum(array_column($transactions, 'credit')), 2),
    number_format($balance, 2),
];

$statement = Doc::make()
    ->image(public_path('images/logo.png'), 35)
    ->heading('كشف حساب عميل')
    ->paragraph([['text' => 'العميل: ', 'bold' => true], 'مؤسسة النور للتجارة'], ['space_after' => 1])
    ->paragraph([['text' => 'رقم الحساب: ', 'bold' => true], ['text' => 'ACC-10457', 'ltr' => true]], ['space_after' => 1])
    ->paragraph([['text' => 'الفترة: ', 'bold' => true], 'من 2026/09/01 إلى 2026/09/30'])
    ->line()
    ->table($rows, [
        'header' => true,
        'footer' => true,
        'striped' => '#F9FAFB',
        'font_size' => 9.5,
        'columns' => [
            14,
            22,
            18,
            ['width' => 15, 'align' => 'end'],
            ['width' => 15, 'align' => 'end'],
            ['width' => 16, 'align' => 'end'],
        ],
    ])
    ->spacer(4)
    ->table([[
        [
            'lines' => [
                ['text' => 'الرصيد المستحق', 'color' => '#6B7280'],
                ['text' => number_format($balance, 2).' ج.م', 'bold' => true, 'size' => 16, 'color' => '#0F766E'],
                ['text' => Arabic::tafqeet($balance, 'EGP', only: true), 'size' => 9],
            ],
            'border' => '#0F766E',
        ],
        ['qr' => 'https://biztech.example/statements/ACC-10457/2026-09', 'width' => 26, 'align' => 'center'],
    ]], ['columns' => [72, 28], 'borders' => false])
    ->paragraph(
        'يُرجى مراجعة هذا الكشف وإبلاغنا بأي ملاحظات خلال 15 يوماً من تاريخه، وإلا اعتُبر الرصيد صحيحاً.',
        ['size' => 9, 'color' => '#6B7280', 'align' => 'justify', 'line_height' => 1.5],
    )
    ->locale('ar')
    ->footer('<div style="text-align: center; font-size: 8pt; color: #6B7280;">كشف حساب <bdo dir="ltr">ACC-10457</bdo> - صفحة {page} من {pages}</div>');

$statement->pdf()->download('كشف-حساب-ACC-10457.pdf');
$statement->word()->download('كشف-حساب-ACC-10457.docx');
```

ولملف Word المحتوى نفسه: فقرات من اليمين إلى اليسار، والجدول مرتب من اليمين مع تكرار صف رؤوسه في كل صفحة، والخلايا المدمجة، ومربع الملخص، وصورة رمز QR، وأرقام صفحات Word في التذييل.

## العناصر {#blocks}

| العنصر | القيم الافتراضية | ما يضيفه |
| --- | --- | --- |
| `heading($text, $level = 1, $style = [])` | المستوى 1 | عنوان. المستويات 1 و2 و3 بأحجام 18 و14 و12 نقطة، بخط عريض، ويأخذ المستوى 1 لون `primary` من الهوية. ويبقى العنوان في صفحة العنصر الذي يليه نفسها. |
| `paragraph($text, $style = [])` | | فقرة: نص، أو قائمة من المقاطع (runs، انظر أدناه). |
| `table($rows, $options = [])` | | جدول: قائمة صفوف، كل صف قائمة خلايا. راجع [الجداول](#tables). |
| `image($source, $widthMm = 40, $align = 'start')` | 40 مم، البداية | صورة من مسار ملف أو رابط مسموح به أو data URI. |
| `qr($value, $sizeMm = 30, $align = 'start')` | 30 مم، البداية | رمز QR لأي نص أو رابط. |
| `spacer($heightMm = 5)` | 5 مم | مسافة رأسية فارغة. |
| `line($color = null)` | `border` من الهوية | خط أفقي رفيع. |
| `pageBreak()` | | يبدأ صفحة جديدة. |

قيمة `$align` هي `start` أو `end` أو `center`. والبداية `start` هي الجانب الأيمن في العربية والأيسر في الإنجليزية، فيعمل الكود نفسه في الاتجاهين.

```php
Doc::make()
    ->heading('عقد تقديم خدمات')
    ->heading('البند الأول: التمهيد', 2)
    ->paragraph('يلتزم الطرف الأول بتنفيذ الأعمال الموضحة في عرض السعر المرفق.', ['align' => 'justify', 'line_height' => 1.5, 'space_after' => 3])
    ->image(public_path('images/logo.png'), 30, 'center')
    ->qr('https://biztech.example/verify/CT-2026-0031', 25, 'end')
    ->spacer(10)
    ->line('#0F766E')
    ->pageBreak()
    ->paragraph('الصفحة الثانية')
    ->locale('ar');
```

تتبع الصور القواعد نفسها المتبعة في كل الحزمة: الملفات المحلية من المجلدات المسموح بها فقط، والروابط من النطاقات المسموح بها فقط، وتُسقط صور SVG من ملفات Word. راجع [الصور](/ar/guide/images).

## الفقرات والمقاطع {#runs}

الفقرة نص، أو قائمة من **المقاطع** (runs): أجزاء من النص لكل منها تنسيقه. والمقطع نص أو مصفوفة فيها `text` ومفاتيح التنسيق:

```php
->paragraph([
    ['text' => 'المبلغ: ', 'bold' => true],
    ['text' => '71,250.00', 'color' => '#0F766E'],
    ' جنيه مصري، ',
    ['text' => 'شامل الضريبة', 'italic' => true, 'size' => 9],
])
```

المعامل الثاني ينسّق الفقرة كلها: `->paragraph('نص', ['align' => 'justify', 'size' => 11])`. وتنسيق المقطع نفسه يتقدم على تنسيق الفقرة. وفاصل السطر في النص (`"السطر الأول\nالسطر الثاني"`) يبدأ سطراً جديداً في الصيغتين.

## التنسيقات {#styles}

| المفتاح | يُستخدم في | ماذا يفعل |
| --- | --- | --- |
| `bold` | العناوين والفقرات والمقاطع والخلايا | `true` للخط العريض |
| `italic` | العناوين والفقرات والمقاطع والخلايا | `true` للخط المائل، إن كان للخط نسخة مائلة |
| `size` | العناوين والفقرات والمقاطع والخلايا | حجم الخط بالنقاط |
| `color` | العناوين والفقرات والمقاطع والخلايا | لون النص بصيغة hex: ‏`#0F766E` |
| `align` | العناوين والفقرات والخلايا وأسطر الخلايا | `start` أو `end` أو `center` أو `justify` |
| `ltr` | العناوين والفقرات والمقاطع والخلايا | `true` يُبقي رقم الهاتف أو الكود أو البريد الإلكتروني بترتيب من اليسار إلى اليمين داخل النص العربي |
| `space_after` | الفقرات | المسافة أسفل الفقرة بالمليمتر |
| `line_height` | الفقرات | تباعد الأسطر: `1.5` يعني سطراً ونصفاً |
| `background` | الخلايا | لون الخلفية |
| `border` | الخلايا | لون، يرسم إطاراً حول الخلية |
| `colspan` | الخلايا | عدد الأعمدة التي تمتد عليها الخلية |

استخدم الألوان بصيغة hex: فملف Word لا يقرأ غيرها، بينما يقبل ملف PDF أيضاً `rgb()` وأسماء الألوان. وتُتجاهل الألوان غير الصحيحة.

نادراً ما تحتاج إلى `ltr` مع الأرقام: فالعدد السالب مثل `-2.5` يُبقي إشارة السالب في مقدمته داخل النص العربي تلقائياً، والنطاقات مثل `2020 - 2021` تبقى كما كُتبت. استخدمه للقيم التي تمزج الحروف والأرقام والرموز، مثل `INV-2026-1024` أو `+20 100 000 0000`.

## الجداول {#tables}

يأخذ `table($rows, $options)` قائمة صفوف. والخيارات:

| الخيار | القيمة الافتراضية | ماذا يفعل |
| --- | --- | --- |
| `header` | `false` | الصف الأول صف رؤوس: بخط عريض وملون، ويتكرر أعلى كل صفحة. |
| `header_background` | `primary` من الهوية | خلفية صف الرؤوس |
| `header_color` | `#FFFFFF` | لون نص صف الرؤوس |
| `borders` | `true` | خط تحت كل صف. و`false` لجدول تنسيق بلا خطوط. |
| `border_color` | `border` من الهوية | لون هذه الخطوط |
| `striped` | لا شيء | لون خلفية لصف بعد صف، مثل `#F9FAFB` |
| `footer` | `false` | الصف الأخير بخط عريض، للإجماليات |
| `font_size` | حجم خط المستند | حجم خط الجدول كله بالنقاط |
| `columns` | لا شيء | عنصر لكل عمود: عرض بالنسبة المئوية (`30`)، أو `['width' => 30, 'align' => 'end']` |

```php
->table([
    ['الصنف', 'الكمية', 'السعر'],
    ['ورق تصوير A4', '40', '950.00'],
    ['حبر طابعة', '10', '3,200.00'],
    ['كرسي مكتب', '6', '4,750.00'],
    [['text' => 'الإجمالي', 'colspan' => 2], '98,500.00'],
], [
    'header' => true,
    'header_background' => '#1F2937',
    'header_color' => '#FACC15',
    'border_color' => '#D1D5DB',
    'striped' => '#F3F4F6',
    'footer' => true,
    'font_size' => 10,
    'columns' => [50, ['width' => 20, 'align' => 'center'], ['width' => 30, 'align' => 'end']],
])
```

تنطبق محاذاة العمود `align` على كل خلاياه، ومنها خلية الرأس. والأعمدة التي بلا عرض تتقاسم المساحة المتبقية: بالتساوي في Word، وبحسب المحتوى في ملف PDF.

والجدول بلا حدود هو أيضاً طريقة وضع الأشياء جنباً إلى جنب، مثل كتلة الشركة بجانب بيانات المستند، كما في [المثال الكامل](#example).

### الخلايا {#cells}

الخلية نص أو مصفوفة:

| الخلية | ما تعرضه |
| --- | --- |
| `'1,250.00'` | نص |
| `['text' => '1,250.00', 'bold' => true, 'align' => 'end']` | نص منسق، بأي مفتاح من [التنسيقات](#styles) |
| `['text' => 'الإجمالي', 'colspan' => 2]` | خلية تمتد على عمودين |
| `['text' => 'مدفوع', 'background' => '#16A34A', 'color' => '#FFFFFF']` | خلية ملونة |
| `['text' => 'ملاحظة', 'border' => '#DC2626']` | خلية بإطار حولها |
| `['lines' => [...]]` | عدة أسطر في خلية واحدة (أدناه) |
| `['image' => $path, 'width' => 30]` | صورة، وعرضها بالمليمتر (30 افتراضياً) |
| `['qr' => $value, 'width' => 20]` | رمز QR، وعرضه بالمليمتر (30 افتراضياً) |

كل عنصر في `lines` سطر في الخلية. والسطر نص، أو مقطع منسق، أو قائمة مقاطع، أو صورة:

```php
['lines' => [
    ['text' => 'شركة بيزتك', 'bold' => true],
    'الدقي، الجيزة',
    ['الرقم الضريبي: ', ['text' => '123-456-789', 'ltr' => true]],
    ['image' => public_path('images/stamp.png'), 'width' => 25],
]]
```

## اللغة والإعدادات {#settings}

يقبل المستند المبني بالكود كل إعدادات المستند، في أي موضع من السلسلة:

```php
$priceList = Doc::make()
    ->heading('Price list')
    ->locale('en')                         // أو 'ar' من اليمين إلى اليسار
    ->numerals('arabic')                   // ١٢٣ في النص
    ->theme(['primary' => '#B45309'])      // لون العناوين ورؤوس الجداول
    ->font('tajawal')                      // خط ملف PDF
    ->paper('A5')->landscape()->margins(12)
    ->title('Price list 2026')             // يُخزَّن في ملف PDF وملف Word
    ->footer('<p>Page {page} of {pages}</p>');
```

ينطبق مقاس الورق واتجاهه والهوامش ورأس الصفحة وتذييلها والعنوان واللغة والأرقام وألوان الهوية على الصيغتين. أما الخط فينطبق على ملف PDF، وتستخدم ملفات Word خط Word، راجع [ملفات Word](/ar/guide/word#word-font). و`->watermark()` و`->password()` لملفات PDF فقط. راجع [إعدادات الصفحة](/ar/guide/page-settings) و[دعم اللغة العربية](/ar/guide/arabic).

## إضافة العناصر في حلقة {#loops}

تعيد كل دالة عنصر المستندَ نفسه، فيمكنك مواصلة الإضافة إليه داخل الحلقات والشروط:

```php
$report = Doc::make()->locale('ar')->heading('مبيعات الفروع');

foreach ($branches as $branch) {
    $report->heading($branch['name'], 2)
        ->table([['الشهر', 'المبيعات'], ...$branch['rows']], ['header' => true]);
}

return $report->pdf()->download('مبيعات-الفروع.pdf');
```

يأخذ `->pdf()` و`->word()` نسخة من المستند كما هو في تلك اللحظة: فالعناصر المضافة بعد ذلك لا تغيّر ملفاً طلبته بالفعل.

## الإخراج {#output}

| الاستدعاء | يعطي |
| --- | --- |
| `->pdf()` | ملف PDF: ‏`download()` و`stream()` و`save()` و`content()` |
| `->word()` | ملف Word بالدوال نفسها (يحتاج إلى `phpoffice/phpword`) |
| `->queue('reports/sales.docx', disk: 's3')` | ينشئ الملف ويحفظه على queue worker، ويحدد الامتداد الصيغة |
| `->toHtml()` | نص HTML الذي يُعطى لمحرك PDF، لتتبع الأخطاء |

راجع [الإخراج والتسليم](/ar/guide/output).

## داخل قالب {#in-templates}

يتلقى ملف `layout.php` في القالب كائن البناء نفسه، فكل ما في هذه الصفحة يعمل هناك أيضاً، مع تسميات القالب وبياناته التي جرى التحقق منها. وهكذا بُنيت معظم القوالب المرفقة. راجع [قوالبك الخاصة](/ar/guide/custom-templates#layout-php).
