# Receipt and payment voucher

A receipt voucher (سند قبض) is what the cashier or accountant hands to someone who pays the company; a payment voucher (سند صرف) records money the company pays out. The `receipt` template prints either one on an A5 landscape page, with the amount in figures and in words, the payment method and signature boxes.

<div class="preview">
  <figure><a href="/samples/receipt-ar.pdf" target="_blank"><img src="/previews/receipt-ar.png" alt="Arabic receipt voucher: title سند قبض, the amount 15,750.50 in a box, the amount in words, the cheque details and three signature boxes"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/receipt-en.pdf" target="_blank"><img src="/previews/receipt-en.png" alt="The same receipt voucher with English labels"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A customer pays an invoice instalment in cash or by cheque at your office, and the cashier prints a receipt for them to keep.
- Your app records a bank transfer from a client and emails them a receipt voucher with the transfer reference.
- The finance team pays a supplier or a contractor and needs a signed payment voucher for the expense file.
- A school or a clinic collects fees at the counter and prints a voucher with the amount written in words.
- Petty cash: an employee receives an advance and signs the payment voucher as the receiver.

## Quick example {#example}

Pass the voucher data to the template and set the language:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$voucher = Doc::template('receipt', [
    'type' => 'receipt',
    'number' => 'RV-2026-0315',
    'date' => '2026-10-08',
    'amount' => 15750.50,
    'currency' => 'EGP',
    'party' => 'مؤسسة النور للتجارة',
    'for' => 'الدفعة الثانية من قيمة عقد تطوير نظام إدارة المخزون',
    'method' => 'cheque',
    'cheque' => [
        'number' => '00045871',
        'bank' => 'البنك الأهلي المصري',
        'date' => '2026-10-15',
    ],
    'notes' => 'يعتبر هذا السند لاغياً في حالة عدم صرف الشيك.',
])->locale('ar');
```

Then return the file you need from your controller:

::: code-group

```php [PDF]
return $voucher->pdf()->download('RV-2026-0315.pdf');
```

```php [Word]
return $voucher->word()->download('RV-2026-0315.docx');
```

:::

You get one A5 landscape page, the same as the preview above:

- the title سند قبض, with the voucher number and date in the corner;
- the amount in a box, `المبلغ: 15,750.50 ج.م`, and in words: فقط خمسة عشر ألفاً وسبعمائة وخمسون جنيهاً وخمسون قرشاً لا غير;
- the payment method line: شيك - رقم الشيك: 00045871 - البنك: البنك الأهلي المصري - تاريخ الاستحقاق: 2026/10/15;
- three signature boxes: المسلِّم، أمين الخزينة، المحاسب.

The company name, address and logo at the top come from the theme (see [Theme values](#theme) below). With `->locale('en')` the same data gets English labels and the amount in words reads "fifteen thousand seven hundred fifty EGP and 50/100 only" (English words need the `intl` PHP extension).

## Fields {#fields}

The data is validated before anything is drawn, and a missing or wrong field throws a `ValidationException`. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `type` | Yes | `receipt` or `payment` | `receipt` | Receipt voucher (money in) or payment voucher (money out). Sets the title, the label before `party` and the default signatures. |
| `number` | Yes | text | | The voucher number, printed next to the title and kept left to right. |
| `date` | Yes | date | | The voucher date, printed as `2026/10/08`. |
| `amount` | Yes | number, 0 or more | | The amount. Rounded to the currency's decimals, then printed in figures (in the box) and in words. |
| `currency` | Yes | 3-letter code | `EGP` | Decides the decimals, the short label (ج.م، ر.س ...) and the currency in words. |
| `party` | Yes | text | | Who paid you (استلمنا من / Received from) or whom you paid (صرفنا إلى / Paid to). |
| `for` | Yes | text | | What the money is for (وذلك عن / For). |
| `method` | Yes | `cash`, `cheque`, `transfer`, `card` | `cash` | The payment method. |
| `cheque.number` | When `method` is `cheque` | text | | The cheque number, added to the payment method line. |
| `cheque.bank` | No | text | | The bank the cheque is drawn on. |
| `cheque.date` | No | date | | The cheque due date (تاريخ الاستحقاق). |
| `reference` | No | text | | A transfer or card reference. Adds a "رقم المرجع / Reference" row, kept left to right. |
| `notes` | No | text | | A small grey note under the details. |
| `signatures` | No | list of text | see [Signature boxes](#signatures) | The signature boxes, in order. |

The `cheque` details are printed only when `method` is `cheque`.

### Theme values {#theme}

The template also reads these theme values, which you set once in `config/easy-pdf-word.php` or per document with `->theme([...])` (see [Configuration](/guide/configuration)):

| Theme key | Where it shows |
| --- | --- |
| `company.name` | Top corner, bold, in the primary colour. |
| `company.address` | Under the company name, small and grey. |
| `logo` | Above the company name, 25 mm wide. See [Images](/guide/images) for the allowed folders. |
| `primary` | The title, the line under the header, and the border of the amount box, whose background is a pale tint of the same colour. |
| `muted` | The address, the notes and the signature lines. |
| `border` | The lines of the details table. |

```php
Doc::template('receipt', $data)
    ->theme([
        'primary' => '#1D4ED8',
        'logo' => public_path('images/logo.png'),
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
        ],
    ])
    ->locale('ar')
    ->pdf();
