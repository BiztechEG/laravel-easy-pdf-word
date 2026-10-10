# Tax invoice

The `invoice` template is the tax invoice a seller issues to a buyer: items, discounts, VAT, the total in figures and in Arabic words, and an optional QR code. It fits Egyptian invoices (14% VAT, the default) and Saudi simplified tax invoices with the ZATCA QR (15% VAT).

<div class="preview">
  <figure><a href="/samples/invoice-ar.pdf" target="_blank"><img src="/previews/invoice-ar.png" alt="Arabic tax invoice made with the invoice template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/invoice-en.pdf" target="_blank"><img src="/previews/invoice-en.png" alt="English tax invoice made with the invoice template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A software company in Cairo bills a client for a project, a hosting plan and training days, with 14% VAT.
- A shop in Riyadh prints a simplified tax invoice with the ZATCA QR code at the counter, or attaches it to the order confirmation email.
- A company registered with the Egyptian e-invoicing system keeps its own invoice design, with the e-invoice UUID and a QR that links to the document on the portal.
- A distributor in Kuwait or the Emirates invoices in KWD or AED; amounts keep the currency's decimals (three for KWD).
- A client's accounts team asks for the invoice as a Word file they can stamp or edit.

## Quick example {#example}

This builds the invoice in the preview above and saves it as a PDF and as a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$invoice = Doc::template('invoice', [
    'invoice' => [
        'number' => 'INV-2026-1024',
        'date' => '2026-10-08',
        'due_date' => '2026-10-22',
        'currency' => 'EGP',
        'tax_rate' => 14,
        'notes' => 'يرجى التحويل على الحساب البنكي خلال 14 يوماً من تاريخ الفاتورة.',
    ],
    'seller' => [
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
        'tax_number' => '123-456-789',
        'phone' => '+20 100 000 0000',
    ],
    'buyer' => [
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'مدينة نصر، القاهرة',
        'tax_number' => '987-654-321',
    ],
    'items' => [
        ['description' => 'تطوير نظام إدارة المخزون (Laravel)', 'quantity' => 1, 'unit_price' => 25000],
        ['description' => 'استضافة سحابية - اشتراك سنوي', 'quantity' => 12, 'unit_price' => 450, 'discount' => 400],
        ['description' => 'تدريب فريق العمل', 'quantity' => 3, 'unit_price' => 1500],
    ],
    'qr' => 'https://example.com/invoices/INV-2026-1024',
])->locale('ar');

// The PDF
$invoice->pdf()->save(storage_path('app/invoices/INV-2026-1024.pdf'));

