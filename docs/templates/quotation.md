# Price quotation

The `quotation` template is the price offer (عرض سعر) a company sends to a customer before a sale: the items with details and units, discounts, optional VAT, the total in words, how long the offer is valid, the terms and the sender's signature.

<div class="preview">
  <figure><a href="/samples/quotation-ar.pdf" target="_blank"><img src="/previews/quotation-ar.png" alt="Arabic price quotation made with the quotation template"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/quotation-en.pdf" target="_blank"><img src="/previews/quotation-en.png" alt="English price quotation made with the quotation template"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A software house sends a client the phases of a project, each with a short description, a discount and payment terms.
- A supplier answers a request for quotation with unit prices, a validity date and delivery terms.
- A sales team sends quick offers from a CRM, with or without VAT depending on the customer.
- A draft offer goes to a manager for review with a «مسودة» watermark before the final version is sent.
- A customer asks for the offer as a Word file to paste into their own approval papers.

## Quick example {#example}

The quotation in the preview above, with your company in the header, saved as a PDF and as a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$quote = Doc::template('quotation', [
    'quote' => [
        'number' => 'QT-2026-0088',
        'date' => '2026-10-08',
        'valid_until' => '2026-11-07',
        'currency' => 'EGP',
        'tax_rate' => 14,
    ],
    'customer' => [
        'name' => 'مؤسسة النور للتجارة',
        'contact' => 'المهندس أحمد عبد الرحمن',
        'phone' => '+20 122 555 0100',
        'address' => 'مدينة نصر، القاهرة',
    ],
    'subject' => 'تنفيذ نظام إدارة المستندات الإلكترونية',
    'items' => [
        [
            'description' => 'تحليل المتطلبات وتصميم النظام',
            'details' => 'ورش عمل مع الأقسام وتوثيق كامل',
            'unit' => 'مرحلة',
            'quantity' => 1,
            'unit_price' => 18000,
        ],
        [
            'description' => 'تطوير النظام (Laravel)',
            'details' => 'الفواتير والخطابات والتقارير بالعربية والإنجليزية',
            'unit' => 'مرحلة',
            'quantity' => 1,
            'unit_price' => 42000,
            'discount' => 2000,
        ],
        ['description' => 'تدريب المستخدمين', 'unit' => 'يوم', 'quantity' => 3, 'unit_price' => 1500],
    ],
    'terms' => [
        'يسري هذا العرض لمدة 30 يوماً من تاريخه.',
        'الدفع: 40% عند التعاقد، و40% عند التسليم، و20% بعد شهر من التشغيل.',
        'مدة التنفيذ 45 يوم عمل من تاريخ استلام الدفعة الأولى.',
        'الدعم الفني مجاني لمدة 12 شهراً.',
    ],
    'sender' => ['name' => 'عمرو محمد', 'title' => 'المدير التنفيذي'],
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
$quote->pdf()->save(storage_path('app/quotations/QT-2026-0088.pdf'));

// The same quotation as a Word file
$quote->word()->save(storage_path('app/quotations/QT-2026-0088.docx'));
```

The quotation shows a subtotal of 64,500.00, a discount of -2,000.00, a net of 62,500.00, VAT (14%) of 8,750.00 and a grand total of 71,250.00 ج.م, then «فقط واحد وسبعون ألفاً ومائتان وخمسون جنيهاً لا غير», the four numbered terms and the signature block.

In a controller, `return $quote->pdf()->download('QT-2026-0088.pdf');` sends it to the browser instead. See [Output and delivery](/guide/output).

## Fields {#fields}

| Field | Required | Type / values | Default | What it does |
| --- | --- | --- | --- | --- |
| `quote.number` | Yes | text or number | | Quotation number, at the top and in the footer. |
| `quote.date` | Yes | date | | Date of the offer. |
| `quote.valid_until` | No | date | | Printed in bold as «صالح حتى» / "Valid until" under the date. |
| `quote.currency` | Yes | 3-letter code | `EGP` | Currency of all amounts: decimals, the label after the total and the amount in words. |
| `quote.tax_rate` | No | number, 0 or more | `0` | VAT percentage on the net after discounts. At 0 there is no VAT row. |
| `customer.name` | Yes | text | | The customer, in the «السادة» / "To" box. |
| `customer.contact` | No | text | | The person the offer is for, printed as «عناية» / "Attn.". |
| `customer.phone` | No | text | | Customer phone, kept left to right. |
| `customer.address` | No | text | | Customer address. |
| `subject` | No | text | | Subject line above the items. |
| `items` | Yes | array, at least one | | The offered items, numbered 1, 2, 3. |
| `items.*.description` | Yes | text | | The item's name. Bold when it has details. |
| `items.*.details` | No | text | | A second, smaller line under the description. |
| `items.*.unit` | No | text | | Unit such as «يوم», «قطعة» or "month". |
| `items.*.quantity` | Yes | number | | Whole numbers print without decimals, others with two. |
| `items.*.unit_price` | Yes | number | | Price of one unit before VAT. |
| `items.*.discount` | No | number, 0 or more | | An amount taken off this line. It cannot be more than quantity × unit price. |
| `terms` | No | array of texts | `[]` | Terms and conditions, printed as a numbered list under a heading. |
| `notes` | No | text | | Notes after the terms. |
| `sender.name` | No | text | | Name in the signature block. |
| `sender.title` | No | text | | Job title above the name. Without any `sender`, there is no signature block. |

A field with a default is filled in before validation, so you can leave it out even when it is required.

### Computed values {#computed}

| Value | How it is computed |
| --- | --- |
| `items.*.total` | quantity × unit price, rounded to the currency's decimals, minus the line discount |
| `totals.subtotal` | sum of quantity × unit price |
| `totals.discount` | sum of the line discounts |
| `totals.net` | subtotal minus discount |
| `totals.tax` | net × `tax_rate` / 100, rounded |
| `totals.total` | net plus tax: the grand total |

The amount in words follows the document's language: Arabic tafqeet, or in English the number spelled out by PHP's `intl` extension («seventy-one thousand two hundred fifty EGP only»). Without `intl`, English documents leave that line out.

### Theme values {#theme}

| Theme key | Used for |
| --- | --- |
| `company.name`, `company.address`, `company.phone` | The sender at the top. Without them the name is your `APP_NAME`. |
| `logo` | Above the company name |
| `primary` | Company name, title, table header and the total row |
| `muted` | Address, phone, the «السادة» label and item details |
| `border` | The customer box |

## Variants and options {#options}

### With or without VAT {#vat}

`quote.tax_rate` defaults to 0, so a quotation has no VAT unless you ask for it. Set `'tax_rate' => 14` (Egypt) or `15` (Saudi Arabia) to add a VAT row. The rows under the items adapt to the data:

- subtotal, always;
- discount and net after discount, when a line has a discount;
- VAT, when it is above zero;
- the grand total.

### Validity, terms and signature {#validity}

`quote.valid_until` prints a bold validity date in the header. `terms` is a plain list of sentences, numbered 1, 2, 3 under «الشروط والأحكام» / "Terms and conditions". The quotation always ends with «وتفضلوا بقبول فائق الاحترام،» / "Kind regards," followed by the `sender` block (title, space for the signature, name).

### A draft with a watermark {#draft}

A short offer without VAT, validity or terms, marked as a draft:

```php
$quote = Doc::template('quotation', [
    'quote' => ['number' => 'QT-2026-0091', 'date' => '2026-10-10'],
    'customer' => ['name' => 'مؤسسة النور للتجارة'],
    'items' => [
        ['description' => 'باقة استضافة سنوية', 'unit' => 'سنة', 'quantity' => 1, 'unit_price' => 5400],
    ],
])->locale('ar')->watermark('مسودة');
```

The total is 5,400.00 ج.م («فقط خمسة آلاف وأربعمائة جنيه لا غير») and «مسودة» runs across the page. The watermark is for PDF files only; the Word version is made without it. See [Page settings](/guide/page-settings).

## Word file {#word}

`layout.php` builds both the PDF and the Word file, so they have the same content in the same order. The footer keeps the title, the quotation number and "Page X of Y" as Word page fields.

A Word file has no watermark, and `->word()` refuses a document with a `->password()`. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template quotation --as=my-quotation
```

This copies the template to `resources/doc-templates/my-quotation`. Use it with `Doc::template('my-quotation', $data)` and edit:

- `lang/ar.php` and `lang/en.php` for the labels: the opening sentence under `intro`, the closing under `closing`, the column names.
- `template.php` for the fields and the defaults, for example `'quote' => ['currency' => 'SAR', 'tax_rate' => 15]` to always add Saudi VAT.
- `layout.php` for the layout of both formats.
- `footer.blade.php` for the footer.

See [Your own templates](/guide/custom-templates).

## Related {#related}

- Templates: [Tax invoice](/templates/invoice), [Purchase order](/templates/purchase-order), [Contract](/templates/contract)
- Recipes: [A price list built in code](/recipes/price-list-builder), [Email an invoice](/recipes/email-invoice), [Branding per customer](/recipes/multi-tenant-branding), [Download, preview or store](/recipes/controller-responses)