```

## Variants and options {#options}

### Receipt or payment voucher {#receipt-or-payment}

`type` switches between the two documents:

| | `receipt` | `payment` |
| --- | --- | --- |
| Title | سند قبض / Receipt Voucher | سند صرف / Payment Voucher |
| Label before `party` | استلمنا من / Received from | صرفنا إلى / Paid to |
| Default signatures | `payer`, `cashier`, `accountant` | `receiver`, `accountant`, `manager` |

A payment voucher for a supplier paid by bank transfer:

```php
$voucher = Doc::template('receipt', [
    'type' => 'payment',
    'number' => 'PV-2026-0088',
    'date' => '2026-10-12',
    'amount' => 4200,
    'currency' => 'SAR',
    'party' => 'مؤسسة الريان للصيانة',
    'for' => 'صيانة أجهزة التكييف في فرع الرياض عن شهر سبتمبر',
    'method' => 'transfer',
    'reference' => 'TRF-88231940',
])->locale('ar');
```

It prints سند صرف, `المبلغ: 4,200.00 ر.س`, the words فقط أربعة آلاف ومائتا ريال لا غير, the method تحويل بنكي, a رقم المرجع row with `TRF-88231940`, and the signature boxes المستلِم، المحاسب، المدير المالي.

### Payment methods {#payment-methods}

| `method` | Arabic | English | What else is printed |
| --- | --- | --- | --- |
| `cash` | نقداً | Cash | |
| `cheque` | شيك | Cheque | The cheque number (required), then the bank and the due date when given. |
| `transfer` | تحويل بنكي | Bank transfer | Pass `reference` for the transfer number. |
| `card` | بطاقة | Card | Pass `reference` for the card transaction number. |

`reference` works with any method; it adds its own row under the payment method.

### Signature boxes {#signatures}

`signatures` is a list of roles, printed side by side in the order you give. These roles are translated; any other text is printed as you wrote it:

| Role | Arabic | English |
| --- | --- | --- |
| `payer` | المسلِّم | Paid by |
| `receiver` | المستلِم | Received by |
| `cashier` | أمين الخزينة | Cashier |
| `accountant` | المحاسب | Accountant |
| `manager` | المدير المالي | Finance manager |

```php
$data['signatures'] = ['payer', 'cashier', 'مدير الفرع'];
```

This prints three boxes: المسلِّم، أمين الخزينة and مدير الفرع.

### Currency, decimals and words {#currency}

- The amount is rounded to the currency's decimals: 2 for most currencies, 3 for `KWD`, `BHD`, `OMR`, `JOD`, `IQD`, `LYD` and `TND`, 0 for `JPY` and `KRW`. `1250.125` in `KWD` prints as `1,250.125 د.ك`.
- Arabic documents use a short label for `EGP`, `SAR`, `AED`, `KWD`, `QAR`, `BHD`, `OMR`, `JOD`, `USD` and `EUR` (ج.م، ر.س، د.إ ...). Other codes, and every code in English documents, are printed as the code.
- The Arabic words name the currency for `EGP`, `SAR`, `AED`, `QAR`, `KWD`, `USD` and `EUR`. For other codes the number is read in words followed by the code. You can add currencies in the config; see [Arabic support](/guide/arabic).

### Paper size {#paper}

The voucher is A5 landscape by default. To print it on A4 instead:

```php
Doc::template('receipt', $data)->paper('A4', 'portrait')->locale('ar')->pdf();
```

`->paper('A4')` alone keeps the template's landscape orientation. More in [Page settings](/guide/page-settings).

## Word file {#word}

The receipt has a single `layout.php` that builds both files, so the Word file has the same content as the PDF: the header with the logo, the amount box, the details table, the notes and the signature boxes, on an A5 landscape page. There is no page footer in either format.

Word files need `phpoffice/phpword`, and they use a font installed on the reader's computer (Arial by default). See [Word files](/guide/word).

## Customise it {#customise}

Copy the template into your project and edit the copy:

```bash
php artisan doc:template receipt --as=my-receipt
```

This creates `resources/doc-templates/my-receipt/` with:

- `template.php`: the fields, the defaults (for example `'currency' => 'SAR'`) and the paper size;
- `layout.php`: the layout, used for both the PDF and the Word file;
- `lang/ar.php` and `lang/en.php`: every label, such as the titles and the signature roles.

Then use it by its new name: `Doc::template('my-receipt', $data)`. Without `--as`, the copy keeps the name `receipt` and replaces the bundled template in your app. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Tax invoice](/templates/invoice) and [Credit and debit note](/templates/credit-note): the documents a receipt usually settles.
- [Payslip](/templates/payslip): the other money document with the amount in words.
- [Email an invoice](/recipes/email-invoice): attach a voucher to a mail the same way.
- [Download, preview or store](/recipes/controller-responses): other ways to return or save the file.
- [Arabic support](/guide/arabic): amounts in words and currencies.