// The same invoice as a Word file
$invoice->word()->save(storage_path('app/invoices/INV-2026-1024.docx'));
```

The invoice shows a subtotal of 34,900.00, a discount of -400.00, VAT (14%) of 4,830.00 and a total due of 39,330.00 ج.م, followed by «فقط تسعة وثلاثون ألفاً وثلاثمائة وثلاثون جنيهاً لا غير». Under the issue date it prints the Hijri date, 27 ربيع الآخر 1448 هـ.

In a controller, return the file instead of saving it:

```php
return $invoice->pdf()->download('INV-2026-1024.pdf');
```

`->stream()` shows it in the browser instead, and `->save('invoices/INV-2026-1024.pdf', disk: 's3')` stores it on a disk. See [Output and delivery](/guide/output).

::: tip Data from your models
Collections and models are accepted wherever an array is expected, so the items can come straight from the order:

```php
'items' => $order->lines->map(fn ($line) => [
    'description' => $line->name,
    'quantity' => $line->qty,
    'unit_price' => $line->price,
]),
```
:::

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `invoice.number` | Yes | text or number | | Invoice number, at the top and in the footer. Printed left to right inside Arabic text. |
| `invoice.date` | Yes | date (string or Carbon) | | Issue date, printed as `2026/10/08`. Also the source of the Hijri date and of the ZATCA QR time. |
| `invoice.due_date` | No | date | | Due date, printed under the issue date when given. |
| `invoice.currency` | Yes | 3-letter code | `EGP` | Currency of all amounts: its decimals (3 for KWD, BHD, OMR, JOD), the label after the total (ج.م, ر.س, د.ك ... in Arabic, the code in English) and the amount in words. |
| `invoice.tax_rate` | No | number, 0 or more | `14` | VAT percentage, charged on the subtotal after discounts. The VAT row is always printed, even at 0%. |
| `invoice.notes` | No | text | | Notes block at the end of the invoice. |
| `invoice.eta_uuid` | No | text | | Egyptian e-invoice UUID, printed in small text under the QR code. Shown only when there is a QR. |
| `seller` | No | array | theme `company` | The seller. Each key you give replaces the same key of the theme's company; empty values are ignored. Keys read: `name`, `address`, `phone`, `tax_number`, `commercial_register`. |
| `buyer.name` | Yes | text | | Customer name, in the "Bill to" box. |
| `buyer.address` | No | text | | Customer address. |
| `buyer.tax_number` | No | text | | Customer tax number. |
| `items` | Yes | array, at least one | | The invoice lines, numbered 1, 2, 3 in the order given. |
| `items.*.description` | Yes | text | | What was sold. |
| `items.*.quantity` | Yes | number | | Whole numbers print without decimals, others with two. |
| `items.*.unit_price` | Yes | number | | Price of one unit before VAT. |
| `items.*.discount` | No | number, 0 or more | | An amount (not a percentage) taken off this line. It cannot be more than quantity × unit price. |
| `qr` | No | text | `null` | `'zatca'` builds the Saudi ZATCA QR from the invoice; any other text (a link, a UUID) is encoded as it is; `null` prints no QR. |

A field with a default is filled in before validation, so you can leave it out even when it is required.

### Computed values {#computed}

The template's `prepare()` step adds these to the data before it is drawn:

| Value | How it is computed |
| --- | --- |
| `items.*.total` | quantity × unit price, rounded to the currency's decimals, minus the line discount |
| `totals.subtotal` | sum of quantity × unit price over all lines |
| `totals.discount` | sum of the line discounts (printed as a negative row when above zero) |
| `totals.taxable` | subtotal minus discount |
| `totals.tax` | taxable × `tax_rate` / 100, rounded |
| `totals.total` | taxable plus tax: the "Total due" |
| `seller` | the theme's company merged with the `seller` you passed |
| `qr` | with `'zatca'`, replaced by the base64 ZATCA payload |

Lines are rounded one by one, so they always add up to the subtotal. Under `Doc::fake()` you can check these values in tests with `$doc->data('totals.total')`; see [Testing your app](/guide/testing).

On the page the template also prints:

- **Amount in words** (المبلغ بالحروف), from `totals.total` and the currency, in the document's language.
- **Hijri date** of `invoice.date`, in Arabic documents when PHP's `intl` extension is installed.

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `company.name`, `company.address`, `company.phone`, `company.tax_number` | Default seller details (any other key you add to `company`, such as `commercial_register`, is merged too) |
| `logo` | Shown above the seller's name at the top |
| `primary` | Seller name, title, table header, the "Total due" row and the box around the amount in words |
| `muted` | Labels and notes |
| `border` | Seller and buyer boxes, lines between items |
| `text` | Body text |

## Variants and options {#options}

### ZATCA QR, a link or no QR {#qr}

Set `qr` to `'zatca'` and the template builds the Saudi phase 1 QR code from the invoice itself: the seller's name, the seller's VAT number (`seller.tax_number`), the invoice time, the total with VAT and the VAT amount.

```php
$invoice = Doc::template('invoice', [
    'invoice' => [
        'number' => 'SA-2026-0451',
        'date' => '2026-10-08 14:30',
        'currency' => 'SAR',
        'tax_rate' => 15,
    ],
    'seller' => [
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'حي العليا، الرياض',
        'tax_number' => '300000000000003',
        'commercial_register' => '1010123456',
    ],
    'buyer' => ['name' => 'شركة الأفق للمقاولات'],
    'items' => [
        ['description' => 'اشتراك سنوي في نظام نقاط البيع', 'quantity' => 1, 'unit_price' => 1000],
    ],
    'qr' => 'zatca',
])->locale('ar');
```

The QR holds `مؤسسة النور للتجارة`, `300000000000003`, `2026-10-08T14:30:00Z`, `1150.00` and `150.00`. The time is written in UTC: a date with a time (or `now()`) is read in your app's time zone and converted, and a date alone counts as midnight UTC of that day. The seller can also come from the theme's company, as long as it has a `tax_number`.

The QR only carries these five fields. The package does not sign invoices or send them to ZATCA. See [Saudi ZATCA invoice](/recipes/zatca-invoice) for the full recipe.

Any other text is put in the QR as it is. For an invoice already submitted to the Egyptian e-invoicing system, put the portal link in the QR and the UUID under it:

```php
'invoice' => [
    // ...
    'eta_uuid' => 'R6ZQ4SB1ZWP2XKCV2G0AYXHG10',
],
'qr' => 'https://invoicing.eta.gov.eg/documents/R6ZQ4SB1ZWP2XKCV2G0AYXHG10/share/A3MZ7X0NE6C8XH3GV2G0AYXHG10QN3K91653372781',
```

Leave `qr` out (or `null`) for an invoice without a QR code. `invoice.eta_uuid` is printed only next to a QR. For the full ETA document, with tax registration numbers and ETA tax types, use the [Egyptian e-invoice](/templates/eg-invoice) template instead.

### Hijri date and amount in words {#arabic}

- Arabic documents (`->locale('ar')`, or any right-to-left locale) show the Hijri date of `invoice.date` under the issue date, for example 27 ربيع الآخر 1448 هـ. It needs PHP's `intl` extension; without it the line is left out.
- The amount in words is printed under the totals in the document's language. In Arabic it is tafqeet, for example «فقط ألف ومائة وخمسون ريالاً لا غير»: EGP, SAR, AED, QAR, KWD, USD and EUR are read with their Arabic names; add others under `currencies` in the config (see [Arabic support](/guide/arabic)), and an unknown currency is read as the number in words followed by its code. In English the number is spelled out by PHP's `intl` extension («one thousand one hundred fifty SAR only»); without `intl`, English documents leave that line out.

### Arabic digits, colours and your company {#branding}

Set the seller once in the theme, and the invoice only needs the buyer and the items. This also shows the defaults at work: EGP and 14% VAT.

```php
$invoice = Doc::template('invoice', [
    'invoice' => ['number' => 'INV-2026-1025', 'date' => '2026-10-09'],
    'buyer' => ['name' => 'مؤسسة النور للتجارة'],
    'items' => [
        ['description' => 'صيانة شهرية للنظام', 'quantity' => 1, 'unit_price' => 3000],
    ],
])
    ->theme([
        'primary' => '#1D4ED8',
        'logo' => public_path('images/logo.png'),
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'tax_number' => '123-456-789',
        ],
    ])
    ->locale('ar')
    ->numerals('arabic');
