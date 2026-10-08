# Delivery note

The `delivery-note` template is the delivery note (إذن تسليم) a company hands over with goods: what was delivered, to whom and where, without prices. It can show the ordered and remaining quantities of a partial delivery, the driver and vehicle, and ends with the receiver's acknowledgement and signature boxes.

<div class="preview">
  <figure><a href="/samples/delivery-note-ar.pdf" target="_blank"><img src="/previews/delivery-note-ar.png" alt="Arabic delivery note made with the delivery-note template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/delivery-note-en.pdf" target="_blank"><img src="/previews/delivery-note-en.png" alt="English delivery note made with the delivery-note template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A warehouse ships an order to a customer's branch and the driver brings back the signed note.
- Part of an order is delivered now and the rest next week; the note shows what remains.
- Goods move between your own branches or warehouses and each side signs.
- An installer delivers devices on site and notes that they were tested in front of the receiver.
- A customer asks for the note as a Word file to file with their receiving records.

## Quick example {#example}

The note in the preview above, with your company in the header, saved as a PDF and as a Word file:

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
        // Or once for the whole app, under "theme" in config/easy-pdf-word.php
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'phone' => '+20 100 000 0000',
        ],
    ])
    ->locale('ar');

// The PDF
$note->pdf()->save(storage_path('app/delivery-notes/DN-2026-0731.pdf'));

// The same note as a Word file
$note->word()->save(storage_path('app/delivery-notes/DN-2026-0731.docx'));
```

The items table has the columns code, item, unit, ordered, delivered, remaining and notes; the screens line shows 6 delivered and 4 remaining. Under it: 3 items, 26 delivered in total and 14 packages, then the transport details, the notes, the sentence «أقر أنا الموقِّع أدناه باستلام الأصناف الموضحة أعلاه كاملة وبحالة جيدة.» and the boxes «أمين المخزن», «السائق» and «المستلم».

In a controller, `return $note->pdf()->download('DN-2026-0731.pdf');` sends it to the browser instead. See [Output and delivery](/guide/output).

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `delivery.number` | Yes | text or number | | Note number, at the top and in the footer. |
| `delivery.date` | Yes | date | | Delivery date. |
| `delivery.order_number` | No | text | | The sales order, printed as «رقم أمر البيع» / "Sales order". |
| `delivery.invoice_number` | No | text | | The related invoice number. |
| `customer.name` | Yes | text | | The customer, in the «العميل» / "Customer" box. |
| `customer.phone` | No | text | | Customer phone, kept left to right. |
| `customer.address` | No | text | | Customer address. |
| `ship_to.address` | No | text | `customer.address` | Where the goods were delivered, in the «مكان التسليم» / "Delivered to" box. |
| `ship_to.contact` | No | text | | Who received the goods there, printed as «المستلم» / "Receiver". |
| `ship_to.phone` | No | text | | Phone at the delivery place. |
| `items` | Yes | array, at least one | | The delivered items, numbered 1, 2, 3. |
| `items.*.code` | No | text | | Item code; the code column appears only when an item has one. |
| `items.*.description` | Yes | text | | The item. |
| `items.*.unit` | No | text | | Unit such as «جهاز» or «قطعة». |
| `items.*.quantity` | Yes | number, 0 or more | | The quantity delivered now, in bold. |
| `items.*.ordered` | No | number, 0 or more | | The quantity ordered. When given, the ordered and remaining columns appear. |
| `items.*.notes` | No | text | | A short note on the line; the notes column appears only when a line has one. |
| `packages` | No | whole number, 0 or more | | Number of packages, printed under the totals. |
| `transport.driver` | No | text | | Driver's name. |
| `transport.phone` | No | text | | Driver's phone. |
| `transport.vehicle` | No | text | | Vehicle plate number. |
| `notes` | No | text | | Notes before the acknowledgement. |
| `signatures` | No | array of texts | `storekeeper`, `driver`, `receiver` | The signature boxes, in order. See [Signatures](#signatures). |

### Computed values {#computed}

| Value | How it is computed |
| --- | --- |
| `items.*.remaining` | `ordered` minus `quantity`, never below 0; only for lines with `ordered` |
| `totals.items` | the number of lines: «عدد الأصناف» / "Items" |
| `totals.quantity` | the sum of the delivered quantities: «إجمالي الكمية المسلَّمة» / "Total delivered" |
| `signatures` | the three default boxes when you pass none |

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `company.name`, `company.address`, `company.phone` | Your company at the top |
| `logo` | Above the company name |
| `primary` | Company name, title and table header |
| `muted` | Address, labels, line notes and the dotted signature lines |
| `border` | The customer, delivery and transport boxes |

## Variants and options {#options}

### Partial deliveries {#partial}

Give each line its `ordered` quantity and the note adds the «المطلوب» / "Ordered" and «المتبقي» / "Remaining" columns. Without `ordered` on any line, the table shows only what was delivered. The same goes for the code and notes columns: each appears only when a line uses it. A minimal note:

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

Here the «مكان التسليم» box repeats the customer's address, there is no transport section and no packages row, and the signature boxes are the storekeeper, the receiver and «مندوب المبيعات».

### Transport {#transport}

When any of `transport.driver`, `transport.phone` or `transport.vehicle` is given, a «بيانات النقل» / "Transport" section shows the ones you passed.

### Signatures {#signatures}

`signatures` lists the boxes at the bottom. Each box has the role, a «الاسم» / "Name" line and a signature line. Three roles are translated; any other text is printed as it is:

| Value | Arabic | English |
| --- | --- | --- |
| `storekeeper` | أمين المخزن | Storekeeper |
| `driver` | السائق | Driver |
| `receiver` | المستلم | Received by |

Pass `'signatures' => []` for a note without signature boxes. The acknowledgement sentence is always printed.

### No prices {#no-prices}

The template has no price fields at all, so the note can travel with the goods. Print the prices on the [Tax invoice](/templates/invoice) and refer to it with `delivery.invoice_number`.

## Word file {#word}

`layout.php` builds both the PDF and the Word file, so they have the same content in the same order. The footer keeps the title, the note number and "Page X of Y" as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template delivery-note --as=my-delivery-note
```

This copies the template to `resources/doc-templates/my-delivery-note`. Use it with `Doc::template('my-delivery-note', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels: the acknowledgement under `acknowledgement`, the opening sentence under `intro`, the roles under `signatures`.
- `template.php` for the fields, the remaining quantities and the default signatures in `prepare()`.
- `layout.php` for the layout of both formats, for example to add a weight column.
- `footer.blade.php` for the footer.

See [Your own templates](/guide/custom-templates).

## Related {#related}

- Templates: [Purchase order](/templates/purchase-order), [Tax invoice](/templates/invoice), [Receipt and payment voucher](/templates/receipt)
- Recipes: [Download, preview or store](/recipes/controller-responses), [Branding per customer](/recipes/multi-tenant-branding), [Testing document features](/recipes/testing-documents)
