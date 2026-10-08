# Credit and debit note

The `credit-note` template is the note a seller issues to correct an invoice already sent: a credit note (إشعار دائن) lowers what the customer owes, a debit note (إشعار مدين) adds to it. It names the original invoice and the reason, lists the items and amounts, adds VAT and the total in words, and can carry a ZATCA or link QR.

<div class="preview">
  <figure><a href="/samples/credit-note-ar.pdf" target="_blank"><img src="/previews/credit-note-ar.png" alt="Arabic credit note made with the credit-note template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/credit-note-en.pdf" target="_blank"><img src="/previews/credit-note-en.png" alt="English credit note made with the credit-note template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A customer returns part of an order and you credit the returned items with their VAT.
- A subscription is cancelled mid-year and you credit the months that were not used.
- A training day or a service on the invoice did not take place.
- A price difference or extra quantity was billed too low, so you issue a debit note for the difference.
- A Saudi seller corrects a simplified tax invoice and prints the note with the ZATCA QR.

## Quick example {#example}

The credit note in the preview above, saved as a PDF and as a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$note = Doc::template('credit-note', [
    'type' => 'credit',
    'note' => [
        'number' => 'CN-2026-0057',
        'date' => '2026-10-08',
        'currency' => 'EGP',
        'tax_rate' => 14,
    ],
    'invoice' => [
        'number' => 'INV-2026-1024',
        'date' => '2026-09-20',
    ],
    'reason' => 'إلغاء ثلاثة أشهر من اشتراك الاستضافة ويوم تدريب لم يُنفذ',
    'seller' => [
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
        'tax_number' => '123-456-789',
    ],
    'buyer' => [
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'مدينة نصر، القاهرة',
        'tax_number' => '987-654-321',
    ],
    'items' => [
        ['description' => 'استضافة سحابية - اشتراك شهري', 'quantity' => 3, 'unit_price' => 450],
        ['description' => 'تدريب فريق العمل', 'quantity' => 1, 'unit_price' => 1500],
    ],
    'qr' => 'https://example.com/credit-notes/CN-2026-0057',
    'notes' => 'سيتم خصم قيمة الإشعار من الدفعة القادمة.',
])->locale('ar');

// The PDF
$note->pdf()->save(storage_path('app/credit-notes/CN-2026-0057.pdf'));