```

The total prints as ٣,٤٢٠.٠٠ ج.م with Arabic digits. To use the same company for every document, put these values under `theme` in `config/easy-pdf-word.php` instead; [Images](/guide/images) explains which logo paths are allowed.

### Rules worth knowing {#rules}

- A line discount larger than the line amount throws a `ValidationException` on `items.N.discount`.
- Every amount is rounded to the currency's decimals: 2 for most currencies, 3 for KWD, BHD, OMR and JOD.
- The discount column is always shown, with `-` on lines without a discount.

## Word file {#word}

The Word version comes from the template's `word.php`, built from the same prepared data; the PDF comes from `pdf.html.php`. Both show the same information, with a few layout differences:

- With a logo, the logo fills the top corner and the seller's name, address and phone follow under the header.
- The seller box also repeats the seller's address.
- The QR code sits under the totals instead of beside them.
- The amount in words has a solid border (dashed in the PDF).
- The footer keeps the invoice number and "Page X of Y", as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword` and use the font set in `DOC_WORD_FONT` (Arial by default); see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template invoice --as=my-invoice
```

This copies the template to `resources/doc-templates/my-invoice`. Use it with `Doc::template('my-invoice', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels, such as the title or the currency names.
- `template.php` for the fields, the `defaults` (for example `'invoice' => ['currency' => 'SAR', 'tax_rate' => 15]` for Saudi Arabia) and the totals in `prepare()`.
- `pdf.html.php` for the PDF layout and `word.php` for the Word layout. Change both when you move or add something.
- `footer.html.php` for the footer; `{page}` and `{pages}` become page numbers.

See [Your own templates](/guide/custom-templates) for the folder and the helpers available in a template.

## Related {#related}

- Templates: [Egyptian e-invoice](/templates/eg-invoice), [Credit and debit note](/templates/credit-note), [Price quotation](/templates/quotation), [Receipt and payment voucher](/templates/receipt)
- Recipes: [Saudi ZATCA invoice](/recipes/zatca-invoice), [Email an invoice](/recipes/email-invoice), [Download, preview or store](/recipes/controller-responses), [Branding per customer](/recipes/multi-tenant-branding), [Testing document features](/recipes/testing-documents)
- Guide: [Arabic support](/guide/arabic), [Word files](/guide/word), [Output and delivery](/guide/output)
