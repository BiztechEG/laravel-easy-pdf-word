# قوالبك الخاصة

يعمل القالب الذي تنشئه بنفسك تماماً كالقوالب المرفقة: يتحقق `Doc::template('packing-list', $data)` من البيانات، ويختار التسميات بحسب اللغة، وينشئ ملف PDF وملف Word. تصف هذه الصفحة كل ملف في مجلد القالب، وتنتهي بمثال كامل.

## متى تنشئ قالباً {#when}

أنشئ قالباً لنوع مستند تصدره مرة بعد مرة ببيانات مختلفة: قائمة تعبئة، أو أمر عمل، أو بطاقة ضمان. تحصل بذلك على التحقق من البيانات، والتسميات بعدة لغات، والصيغتين، والبيانات التجريبية لـ [صفحة المعاينة](/ar/guide/preview) والأمر `doc:sample`، واسم واحد تستدعيه به.

أما المستند الذي تنشئه مرة واحدة، فملف [Blade](/ar/guide/views-and-html) أو [بناء المستند بالكود](/ar/guide/builder) أسرع. ولتعديل قالب مرفق، انسخه بالأمر `php artisan doc:template` بدلاً من ذلك (راجع [القوالب الجاهزة](/ar/guide/templates#copy)).

## إنشاء قالب {#create}

```bash
php artisan doc:make-template packing-list
```

ينشئ هذا الأمر المجلد `resources/doc-templates/packing-list/` من قالب بداية يعمل مباشرة:

```text
resources/doc-templates/packing-list/
├── template.php        title, fields, defaults, sample data
├── pdf.blade.php       the PDF layout, in Blade
├── word.php            the Word layout, in code
├── footer.blade.php    a page footer with page numbers
└── lang/
    ├── ar.php          Arabic labels
    └── en.php          English labels
```

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('packing-list', ['title' => 'قائمة التعبئة'])->locale('ar')->pdf();
```

يمكن أن يحتوي الاسم على حروف وأرقام ونقاط وشرطات وشرطات سفلية. وإذا كان اسمَ قالب مرفق، ينبهك الأمر إلى أن قالبك الجديد سيحل محل القالب المرفق في تطبيقك.

## مجلد القالب {#folder}

| الملف | مطلوب؟ | ما هو |
| --- | --- | --- |
| `template.php` | يُنصح به | العنوان، والحقول مع قواعد التحقق، والقيم الافتراضية، والقيم المحسوبة، والبيانات التجريبية |
| `pdf.blade.php` | يلزم تخطيط واحد على الأقل | تخطيط PDF بلغة Blade، مع HTML وCSS |
| `layout.php` | يلزم تخطيط واحد على الأقل | تخطيط واحد بالكود ينشئ ملف PDF وملف Word معاً |
| `word.php` | لا | تخطيط Word بالكود، يُستخدم بدلاً من `layout.php` لملفات Word |
| `word.docx` | لا | ملف Word مصمم في Word ومعه `${placeholders}`، يُقدَّم على كل ما عداه لملفات Word |
| `header.blade.php` | لا | رأس الصفحة، يتكرر في كل صفحة |
| `footer.blade.php` | لا | تذييل الصفحة، يتكرر في كل صفحة، ويمكنه طباعة `{page}` و`{pages}` |
| `lang/{language}.php` | لا | التسميات لكل لغة: `lang/ar.php` و`lang/en.php` و`lang/fr.php` ... |

يعثر `Doc::template()` على المجلد بمجرد أن يحتوي على `template.php` أو `pdf.blade.php` أو `layout.php` أو `word.php` أو `word.docx`. لكنه لا يظهر في `doc:templates` ولا في صفحة المعاينة إلا إذا احتوى على `template.php`.

## الملف template.php {#template-php}

يعيد `template.php` مصفوفة، وكل مفاتيحها اختيارية:

| المفتاح | القيمة الافتراضية | ماذا يفعل |
| --- | --- | --- |
| `title` | اسم المجلد | الاسم الذي يعرضه `doc:templates` وصفحة المعاينة. وهو أيضاً عنوان المستند المخزّن في ملف PDF وملف Word، ما لم يُستدعَ `->title()`. |
| `description` | `''` | سطر يظهر في صفحة المعاينة. |
| `locales` | `['ar', 'en']` | اللغات التي يعرضها `doc:templates` وتتيحها صفحة المعاينة، وأولها هي لغة المعاينة الافتراضية. ولا تقيّد `->locale()`. |
| `paper` | `pdf.paper` في الإعدادات (`A4`) | اسم مقاس ورق مثل `A4` أو `A5` أو `Letter`، أو `[width, height]` بالمليمتر. و`->paper()` تتقدم عليه. |
| `orientation` | `pdf.orientation` في الإعدادات (`portrait`) | `portrait` أو `landscape`. و`->landscape()` و`->portrait()` تتقدمان عليه. |
| `margins` | `pdf.margins` في الإعدادات (15 مم) | بالمليمتر: `[top, right, bottom, left]`. والقوائم الأقصر تعمل كما في `->margins()`: ‏`[15]` لكل الجهات، و`[15, 12]` للأعلى والأسفل ثم لليمين واليسار. و`->margins()` تتقدم عليه. |
| `fields` | `[]` | قواعد تحقق Laravel للبيانات، ومفاتيحها مسارات بالنقاط: `'items.*.quantity' => ['required', 'numeric']`. |
| `defaults` | `[]` | بيانات تُدمج تحت ما يمرره المطور، مفتاحاً بمفتاح، قبل التحقق. |
| `prepare` | لا شيء | `function (array $data, array $theme): array`. تعمل بعد التحقق وتعيد البيانات بعد إضافة القيم المحسوبة (الإجماليات، والترقيم، ورمز QR). ويمكنها رمي `ValidationException::withMessages()` لفحوص لا تعبّر عنها القواعد. |
| `sample` | `[]` | بيانات مثال لصفحة المعاينة والأمر `doc:sample` واختباراتك: مصفوفة، أو closure تعيد مصفوفة (مفيدة مع `now()`). |
| `theme` | `[]` | قيم هوية لهذا القالب وحده، مثل لون `primary` خاص به. تقع بين هوية الإعدادات و`->theme()`. |

تصل البيانات إلى التخطيط بهذا الترتيب: بياناتك، بعد دمج `defaults` تحتها، تُفحص مقابل `fields`، ثم تمر عبر `prepare`. ويتلقى التخطيط الناتج.

## تخطيط PDF: الملف pdf.blade.php {#pdf-blade}

`pdf.blade.php` ملف view بلغة Blade. يتلقى `$doc`، أي [أدوات القوالب](/ar/reference/template-helpers)، وكل مفتاح من المستوى الأعلى في البيانات المجهزة متغيراً مستقلاً: فالبيانات التي فيها `shipment` و`customer` و`packages` تعطي `$shipment` و`$customer` و`$packages`.

غلّف الصفحة بمكوّن التخطيط، الذي يضبط الاتجاه والخط والتنسيقات الأساسية، وأضف CSS الخاص بك في الـ slot المسمى `styles`:

```blade
<x-doc::layout :doc="$doc" :title="$doc->t('title')">
    <x-slot:styles>
        <style>
            .title { font-size: 18pt; color: {{ $doc->theme('primary') }}; }
        </style>
    </x-slot:styles>

    <h1 class="title">{{ $doc->t('title') }}</h1>
    <p>{{ $doc->t('customer') }}: {{ $customer['name'] }}</p>
</x-doc::layout>
```

يجب أن يعمل CSS في mPDF الذي يدعم CSS 2.1: نسّق الصفحة بالجداول لا بـ flexbox أو grid. وتشرح صفحة [ملفات Blade و HTML](/ar/guide/views-and-html) مكوّن التخطيط والأدوات وCSS الذي يعمل على كل المحركات. وفي [المثال الكامل](#blade-version) أدناه ملف `pdf.blade.php` كامل.

## التسميات: ملفات lang {#lang}

يعيد كل ملف في `lang/` تسميات لغة واحدة:

```php
// lang/ar.php
return [
    'title' => 'قائمة التعبئة',
    'customer' => 'العميل',
    'greeting' => 'مرحباً :name، رقم طلبك :number',
    'units' => ['box' => 'صندوق', 'pallet' => 'طبلية'],
];
```

اقرأها بـ `$doc->t()` في ملفات Blade والتخطيطات، وبـ `${t.key}` في ملف `word.docx`:

```blade
{{ $doc->t('title') }}
{{ $doc->t('units.box') }}
{{ $doc->t('greeting', ['name' => $customer['name'], 'number' => $order['number']]) }}
```

تُقرأ التسميات المتداخلة بالنقاط، وتُستبدل المتغيرات مثل `:name` بالقيم التي تمررها. يُختار الملف بحسب لغة الـ locale (فاللغة `ar_EG` تقرأ `lang/ar.php`). والتسمية الناقصة هناك تؤخذ من `lang/en.php`، أما التسمية الناقصة في الملفين فيُطبع مفتاحها، فيسهل اكتشاف الأخطاء الإملائية.

## رأس الصفحة وتذييلها {#header-footer}

`header.blade.php` و`footer.blade.php` ملفا Blade يتلقيان `$doc` ومتغيرات البيانات نفسها التي يتلقاها التخطيط. يُرسمان في كل صفحة، ويتحول `{page}` و`{pages}` إلى رقم الصفحة وعدد الصفحات:

```blade
{{-- footer.blade.php --}}
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $doc->ltr($shipment['number']) }}</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
```

ما يجب معرفته:

- يقعان في هوامش الصفحة. فرأس الصفحة الأطول من سطر أو سطرين يحتاج إلى هامش علوي أكبر، مثل `'margins' => [28, 15, 15, 15]`.
- استخدم التنسيقات المضمّنة (inline styles). يرسم Chromium رأس الصفحة وتذييلها بمعزل عن الصفحة، فلا يصل إليهما CSS الموجود في `pdf.blade.php` هناك.
- تتبع الأرقام `->numerals()`. لكن Chromium يطبع `{page}` و`{pages}` بالأرقام اللاتينية حتى مع الأرقام العربية.
- في ملفات Word، يصبح رأس الصفحة وتذييلها سطراً واحداً من نص صغير في الوسط، مع أرقام صفحات Word حقيقية. وتُسقط تنسيقاتهما وجداولهما وصورهما.
- يستبدل `->header($html)` و`->footer($html)` على المستند ملفاتِ القالب لذلك المستند.

## تخطيط واحد لـ PDF وWord: الملف layout.php {#layout-php}

يعيد `layout.php` دالة تضيف العناصر إلى المستند، بالعناصر نفسها التي يستخدمها [`Doc::make()`](/ar/guide/builder):

```php
<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;

