# Your own templates

A template of your own works exactly like the bundled ones: `Doc::template('packing-list', $data)` validates the data, picks the labels for the language, and makes a PDF and a Word file. This page describes every file in a template folder and ends with a complete example.

## When to make a template {#when}

Make a template for a document type you produce again and again with different data: a packing list, a work order, a warranty card. You get validation, labels in several languages, both formats, sample data for the [preview page](/guide/preview) and `doc:sample`, and one name to call it by.

For a one-off document, a [Blade view](/guide/views-and-html) or the [builder](/guide/builder) is quicker. To change a bundled template, copy it with `php artisan doc:template` instead (see [Ready-made templates](/guide/templates#copy)).

## Create one {#create}

```bash
php artisan doc:make-template packing-list
```

This creates `resources/doc-templates/packing-list/` from a starter that already works:

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

The name may use letters, digits, dots, dashes and underscores. If it is the name of a bundled template, the command warns that your new template replaces the bundled one in your app.

## The template folder {#folder}

| File | Required | What it is |
| --- | --- | --- |
| `template.php` | Recommended | The title, the fields with their validation rules, defaults, computed values and sample data |
| `pdf.blade.php` | One layout is needed | The PDF layout in Blade, with HTML and CSS |
| `layout.php` | One layout is needed | One layout in code that makes both the PDF and the Word file |
| `word.php` | No | A Word layout in code, used instead of `layout.php` for Word |
| `word.docx` | No | A Word file designed in Word with `${placeholders}`, used for Word before anything else |
| `header.blade.php` | No | A page header, repeated on every page |
| `footer.blade.php` | No | A page footer, repeated on every page; may print `{page}` and `{pages}` |
| `lang/{language}.php` | No | Labels for each language: `lang/ar.php`, `lang/en.php`, `lang/fr.php` ... |

A folder is found by `Doc::template()` as soon as it has `template.php`, `pdf.blade.php`, `layout.php`, `word.php` or `word.docx`. It is listed by `doc:templates` and on the preview page only when it has `template.php`.

## template.php {#template-php}

`template.php` returns an array. Every key is optional:

| Key | Default | What it does |
| --- | --- | --- |
| `title` | The folder name | The name shown by `doc:templates` and the preview page. Also the document title stored in the PDF and the Word file, unless `->title()` is called. |
| `description` | `''` | A line shown on the preview page. |
| `locales` | `['ar', 'en']` | The languages `doc:templates` lists and the preview page offers, the first being the preview's default. It does not limit `->locale()`. |
| `paper` | `pdf.paper` in the config (`A4`) | A paper name such as `A4`, `A5` or `Letter`, or `[width, height]` in millimetres. `->paper()` wins. |
| `orientation` | `pdf.orientation` in the config (`portrait`) | `portrait` or `landscape`. `->landscape()` and `->portrait()` win. |
| `margins` | `pdf.margins` in the config (15 mm) | Millimetres: `[top, right, bottom, left]`. Shorter lists work like `->margins()`: `[15]` for all sides, `[15, 12]` for top and bottom, then right and left. `->margins()` wins. |
| `fields` | `[]` | Laravel validation rules for the data, keyed by dotted paths: `'items.*.quantity' => ['required', 'numeric']`. |
| `defaults` | `[]` | Data merged under what the developer passes, key by key, before validation. |
| `prepare` | none | `function (array $data, array $theme): array`. Runs after validation and returns the data with computed values added (totals, numbering, a QR code). It can throw `ValidationException::withMessages()` for checks rules cannot express. |
| `sample` | `[]` | Example data for the preview page, `doc:sample` and your tests: an array, or a closure that returns one (useful for `now()`). |
| `theme` | `[]` | Theme values for this template only, such as its own `primary` colour. They sit between the config theme and `->theme()`. |

The data reaches the layout in this order: your data, with `defaults` merged under it, is checked against `fields`, then passed through `prepare`. The layout receives the result.

## The PDF layout: pdf.blade.php {#pdf-blade}

`pdf.blade.php` is a Blade view. It receives `$doc`, the [document helpers](/reference/template-helpers), and every top-level key of the prepared data as a variable: data with `shipment`, `customer` and `packages` gives `$shipment`, `$customer` and `$packages`.

Wrap the page in the layout component, which sets the direction, the font and the base styles, and add your CSS in its `styles` slot:

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

The CSS has to work in mPDF, which supports CSS 2.1: lay out with tables, not flexbox or grid. [Blade views and HTML](/guide/views-and-html) covers the layout component, the helpers and the CSS that works on every engine. The [worked example](#blade-version) below has a complete `pdf.blade.php`.

## Labels: lang files {#lang}

Each file in `lang/` returns the labels for one language:

```php
// lang/ar.php
return [
    'title' => 'قائمة التعبئة',
    'customer' => 'العميل',
    'greeting' => 'مرحباً :name، رقم طلبك :number',
    'units' => ['box' => 'صندوق', 'pallet' => 'طبلية'],
];
```

Read them with `$doc->t()` in Blade files and layouts, and with `${t.key}` in a `word.docx`:

```blade
{{ $doc->t('title') }}
{{ $doc->t('units.box') }}
{{ $doc->t('greeting', ['name' => $customer['name'], 'number' => $order['number']]) }}
```

Nested labels are read with dots, and `:name` placeholders are replaced by the values you pass. The file is chosen by the language of the locale (`ar_EG` reads `lang/ar.php`). A label missing there is taken from `lang/en.php`, and a label missing in both prints its key, which makes typos easy to spot.

## Header and footer {#header-footer}

`header.blade.php` and `footer.blade.php` are Blade files with the same `$doc` and data variables as the layout. They are drawn on every page, and `{page}` and `{pages}` become the page number and the page count:

```blade
{{-- footer.blade.php --}}
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $doc->ltr($shipment['number']) }}</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
```

What to know:

- They sit in the page margins. A header taller than a line or two needs a larger top margin, for example `'margins' => [28, 15, 15, 15]`.
- Use inline styles. Chromium draws headers and footers apart from the page, so the CSS of `pdf.blade.php` does not reach them there.
- Digits follow `->numerals()`. Chromium prints `{page}` and `{pages}` in Latin digits even with Arabic digits.
- In Word files, the header and footer become one centred line of small text, with real Word page numbers. Their styles, tables and images are left out.
- `->header($html)` and `->footer($html)` on a document replace the template's files for that document.

## One layout for PDF and Word: layout.php {#layout-php}

`layout.php` returns a function that adds blocks to a document, using the same blocks as [`Doc::make()`](/guide/builder):

```php
<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;

return function (DocumentBuilder $list, array $data, DocContext $doc): void {
    $list->heading($doc->t('title'));
    $list->paragraph([['text' => $doc->t('customer').': ', 'bold' => true], $data['customer']['name']]);
};
```

The function gets the builder, the prepared data, and `$doc` with the same helpers as in Blade. One layout makes both formats, which is how the quotation, purchase order, delivery note, credit note, receipt, payslip, contract and Egyptian e-invoice templates are built.

`word.php` has exactly the same shape. Use it next to `pdf.blade.php` when the PDF is designed in Blade and the Word file in code, as the invoice, letter, report and certificate templates do.

### Which file makes which format {#precedence}

| | PDF | Word |
| --- | --- | --- |
| First choice | `pdf.blade.php` | `word.docx` |
| Then | `layout.php` | `word.php` |
| Then | `word.php` | `layout.php` |

So:

- `layout.php` alone makes both formats from one layout.
- `pdf.blade.php` with `layout.php` or `word.php`: Blade for the PDF, code for the Word file.
- `word.docx` makes the Word file whatever else is there. See [Word files](/guide/word#word-docx).
- `pdf.blade.php` alone makes PDFs only. `->word()` then throws `WordNotSupported`: "Template [packing-list] has no Word layout. Add layout.php, word.php or word.docx to its folder."

Choose `layout.php` when one layout for both formats is enough, which it usually is for business documents built from tables. Choose `pdf.blade.php` when the PDF needs a design you can only express in HTML and CSS, and accept keeping a second layout for Word. Choose `word.docx` when someone who does not write code designs the Word file.

## Where templates are found {#paths}

Templates are looked up in the folders listed under `templates.paths` in `config/easy-pdf-word.php`, in order, and then in the package. The first folder that has the name wins.

```php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
        base_path('modules/Shipping/doc-templates'),
    ],
],
```

`doc:make-template` and `doc:template` write into the first folder. A package or module can also add a folder from a service provider; it is searched first unless you pass `first: false`:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

public function boot(): void
{
    Doc::templates()->addPath(base_path('modules/Shipping/doc-templates'));
}
```

## Override a bundled template {#override}

Because your folders are searched before the package, a template in your folder with the name of a bundled one replaces it everywhere in your app. The usual way is to copy the bundled one with `php artisan doc:template invoice` and edit the copy; see [Ready-made templates](/guide/templates#override).

## Worked example: a packing list {#example}

A packing list (قائمة التعبئة) for a shipment: the company, the shipment details, the customer, a table of packages with a totals row, notes and two signatures, in Arabic and English, as PDF and Word.

<div class="preview">
  <figure><img src="/images/guide-a/packing-list-ar.png" alt="The packing list template in Arabic"><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><img src="/images/guide-a/packing-list-en.png" alt="The packing list template in English"><figcaption>English (PDF)</figcaption></figure>
</div>

### 1. Create the folder {#example-create}

```bash
php artisan doc:make-template packing-list
```

This example uses one layout for both formats, so delete `pdf.blade.php` and `word.php` from the new folder. You will add `layout.php`.

### 2. template.php {#example-template}

The fields, a `prepare` step that adds the totals, and sample data:

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

### 3. Labels {#example-labels}

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

### 4. layout.php {#example-layout}

The company comes from the [theme](/guide/templates#theme), so the same template works for every brand. Text that must stay left to right, such as the shipment number and the sizes, is marked `ltr`.

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

### 5. footer.blade.php {#example-footer}

Replace the starter's footer with the shipment number on one side and the page numbers on the other:

```blade
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $doc->ltr($shipment['number']) }}</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
```

### 6. Use it {#example-use}

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

The totals row shows 20 items and 43.10 kg, calculated by `prepare`. Pass `->locale('en')` for the English version. The template also appears in `php artisan doc:templates`, `php artisan doc:sample packing-list` renders its sample data, and the preview page shows it next to the bundled templates.

A mistake in the data is reported by field. A package with `'quantity' => 0` fails with "The packages.0.quantity field must be at least 1."

### 7. Optional: a Blade PDF {#blade-version}

To design the PDF in HTML and CSS instead, add a `pdf.blade.php`. The PDF then uses it, and the Word file still comes from `layout.php`:

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
