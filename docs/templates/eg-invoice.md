# Egyptian e-invoice

The `eg-invoice` template prints an e-invoice, credit note or debit note that an Egyptian seller submits to the Egyptian Tax Authority (ETA) e-invoicing system (منظومة الفاتورة الإلكترونية). Its fields follow the ETA document, so you can print the same data you submit, with tax registration numbers, item codes, ETA tax types and a QR that opens the document on the ETA portal.

<div class="preview">
  <figure><a href="/samples/eg-invoice-ar.pdf" target="_blank"><img src="/previews/eg-invoice-ar.png" alt="Arabic Egyptian e-invoice made with the eg-invoice template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/eg-invoice-en.pdf" target="_blank"><img src="/previews/eg-invoice-en.png" alt="English Egyptian e-invoice made with the eg-invoice template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

The template prints the document. Signing it and submitting it to the ETA API is up to your app or your e-invoicing provider; once it is accepted, the UUID and long ID the ETA returns give the QR code.

## When to use it {#when-to-use}

- After an invoice is accepted by the ETA, send the customer a PDF copy with its UUID and a QR code that opens it on the portal.
- Issue a credit note (type `C`) for returned goods, or a debit note (type `D`) for a price increase, on invoices already submitted.
- Sell goods that carry table tax (T2, T3) or fees, and show each tax on its line and in the tax summary.
- Bill services with withholding tax (T4) deducted from the total.
- Invoice a person by national ID or a foreign buyer by passport number, not only companies.

## Quick example {#example}

The data of the preview above, saved as a PDF and as a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$einvoice = Doc::template('eg-invoice', [
    'document' => [
        'type' => 'I',
        'internal_id' => 'INV-2026-1024',
        'issued_at' => '2026-10-08 11:45',
        'uuid' => 'R6ZQ4SB1ZWP2XKCV2G0AYXHG10',
        'long_id' => 'A3MZ7X0NE6C8XH3GV2G0AYXHG10QN3K91653372781',
        'currency' => 'EGP',
        'purchase_order' => 'PO-7781',
    ],
    'issuer' => [
        'name' => 'شركة بيزتك للحلول البرمجية',
        'rin' => '123456789',
        'branch_id' => '0',
        'activity_code' => '6201',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
    ],
    'receiver' => [
        'type' => 'B',
        'id' => '987654321',
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'مدينة نصر، القاهرة',
    ],
    'lines' => [
        [
            'description' => 'تطوير نظام إدارة المخزون',
            'item_type' => 'EGS',
            'item_code' => 'EG-123456789-1001',
            'unit' => 'EA',
            'quantity' => 1,
            'unit_price' => 25000,
            'taxes' => [
                ['type' => 'T1', 'rate' => 14],
                ['type' => 'T4', 'subtype' => 'W010', 'rate' => 3],
            ],
        ],
        [
            'description' => 'استضافة سحابية - اشتراك شهري',
            'item_type' => 'EGS',
            'item_code' => 'EG-123456789-2002',
            'unit' => 'MON',
            'quantity' => 12,
            'unit_price' => 450,
            'discount' => 400,
            'taxes' => [
                ['type' => 'T1', 'rate' => 14],
            ],
        ],
    ],
])->locale('ar');

// The PDF
$einvoice->pdf()->save(storage_path('app/e-invoices/INV-2026-1024.pdf'));