return function (DocumentBuilder $list, array $data, DocContext $doc): void {
    $list->heading($doc->t('title'));
    $list->paragraph([['text' => $doc->t('customer').': ', 'bold' => true], $data['customer']['name']]);
};
```

تتلقى الدالة كائن بناء المستند، والبيانات المجهزة، و`$doc` بالأدوات نفسها المتاحة في Blade. وتخطيط واحد ينتج الصيغتين، وهكذا بُنيت قوالب عرض السعر وأمر الشراء وإذن التسليم والإشعار الدائن والسند وقسيمة الراتب والعقد والفاتورة الإلكترونية المصرية.

وللملف `word.php` الشكل نفسه تماماً. استخدمه بجانب `pdf.blade.php` عندما يُصمَّم ملف PDF بلغة Blade وملف Word بالكود، كما في قوالب الفاتورة والخطاب والتقرير والشهادة.

### أي ملف ينتج أي صيغة {#precedence}

| | PDF | Word |
| --- | --- | --- |
| الخيار الأول | `pdf.blade.php` | `word.docx` |
| ثم | `layout.php` | `word.php` |
| ثم | `word.php` | `layout.php` |

وبذلك:

- `layout.php` وحده ينتج الصيغتين من تخطيط واحد.
- `pdf.blade.php` مع `layout.php` أو `word.php`: ملف Blade لملف PDF، والكود لملف Word.
- `word.docx` ينتج ملف Word أياً كان ما بجانبه. راجع [ملفات Word](/ar/guide/word#word-docx).
- `pdf.blade.php` وحده لا ينتج إلا ملفات PDF. وعندها يرمي `->word()` الاستثناء `WordNotSupported`: "Template [packing-list] has no Word layout. Add layout.php, word.php or word.docx to its folder."

اختر `layout.php` عندما يكفي تخطيط واحد للصيغتين، وهو ما يكفي غالباً لمستندات الأعمال المبنية من الجداول. واختر `pdf.blade.php` عندما يحتاج ملف PDF إلى تصميم لا تعبّر عنه إلا بـ HTML وCSS، مع قبول الاحتفاظ بتخطيط ثانٍ لملف Word. واختر `word.docx` عندما يصمم ملفَ Word شخصٌ لا يكتب الكود.

## أين يُبحث عن القوالب {#paths}

يُبحث عن القوالب في المجلدات المدرجة تحت `templates.paths` في `config/easy-pdf-word.php` بالترتيب، ثم في الحزمة. وأول مجلد يحتوي على الاسم هو الذي يُستخدم.

```php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
        base_path('modules/Shipping/doc-templates'),
    ],
],
```

يكتب الأمران `doc:make-template` و`doc:template` في المجلد الأول. ويمكن لحزمة أو وحدة (module) أيضاً إضافة مجلد من service provider، ويُبحث فيه أولاً ما لم تمرر `first: false`:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

public function boot(): void
{
    Doc::templates()->addPath(base_path('modules/Shipping/doc-templates'));
}
```

