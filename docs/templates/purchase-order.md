# Purchase order

The `purchase-order` template is the order (أمر شراء) a company sends to a supplier: what to supply, at what price, where and when to deliver it, the payment terms, and signature boxes for whoever prepares and approves it and for the supplier's acceptance.

<div class="preview">
  <figure><a href="/samples/purchase-order-ar.pdf" target="_blank"><img src="/previews/purchase-order-ar.png" alt="Arabic purchase order made with the purchase-order template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/purchase-order-en.pdf" target="_blank"><img src="/previews/purchase-order-en.png" alt="English purchase order made with the purchase-order template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- The purchasing team orders office supplies or spare parts from a supplier, with item codes and units.
- An approved quotation from a supplier becomes an order that refers to it.
- Goods must arrive at a warehouse or branch other than the head office, by a given date.
- The order needs signatures from the requester and the manager, and the supplier signs to accept it.
- A supplier asks for the order as a Word file for their own system.

## Quick example {#example}

The order in the preview above, with your company in the header, saved as a PDF and as a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$order = Doc::template('purchase-order', [
    'order' => [
        'number' => 'PO-2026-0412',
        'date' => '2026-10-08',
        'delivery_date' => '2026-10-20',
        'currency' => 'EGP',
        'tax_rate' => 14,
        'reference' => 'QT-5531',
        'payment_terms' => 'الدفع بتحويل بنكي خلال 30 يوماً من الاستلام وتقديم الفاتورة الضريبية.',
    ],
    'supplier' => [
        'name' => 'شركة الأمل لتوريد مستلزمات المكاتب',
        'contact' => 'الأستاذة منى سامي',
        'phone' => '+20 2 2345 6789',
        'address' => 'المنطقة الصناعية الثالثة، العاشر من رمضان',
        'tax_number' => '456-789-123',
    ],
    'delivery' => [
        'address' => 'المخزن الرئيسي، 15 شارع التحرير، الدقي، الجيزة',
        'contact' => 'محمود علي (أمين المخزن)',
        'phone' => '+20 100 111 2222',
    ],
    'items' => [
        ['code' => 'PPR-A4-80', 'description' => 'ورق طباعة A4 وزن 80 جم', 'unit' => 'كرتونة', 'quantity' => 40, 'unit_price' => 950],
        ['code' => 'TNR-26A', 'description' => 'حبر طابعة HP 26A أصلي', 'unit' => 'قطعة', 'quantity' => 10, 'unit_price' => 3200, 'discount' => 1500],
        ['code' => 'CHR-ERG-01', 'description' => 'كرسي مكتب طبي بمسند ظهر', 'unit' => 'قطعة', 'quantity' => 6, 'unit_price' => 4750],
    ],
    'terms' => [
        'يتم التوريد دفعة واحدة إلى عنوان التسليم الموضح.',
        'يحق للشركة رفض أي صنف مخالف للمواصفات أو تالف عند الاستلام.',
        'يجب ذكر رقم أمر الشراء على الفاتورة وإذن التسليم.',
    ],
])
    ->theme([
        // Or once for the whole app, under "theme" in config/easy-pdf-word.php
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'phone' => '+20 100 000 0000',
            'tax_number' => '123-456-789',
        ],
    ])
    ->locale('ar');

// The PDF
$order->pdf()->save(storage_path('app/purchase-orders/PO-2026-0412.pdf'));