// The same document as a Word file
$einvoice->word()->save(storage_path('app/e-invoices/INV-2026-1024.docx'));
```

The first line totals 27,750.00 (25,000 + 3,500 VAT - 750 withholding) and the second 5,700.00 (5,000 + 700 VAT). The summary shows total sales 30,400.00, total discount -400.00, net amount 30,000.00, T1 4,200.00, T4 -750.00 and a total of 33,450.00 ج.م, with «فقط ثلاثة وثلاثون ألفاً وأربعمائة وخمسون جنيهاً لا غير» and the portal QR code.

In a controller, `return $einvoice->pdf()->download('INV-2026-1024.pdf');` sends it to the browser instead. See [Output and delivery](/guide/output).

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `document.type` | Yes | `I`, `C` or `D` | `I` | Invoice, credit note or debit note: sets the title and the footer. |
| `document.internal_id` | Yes | text or number | | Your own document number, at the top and in the footer. |
| `document.issued_at` | Yes | date and time | | Issue time, printed as `2026/10/08 11:45` in the app's time zone. An ETA time in UTC (`2026-10-08T09:45:00Z`) is converted. |
| `document.uuid` | No | text | | The UUID the ETA returned, printed under the header. With `long_id`, it builds the portal QR. |
| `document.long_id` | No | text | | The long ID the ETA returned, used only in the portal QR link. |
| `document.submission_uuid` | No | text | | The submission ID the ETA returned, printed under the UUID as رقم الإرسال (Submission ID). |
| `document.purchase_order` | No | text | | Purchase order reference, printed under the issue time. |
| `document.currency` | Yes | 3-letter code | `EGP` | Currency of all amounts: decimals, the label after the total and the amount in words. |
| `issuer.name` | Yes | text | | The seller's registered name, at the top and in the issuer box. |
| `issuer.rin` | Yes | text | | The seller's tax registration number (رقم التسجيل الضريبي). |
| `issuer.branch_id` | No | text or number | | Branch code; `'0'` (the main branch) is printed too. |
| `issuer.activity_code` | No | text | | The ETA activity code, for example `6201`. |
| `issuer.address` | No | text | | Seller's address, at the top and in the issuer box. |
| `receiver.type` | Yes | `B`, `P` or `F` | `B` | Business, person or foreigner: sets how `receiver.id` is labelled. |
| `receiver.id` | No | text | | Tax registration number, national ID or passport number, depending on the type. |
| `receiver.name` | Yes | text | | The buyer's name. |
| `receiver.address` | No | text | | The buyer's address. |
| `lines` | Yes | array, at least one | | The document lines, numbered 1, 2, 3 in the order given. |
| `lines.*.description` | Yes | text | | What was sold. |
| `lines.*.item_type` | No | `EGS` or `GS1` | | Coding system, printed small above the item code. |
| `lines.*.item_code` | No | text | | The item's EGS or GS1 code. |
| `lines.*.unit` | No | text | | Unit code as registered, for example `EA`, `MON` or `BOX`; printed as given. |
| `lines.*.quantity` | Yes | number | | Quantity. |
| `lines.*.unit_price` | Yes | number | | Price of one unit before taxes. |
| `lines.*.discount` | No | number, 0 or more | | An amount taken off the line before taxes. It cannot be more than quantity × unit price. |
| `lines.*.taxes` | No | array | | The line's taxes, see [Taxes](#taxes). |
| `lines.*.taxes.*.type` | Yes, per tax | `T1` to `T20` (`t1` works too) | | The ETA tax type. |
| `lines.*.taxes.*.rate` | No | number | | Percentage of the tax's base. |
| `lines.*.taxes.*.amount` | No | number | | A fixed amount instead of a rate (wins over `rate`). |
| `lines.*.taxes.*.subtype` | No | text, up to 20 characters | `V009` for T1 | The ETA subtype, such as `W010`. Kept with the tax in the data, not printed. |
| `extra_discount` | No | number, 0 or more | `0` | A discount on the whole document, taken off the total after taxes. |
| `qr` | No | text | portal link | Replaces the QR content. Without it, the QR links to the portal when `uuid` and `long_id` are given. |
| `notes` | No | text | | Notes at the end. |

A field with a default is filled in before validation, so you can leave it out even when it is required.

### Computed values {#computed}

| Value | How it is computed |
| --- | --- |
| `lines.*.sales` | quantity × unit price |
| `lines.*.net` | sales minus the line discount |
| `lines.*.taxes` | each tax with its computed `amount`, sorted by type (T1, T2 ...) |
| `lines.*.vat` | the line's T1 amount: the "VAT" column |
| `lines.*.total` | net plus all taxes, minus T4: the "Total" column |
| `totals.sales`, `totals.discount`, `totals.net` | sums over all lines |
| `totals.taxes` | the total of each tax type, for example `['T1' => 4200, 'T4' => 750]` |
| `totals.extra_discount` | `extra_discount`, rounded |
| `totals.total` | the sum of the line totals minus `extra_discount` |
| `qr` | `https://invoicing.eta.gov.eg/documents/{uuid}/share/{long_id}` when not given |

Tax amounts are computed with five decimals, as the ETA does, and printed with the currency's decimals. The amount in words follows the document's language: Arabic tafqeet in Arabic, and in English the number spelled out by PHP's `intl` extension («thirty-three thousand four hundred fifty EGP only»). Without `intl`, English documents leave that line out.

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `logo` | Above the issuer's name at the top |
| `primary` | Issuer name, title, table header and the total row |
| `muted` | Labels, the address and the QR caption |
| `border` | Issuer and receiver boxes |

The issuer comes from `issuer`, not from the theme's company.

## Variants and options {#options}

### Document types {#document-types}

| `document.type` | Arabic title | English title |
| --- | --- | --- |
| `I` | فاتورة ضريبية إلكترونية | E-Invoice |
| `C` | إشعار دائن | Credit Note |
| `D` | إشعار مدين | Debit Note |

The type changes the title and the footer. Amounts are printed as you pass them, so a credit note lists the credited quantities and prices as positive numbers, as in the ETA document.

### Receiver types {#receiver-types}

| `receiver.type` | Who | `receiver.id` is printed as |
| --- | --- | --- |
| `B` | A business registered in Egypt | رقم التسجيل الضريبي / Tax registration no. |
| `P` | A person | الرقم القومي / National ID |
| `F` | A foreign buyer | رقم جواز السفر / Passport no. |