## استبدال قالب مرفق {#override}

لأن مجلداتك يُبحث فيها قبل الحزمة، فإن القالب الموجود في مجلدك باسم قالب مرفق يحل محله في التطبيق كله. والطريقة المعتادة نسخ القالب المرفق بالأمر `php artisan doc:template invoice` وتعديل النسخة. راجع [القوالب الجاهزة](/ar/guide/templates#override).

## مثال كامل: قائمة تعبئة {#example}

قائمة تعبئة (Packing list) لشحنة: الشركة، وبيانات الشحنة، والعميل، وجدول بالطرود مع صف للإجماليات، وملاحظات وتوقيعان، بالعربية والإنجليزية، وبصيغتي PDF وWord.

<div class="preview">
  <figure><img src="/images/guide-a/packing-list-ar.png" alt="قالب قائمة التعبئة بالعربية"><figcaption>بالعربية (PDF)</figcaption></figure>
  <figure><img src="/images/guide-a/packing-list-en.png" alt="قالب قائمة التعبئة بالإنجليزية"><figcaption>بالإنجليزية (PDF)</figcaption></figure>
</div>

### 1. إنشاء المجلد {#example-create}

```bash
php artisan doc:make-template packing-list
```

يستخدم هذا المثال تخطيطاً واحداً للصيغتين، فاحذف `pdf.blade.php` و`word.php` من المجلد الجديد، وستضيف `layout.php`.

### 2. الملف template.php {#example-template}

الحقول، وخطوة `prepare` تضيف الإجماليات، والبيانات التجريبية:

```php
<?php

// resources/doc-templates/packing-list/template.php

return [
    'title' => 'Packing list',
    'description' => 'The packages in a shipment: contents, quantities, weights and sizes.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'margins' => [15, 15, 20, 15],

    'fields' => [
        'shipment.number' => ['required', 'string'],
        'shipment.date' => ['required', 'date'],
        'shipment.order_number' => ['nullable', 'string'],
        'customer.name' => ['required', 'string'],
        'customer.address' => ['nullable', 'string'],
        'packages' => ['required', 'array', 'min:1'],
        'packages.*.contents' => ['required', 'string'],
        'packages.*.quantity' => ['required', 'integer', 'min:1'],
        'packages.*.weight' => ['required', 'numeric', 'min:0'],
        'packages.*.size' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
    ],

    'defaults' => [
        'notes' => null,
    ],

    // Adds the totals; the layout only prints them.
    'prepare' => function (array $data, array $theme): array {
        $data['packages'] = array_values($data['packages']);

        $data['totals'] = [
            'quantity' => array_sum(array_column($data['packages'], 'quantity')),
            'weight' => round(array_sum(array_column($data['packages'], 'weight')), 2),
        ];

        return $data;
    },

    'sample' => [
        'shipment' => ['number' => 'SHP-2026-0412', 'date' => '2026-10-08', 'order_number' => 'PO-2026-0057'],
        'customer' => ['name' => 'مؤسسة النور للتجارة', 'address' => 'المنطقة الصناعية الثانية، مدينة السادس من أكتوبر'],
        'packages' => [
            ['contents' => 'لابتوب 14 بوصة Core i5', 'quantity' => 4, 'weight' => 9.6, 'size' => '60 × 40 × 30'],
            ['contents' => 'شاشة 24 بوصة', 'quantity' => 6, 'weight' => 27, 'size' => '70 × 50 × 45'],
            ['contents' => 'لوحة مفاتيح عربي/إنجليزي', 'quantity' => 10, 'weight' => 6.5, 'size' => '50 × 30 × 25'],
        ],
        'notes' => 'يُرجى فحص الطرود عند الاستلام وتسجيل أي تلف على إذن التسليم.',
    ],
];
```

### 3. التسميات {#example-labels}

::: code-group

```php [lang/ar.php]
<?php

return [
    'title' => 'قائمة التعبئة',
    'number' => 'رقم الشحنة',
    'date' => 'التاريخ',
    'order_number' => 'رقم الطلب',
    'customer' => 'العميل',
    'contents' => 'المحتويات',
    'quantity' => 'الكمية',
    'weight' => 'الوزن (كجم)',
    'size' => 'المقاس (سم)',
    'total' => 'الإجمالي',
    'notes' => 'ملاحظات',
    'packed_by' => 'أعدّه',
    'received_by' => 'استلمه',
    'page' => 'صفحة',
    'of' => 'من',
];
```

```php [lang/en.php]
<?php

return [
    'title' => 'Packing List',
    'number' => 'Shipment no.',
    'date' => 'Date',
    'order_number' => 'Order no.',
    'customer' => 'Customer',
    'contents' => 'Contents',
    'quantity' => 'Qty',
    'weight' => 'Weight (kg)',
    'size' => 'Size (cm)',
    'total' => 'Total',
    'notes' => 'Notes',
    'packed_by' => 'Packed by',
    'received_by' => 'Received by',
    'page' => 'Page',
    'of' => 'of',
];
```

:::

### 4. الملف layout.php {#example-layout}

تأتي الشركة من [الهوية](/ar/guide/templates#theme)، فيعمل القالب نفسه مع كل علامة تجارية. والنص الذي يجب أن يبقى من اليسار إلى اليمين، مثل رقم الشحنة والمقاسات، مُعلَّم بـ `ltr`.

```php
<?php

// resources/doc-templates/packing-list/layout.php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;
use Illuminate\Support\Carbon;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $list, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary');
    $muted = $doc->theme('muted');
    $shipment = $data['shipment'];
    $logo = $doc->theme('logo');

    // The company on one side; the title and shipment details on the other.
    $details = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 18, 'color' => $primary],
        [$doc->t('number').': ', ['text' => $shipment['number'], 'ltr' => true]],
        $doc->t('date').': '.Carbon::parse($shipment['date'])->format('Y/m/d'),
    ];

    if (! empty($shipment['order_number'])) {
        $details[] = [$doc->t('order_number').': ', ['text' => $shipment['order_number'], 'ltr' => true]];
    }

    $list->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 13, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
        ]))],
        ['lines' => $details],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $list->spacer(4);

    // The customer.
    $list->paragraph([['text' => $doc->t('customer').': ', 'bold' => true], $data['customer']['name']]);

    if (! empty($data['customer']['address'])) {
        $list->paragraph($data['customer']['address'], ['color' => $muted]);
    }

    $list->spacer(2);

    // The packages, with a totals row.
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];
    $rows = [['#', $doc->t('contents'), $end($doc->t('quantity')), $end($doc->t('weight')), $doc->t('size')]];

    foreach ($data['packages'] as $i => $package) {
        $rows[] = [
            (string) ($i + 1),
            $package['contents'],
            (string) $package['quantity'],
            $doc->numberText($package['weight']),
            ['text' => $package['size'] ?? '', 'ltr' => true],
        ];
    }

    $rows[] = [
        ['text' => $doc->t('total'), 'colspan' => 2],
        (string) $data['totals']['quantity'],
        $doc->numberText($data['totals']['weight']),
        '',
    ];

    $list->table($rows, [
        'header' => true,
        'footer' => true,
        'striped' => '#F9FAFB',
        'columns' => [8, 44, ['width' => 12, 'align' => 'end'], ['width' => 16, 'align' => 'end'], 20],
    ]);

    if (! empty($data['notes'])) {
        $list->spacer(2);
        $list->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']]);
    }

    // Signatures.
    $list->spacer(12);
    $list->table([
        [['text' => $doc->t('packed_by'), 'bold' => true], ['text' => $doc->t('received_by'), 'bold' => true]],
        ['....................................', '....................................'],
    ], ['borders' => false]);
};
```

### 5. الملف footer.blade.php {#example-footer}

استبدل تذييل قالب البداية بتذييل فيه رقم الشحنة في جانب وأرقام الصفحات في الجانب الآخر:

```blade
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $doc->ltr($shipment['number']) }}</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
```

### 6. الاستخدام {#example-use}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$packingList = Doc::template('packing-list', [
    'shipment' => ['number' => 'SHP-2026-0412', 'date' => '2026-10-08', 'order_number' => 'PO-2026-0057'],
    'customer' => ['name' => 'مؤسسة النور للتجارة', 'address' => 'المنطقة الصناعية الثانية، مدينة السادس من أكتوبر'],
    'packages' => [
        ['contents' => 'لابتوب 14 بوصة Core i5', 'quantity' => 4, 'weight' => 9.6, 'size' => '60 × 40 × 30'],
        ['contents' => 'شاشة 24 بوصة', 'quantity' => 6, 'weight' => 27, 'size' => '70 × 50 × 45'],
        ['contents' => 'لوحة مفاتيح عربي/إنجليزي', 'quantity' => 10, 'weight' => 6.5, 'size' => '50 × 30 × 25'],
    ],
])->locale('ar');