// The same order as a Word file
$order->word()->save(storage_path('app/purchase-orders/PO-2026-0412.docx'));
```

The order shows a subtotal of 98,500.00, a discount of -1,500.00, a net of 97,000.00, VAT (14%) of 13,580.00 and an order total of 110,580.00 ج.م, then «فقط مائة وعشرة آلاف وخمسمائة وثمانون جنيهاً لا غير», the payment terms, the terms and three signature boxes: «أعدّه», «اعتمده» and «موافقة المورد».

In a controller, `return $order->pdf()->download('PO-2026-0412.pdf');` sends it to the browser instead. See [Output and delivery](/guide/output).

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `order.number` | Yes | text or number | | Order number, at the top and in the footer. |
| `order.date` | Yes | date | | Order date. |
| `order.delivery_date` | No | date | | Required delivery date, printed in bold in the header. |
| `order.currency` | Yes | 3-letter code | `EGP` | Currency of all amounts: decimals, the label after the total and the amount in words. |
| `order.tax_rate` | No | number, 0 or more | `0` | VAT percentage on the net after discounts. At 0 there is no VAT row. |
| `order.reference` | No | text | | The supplier's quotation number, printed as «مرجع عرض السعر» / "Quotation ref.". |
| `order.payment_terms` | No | text | | Payment terms, printed after the totals. |
| `supplier.name` | Yes | text | | The supplier, in the «المورد» / "Supplier" box. |
| `supplier.contact` | No | text | | Contact person at the supplier. |
| `supplier.phone` | No | text | | Supplier phone, kept left to right. |
| `supplier.address` | No | text | | Supplier address. |
| `supplier.tax_number` | No | text | | Supplier tax number. |
| `delivery.address` | No | text | theme `company.address` | Where to deliver, in the «جهة التسليم» / "Deliver to" box. |
| `delivery.contact` | No | text | | Who receives the goods. |
| `delivery.phone` | No | text | | Phone at the delivery place. |
| `items` | Yes | array, at least one | | The ordered items, numbered 1, 2, 3. |
| `items.*.code` | No | text | | Item code; the code column appears only when an item has one. |
| `items.*.description` | Yes | text | | The item. |
| `items.*.unit` | No | text | | Unit such as «كرتونة» or «قطعة». |
| `items.*.quantity` | Yes | number, 0 or more | | Quantity ordered. |
| `items.*.unit_price` | Yes | number | | Agreed price of one unit before VAT. |
| `items.*.discount` | No | number, 0 or more | | An amount taken off this line; the discount column appears only when a line has one. |
| `terms` | No | array of texts | `[]` | Terms, printed as a numbered list. |
| `notes` | No | text | | Notes after the terms. |
| `signatures` | No | array of texts | `prepared_by`, `approved_by`, `supplier` | The signature boxes, in order. See [Signatures](#signatures). |

A field with a default is filled in before validation, so you can leave it out even when it is required.

### Computed values {#computed}

| Value | How it is computed |
| --- | --- |
| `items.*.total` | quantity × unit price, rounded to the currency's decimals, minus the line discount |
| `totals.subtotal` | sum of quantity × unit price |
| `totals.discount` | sum of the line discounts |
| `totals.net` | subtotal minus discount |
| `totals.tax` | net × `tax_rate` / 100, rounded |
| `totals.total` | net plus tax: the order total |
| `signatures` | the three default boxes when you pass none |

The amount in words follows the document's language: Arabic tafqeet, or in English the number spelled out by PHP's `intl` extension. Without `intl`, English documents leave that line out.

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `company.name`, `company.address`, `company.phone`, `company.tax_number` | Your company at the top. `company.address` is also the delivery address when `delivery.address` is empty. |
| `logo` | Above the company name |
| `primary` | Company name, title, table header and the total row |
| `muted` | Address, labels and the dotted signature lines |
| `border` | The supplier and delivery boxes |

## Variants and options {#options}

### Signatures {#signatures}

`signatures` lists the boxes at the bottom, left to right in English and right to left in Arabic. Three names are translated; any other text is printed as it is:

| Value | Arabic | English |
| --- | --- | --- |
| `prepared_by` | أعدّه | Prepared by |
| `approved_by` | اعتمده | Approved by |
| `supplier` | موافقة المورد | Supplier acceptance |

```php
'signatures' => ['prepared_by', 'approved_by', 'مدير المشتريات'],
```

Pass an empty array, `'signatures' => []`, for an order without signature boxes.

### Delivery place {#delivery}

The «جهة التسليم» box shows `delivery.address`, or your company's address from the theme when you leave it out, with the receiver's name and phone under it.

### With or without VAT {#vat}

`order.tax_rate` defaults to 0, so there is no VAT row until you set a rate. With a discount, the totals add the discount and the net after discount; with VAT, the VAT row; then the order total.

### Columns that appear when needed {#columns}

The code column appears only when at least one item has a `code`, and the discount column only when a line has a `discount`. The description column takes the width the others leave.

## Word file {#word}

`layout.php` builds both the PDF and the Word file, so they have the same content in the same order. The footer keeps the title, the order number and "Page X of Y" as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template purchase-order --as=my-purchase-order
```

This copies the template to `resources/doc-templates/my-purchase-order`. Use it with `Doc::template('my-purchase-order', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels: the opening sentence under `intro`, the signature names under `signatures` (add your own roles there to have them translated).
- `template.php` for the fields, the defaults (currency, VAT rate) and the default signatures in `prepare()`.
- `layout.php` for the layout of both formats.
- `footer.html.php` for the footer.

See [Your own templates](/guide/custom-templates).

## Related {#related}

- Templates: [Delivery note](/templates/delivery-note), [Price quotation](/templates/quotation), [Tax invoice](/templates/invoice)
- Recipes: [Download, preview or store](/recipes/controller-responses), [Branding per customer](/recipes/multi-tenant-branding), [Testing document features](/recipes/testing-documents)