// The same note as a Word file
$note->word()->save(storage_path('app/credit-notes/CN-2026-0057.docx'));
```

The note shows a subtotal of 2,850.00, VAT (14%) of 399.00 and a total of 3,249.00 ج.م, then «فقط ثلاثة آلاف ومائتان وتسعة وأربعون جنيهاً لا غير» and the sentence «أُضيفت قيمة هذا الإشعار إلى رصيد حسابكم لدينا.» (this amount has been credited to your account).

In a controller, `return $note->pdf()->download('CN-2026-0057.pdf');` sends it to the browser instead. See [Output and delivery](/guide/output).

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `type` | Yes | `credit` or `debit` | `credit` | Sets the title, the footer and the closing sentence. |
| `note.number` | Yes | text or number | | The note's own number, at the top and in the footer. |
| `note.date` | Yes | date | | Issue date of the note; also the time in the ZATCA QR. |
| `note.currency` | Yes | 3-letter code | `EGP` | Currency of all amounts: decimals, the label after the total and the amount in words. |
| `note.tax_rate` | No | number, 0 or more | `14` | VAT percentage on the subtotal after discounts. At 0 the VAT row is left out. |
| `invoice.number` | Yes | text or number | | The original invoice, in the "Original invoice" box. |
| `invoice.date` | No | date | | Date of the original invoice. |
| `reason` | Yes | text | | Why the note is issued, printed above the items. |
| `seller` | No | array | theme `company` | The seller. Each key you give replaces the same key of the theme's company. Keys read: `name`, `address`, `tax_number`. |
| `buyer.name` | Yes | text | | The customer. |
| `buyer.address` | No | text | | Customer address. |
| `buyer.tax_number` | No | text | | Customer tax number. |
| `items` | Yes | array, at least one | | The lines being credited or charged, numbered 1, 2, 3. |
| `items.*.description` | Yes | text | | What is credited or charged. |
| `items.*.quantity` | Yes | number, 0 or more | | Quantity. |
| `items.*.unit_price` | Yes | number, 0 or more | | Price of one unit before VAT. |
| `items.*.discount` | No | number, 0 or more | | An amount taken off this line. It cannot be more than quantity × unit price. |
| `qr` | No | text | `null` | `'zatca'` builds the Saudi ZATCA QR from the note; any other text is encoded as it is; `null` prints no QR. |
| `notes` | No | text | | Notes at the end. |

A field with a default is filled in before validation, so you can leave it out even when it is required. Quantities and prices cannot be negative: the `type` says which way the amount goes.

### Computed values {#computed}

| Value | How it is computed |
| --- | --- |
| `items.*.total` | quantity × unit price, rounded to the currency's decimals, minus the line discount |
| `totals.subtotal` | sum of quantity × unit price |
| `totals.discount` | sum of the line discounts |
| `totals.taxable` | subtotal minus discount ("Net after discount", printed when there is a discount) |
| `totals.tax` | taxable × `tax_rate` / 100, rounded |
| `totals.total` | taxable plus tax: the "Note total" |
| `seller` | the theme's company merged with the `seller` you passed |
| `qr` | with `'zatca'`, replaced by the base64 ZATCA payload |

Under the total the template prints the amount in words in the document's language: Arabic tafqeet, or in English the number spelled out by PHP's `intl` extension («three thousand two hundred forty-nine EGP only»). Without `intl`, English documents leave that line out.

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `company.name`, `company.address`, `company.tax_number` | Default seller details |
| `logo` | Above the seller's name at the top |
| `primary` | Seller name, title, table header and the total row |
| `muted` | Address, labels, the amount in words and the QR caption |
| `border` | The customer and original invoice boxes |

## Variants and options {#options}

### Credit or debit {#credit-or-debit}

| `type` | Arabic title | English title | Closing sentence |
| --- | --- | --- | --- |
| `credit` | إشعار دائن | Credit Note | أُضيفت قيمة هذا الإشعار إلى رصيد حسابكم لدينا. / This amount has been credited to your account with us. |
| `debit` | إشعار مدين | Debit Note | قُيدت قيمة هذا الإشعار على حسابكم لدينا. / This amount has been debited to your account with us. |

### ZATCA QR {#zatca}

A Saudi debit note for a price difference, with the ZATCA QR built from the note:

```php
$note = Doc::template('credit-note', [
    'type' => 'debit',
    'note' => [
        'number' => 'DN-2026-0012',
        'date' => '2026-10-08',
        'currency' => 'SAR',
        'tax_rate' => 15,
    ],
    'invoice' => ['number' => 'SA-2026-0451', 'date' => '2026-10-01'],
    'reason' => 'فرق سعر على الكمية الموردة بعد تعديل عقد التوريد',
    'seller' => [
        'name' => 'مؤسسة النور للتجارة',
        'tax_number' => '300000000000003',
    ],
    'buyer' => ['name' => 'شركة الأفق للمقاولات'],
    'items' => [
        ['description' => 'فرق سعر - 50 كرتونة ورق A4', 'quantity' => 50, 'unit_price' => 12],
    ],
    'qr' => 'zatca',
])->locale('ar');
```

The total is 690.00 ر.س (600.00 + 90.00 VAT). The QR holds the seller's name and VAT number, the note's date in UTC (`2026-10-08T00:00:00Z` for a date without a time), `690.00` and `90.00`. Any other `qr` text, such as a link to the note in your app, is encoded as it is, with the caption «امسح الرمز للتحقق من الإشعار» under it.

### Columns and rows that appear when needed {#optional-rows}

- The discount column appears only when a line has a discount; with a discount, the totals also show the discount and the net after discount.
- The VAT row appears only when the VAT is above zero, so a note with `'tax_rate' => 0` goes straight from the subtotal to the total.

### Egyptian e-invoicing {#eta}

For a credit or debit note submitted to the Egyptian Tax Authority, use the [Egyptian e-invoice](/templates/eg-invoice) template with `'type' => 'C'` or `'D'`: it prints the ETA fields, item codes and tax types.

## Word file {#word}

`layout.php` builds both the PDF and the Word file, so they have the same content in the same order. The footer keeps the title, the note number and "Page X of Y" as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template credit-note --as=my-credit-note
```

This copies the template to `resources/doc-templates/my-credit-note`. Use it with `Doc::template('my-credit-note', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels: the titles under `title`, the closing sentences under `effect`, the QR caption under `verify`.
- `template.php` for the fields, the defaults (currency and VAT rate) and the totals in `prepare()`.
- `layout.php` for the layout of both formats.
- `footer.blade.php` for the footer.

See [Your own templates](/guide/custom-templates).

## Related {#related}

- Templates: [Tax invoice](/templates/invoice), [Egyptian e-invoice](/templates/eg-invoice), [Receipt and payment voucher](/templates/receipt)
- Recipes: [Saudi ZATCA invoice](/recipes/zatca-invoice), [Email an invoice](/recipes/email-invoice), [Download, preview or store](/recipes/controller-responses)