$packingList->pdf()->save(storage_path('app/shipments/SHP-2026-0412.pdf'));
$packingList->word()->save(storage_path('app/shipments/SHP-2026-0412.docx'));
```

يعرض صف الإجماليات 20 قطعة و43.10 كجم، تحسبها خطوة `prepare`. ومرّر `->locale('en')` للنسخة الإنجليزية. ويظهر القالب أيضاً في `php artisan doc:templates`، وينشئه `php artisan doc:sample packing-list` ببياناته التجريبية، وتعرضه صفحة المعاينة بجانب القوالب المرفقة.

ويُبلَّغ عن أي خطأ في البيانات بحسب الحقل. فالطرد الذي فيه `'quantity' => 0` يفشل بالرسالة "The packages.0.quantity field must be at least 1."

### 7. اختياري: ملف PDF بلغة Blade {#blade-version}

لتصميم ملف PDF بـ HTML وCSS بدلاً من ذلك، أضف `pdf.blade.php`. عندها يستخدمه ملف PDF، ويبقى ملف Word قادماً من `layout.php`:

```blade
{{-- resources/doc-templates/packing-list/pdf.blade.php --}}
@php
    $company = (array) $doc->theme('company', []);
    $logo = $doc->image($doc->theme('logo'));
@endphp
<x-doc::layout :doc="$doc" :title="$doc->t('title').' '.$shipment['number']">
    <x-slot:styles>
        <style>
            .title { font-size: 18pt; font-weight: bold; color: {{ $doc->theme('primary') }}; }
            .packages { margin-top: 4mm; }
            .packages th { background-color: {{ $doc->theme('primary') }}; color: #FFFFFF; padding: 2mm; text-align: {{ $doc->start() }}; }
            .packages td { border-bottom: 1px solid {{ $doc->theme('border') }}; padding: 2mm; }
            .packages .num { text-align: {{ $doc->end() }}; }
            .packages .total td { font-weight: bold; }
        </style>
    </x-slot:styles>

    <table>
        <tr>
            <td style="width: 55%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="width: 30mm;"><br>
                @endif
                <strong>{{ $company['name'] ?? '' }}</strong>
                <div class="muted">{{ $company['address'] ?? '' }}</div>
            </td>
            <td style="width: 45%;">
                <div class="title">{{ $doc->t('title') }}</div>
                <div>{{ $doc->t('number') }}: {{ $doc->ltr($shipment['number']) }}</div>
                <div>{{ $doc->t('date') }}: {{ \Illuminate\Support\Carbon::parse($shipment['date'])->format('Y/m/d') }}</div>
            </td>
        </tr>
    </table>

    <p style="margin-top: 5mm;"><strong>{{ $doc->t('customer') }}:</strong> {{ $customer['name'] }}</p>

    <table class="packages">
        <thead>
            <tr>
                <th>#</th>
                <th>{{ $doc->t('contents') }}</th>
                <th class="num">{{ $doc->t('quantity') }}</th>
                <th class="num">{{ $doc->t('weight') }}</th>
                <th>{{ $doc->t('size') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($packages as $package)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $package['contents'] }}</td>
                    <td class="num">{{ $package['quantity'] }}</td>
                    <td class="num">{{ $doc->number($package['weight']) }}</td>
                    <td>{{ $doc->ltr($package['size'] ?? '') }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ $doc->t('total') }}</td>
                <td class="num">{{ $totals['quantity'] }}</td>
                <td class="num">{{ $doc->number($totals['weight']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</x-doc::layout>
```