```php
'document' => ['type' => 'C', /* ... */],
'receiver' => [
    'type' => 'P',
    'id' => '29001011234567',
    'name' => 'أحمد عبد الرحمن',
],
```

The issuer always shows its tax registration number, branch code and activity code.

### Taxes {#taxes}

Each line lists its taxes by ETA type, with a `rate` (a percentage) or a fixed `amount`. The template computes them in the order the ETA uses:

| Types | Name | Base | Effect on the total |
| --- | --- | --- | --- |
| T1 | Value added tax | net + T2 + T3 + taxable fees (T5 to T12) | added |
| T2 | Table tax (percentage) | net + T3 + taxable fees (T5 to T12) | added |
| T3 | Table tax (fixed amount) | net (usually given as `amount`) | added |
| T4 | Withholding tax (WHT) | net | deducted |
| T5 to T12 | Stamping tax, entertainment tax, resource development fee, service charges, municipality fees, medical insurance fee, other fees | net | added, and part of the T1 and T2 base |
| T13 to T20 | The same taxes and fees as T5 to T12 when they are not taxable, with the same names | net | added only |

Here a line of goods carries a fixed table tax, a table tax and VAT, and a service line has VAT and 1% withholding:

```php
'lines' => [
    [
        'description' => 'مياه غازية - عبوة 330 مل',
        'item_type' => 'GS1',
        'item_code' => '6223001234567',
        'unit' => 'BOX',
        'quantity' => 10,
        'unit_price' => 120,
        'taxes' => [
            ['type' => 'T3', 'amount' => 50],  // fixed table tax
            ['type' => 'T2', 'rate' => 8],     // table tax: net + T3
            ['type' => 'T1', 'rate' => 14],    // VAT: net + T2 + T3
        ],
    ],
    [
        'description' => 'خدمة توريد وتركيب',
        'item_type' => 'EGS',
        'item_code' => 'EG-123456789-3003',
        'unit' => 'EA',
        'quantity' => 1,
        'unit_price' => 2000,
        'taxes' => [
            ['type' => 'T1', 'rate' => 14],
            ['type' => 'T4', 'subtype' => 'W010', 'rate' => 1],  // deducted
        ],
    ],
],
```

The first line: net 1,200.00, T3 50.00, T2 8% of 1,250 = 100.00, T1 14% of 1,350 = 189.00, total 1,539.00. The second: 2,000 + 280.00 VAT - 20.00 withholding = 2,260.00. The summary lists T1 469.00, T2 100.00, T3 50.00 and T4 -20.00.

The line's "VAT" column shows T1 only; every other tax appears in the summary under its type and name, such as `T13 - ضريبة الدمغة (نسبية)`.

### Totals and extra discount {#totals}

The summary under the lines reads, in order: total sales, total discount (when there is one), net amount, one row per tax type, the extra discount (when there is one) and the total. `extra_discount` is taken off after taxes, so it does not change any tax.

### QR code {#qr}

With `document.uuid` and `document.long_id`, the QR opens the document on the ETA portal, with the caption «امسح الرمز للتحقق من الفاتورة على بوابة مصلحة الضرائب». Pass `qr` to put something else in it. Without `qr` and without both IDs, no QR is printed, which suits a draft printed before submission.

### Issue time {#issue-time}

The ETA gives `dateTimeIssued` in UTC, such as `2026-10-08T09:45:00Z`. The template prints it in the app's time zone (`timezone` in `config/app.php`): 2026/10/08 12:45 for `Africa/Cairo`. A time without a zone is printed as it is.

## Word file {#word}

`layout.php` builds both the PDF and the Word file, so they have the same content in the same order. The tax summary is part of the totals table in both, and the QR code comes after the amount in words. The footer keeps the document type, its number and "Page X of Y" as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template eg-invoice --as=my-eg-invoice
```

This copies the template to `resources/doc-templates/my-eg-invoice`. Use it with `Doc::template('my-eg-invoice', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels: the titles under `types`, the tax names under `tax_types`, the QR caption under `verify`.
- `layout.php` for the layout of both formats: the columns of the lines table, what the boxes show.
- `template.php` for the fields and the tax computation in `prepare()`.
- `footer.blade.php` for the footer.

See [Your own templates](/guide/custom-templates).

## Related {#related}

- Templates: [Tax invoice](/templates/invoice), [Credit and debit note](/templates/credit-note), [Receipt and payment voucher](/templates/receipt)
- Recipes: [Print an ETA e-invoice](/recipes/egypt-e-invoice), [Email an invoice](/recipes/email-invoice), [Download, preview or store](/recipes/controller-responses)
- Guide: [Arabic support](/guide/arabic), [Word files](/guide/word)
