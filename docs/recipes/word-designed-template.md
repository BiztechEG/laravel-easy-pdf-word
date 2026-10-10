# A template designed in Word

Let the person who owns a document's look design it in Microsoft Word, with placeholders such as `${customer.name}`, and let your app fill it with data. This recipe includes a right-to-left starter file you can download and hand to that person.

## The situation {#situation}

A company in Riyadh sends price offers (عروض أسعار) to its customers. The sales manager wants to control how they look: the letterhead, the wording, the table, the fonts. She works in Microsoft Word, not in Blade, and she wants to change the design next month without waiting for a developer.

The offer holds:

- the company's letterhead with its logo;
- the offer number, the customer and the person to contact, the date in Gregorian and Hijri, and the validity date;
- a table with one row per item, then the totals with 15% VAT and the amount in words;
- notes, and the salesperson's name and signature.

The app already stores quotations: `Quotation` (`number`, `issued_at`, `valid_until`, `notes`) with a `customer`, a `salesperson` (a `User` with a `job_title`) and `items` (`description`, `quantity`, `unit_price`).

## The solution {#solution}

### 1. Start from the starter file

Download <a href="/files/recipes-b/word-template-starter.docx" download>word-template-starter.docx</a>: an Arabic price offer with right-to-left paragraphs and tables and every placeholder this recipe uses. Give it to the designer as a starting point, or use it to see how a template is put together.

<div class="preview">
  <figure><img src="/images/recipes-b/word-template-starter.png" alt="The starter file as Word shows it: an Arabic price offer with placeholders in the letterhead, the customer details, a table with one row of item placeholders and the totals, and a signature block"><figcaption>The starter file, as designed in Word</figcaption></figure>
  <figure><img src="/images/recipes-b/word-template-filled.png" alt="The same offer after the app filled it: logo, company details, customer, Hijri date, three item rows, totals with VAT, the amount in words and a signature image"><figcaption>The Word file the app sends</figcaption></figure>
</div>

### 2. The placeholders

A placeholder is `${name}`, typed as plain text anywhere in the file: in a paragraph, a table cell, the header or the footer. Nested data uses dots.

| Placeholder | Filled with |
| --- | --- |
| `${quote.number}`, `${customer.name}` | Your data, nested with dots |
| `${date}` | The offer date; a date object prints as `2026/10/08` |
| `${doc.hijri_date}` | The Hijri date of the top-level `date` value (needs `ext-intl`) |
| `${items.description}`, `${items.quantity}` ... in one table row | That row is repeated for every item |
| `${items.row_number}` | 1, 2, 3 ... in the repeated row |
| `${totals.total}`, `${totals.in_words}` | Values worked out in `template.php` (step 3) |
| `${theme.company.name}`, `${theme.company.phone}` ... | The company details from the theme |
| `${theme.logo:150:60}`, `${signature:150:60}` | An image, 150 × 60 pixels |

Other placeholders the package fills: `${doc.today}` (today's date), `${doc.qr}` (a QR image of a top-level `qr` value) and `${t.key}` (a label from the template's `lang` files, see [Variations](#variations)).

Tell the designer these rules:

- **Type each placeholder in one go, in one style**, or paste it as plain text. Word stores a placeholder whose formatting changes halfway in several pieces; the filler joins most of them back, but check the list before the file goes live (see [Check a design before it goes live](#check)).
- **One row per list.** Put all the `${items.*}` placeholders in one table row. The package copies that row for every item; the rows after it (the totals here) stay as they are.
- **Right to left is set in Word.** Select the text and use the "Right-to-left Text Direction" button (Home > Paragraph), and set each table's direction to right to left in Table Properties. The filled file keeps these settings.
- **Word displays placeholders oddly in Arabic paragraphs.** In a right-to-left paragraph Word shows `${customer.name}` as `{customer.name}$`. The file still holds `${customer.name}` and it is filled correctly.
- **An image placeholder sits alone** in its paragraph or cell, with its size in pixels after colons. A picture that never changes, such as a fixed logo, can be inserted as an ordinary picture.
- **Use fonts your readers have.** Word does not embed fonts; Arial, Tahoma, Sakkal Majalla and Simplified Arabic are safe for Arabic.

### 3. The template folder

Put the file in a template folder as `word.docx`, next to a `template.php` that checks the data and works out the totals:

```text
resources/doc-templates/price-offer/
    word.docx       the file designed in Word
    template.php    fields (validation) and the totals
```

```php
<?php
// resources/doc-templates/price-offer/template.php

use BiztechEG\EasyPdfWord\Arabic\Arabic;

/*
| Price offer designed in Word by the sales team (word.docx in this folder).
| This file checks the data and adds the totals before Word is filled.
*/

return [
    'title' => 'Price offer',
    'description' => 'Price offer designed in Microsoft Word.',

    'fields' => [
        'quote.number' => ['required', 'string'],
        'quote.valid_until' => ['required', 'date'],
        'date' => ['required', 'date'],
        'customer.name' => ['required', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:1'],
        'items.*.unit_price' => ['required', 'numeric', 'min:0'],
    ],

    'prepare' => function (array $data): array {
        $subtotal = 0.0;

        foreach ($data['items'] as $i => $item) {
            // Floats are printed as amounts: 1,450.00
            $data['items'][$i]['unit_price'] = (float) $item['unit_price'];
            $data['items'][$i]['total'] = round($item['quantity'] * $item['unit_price'], 2);
            $subtotal += $data['items'][$i]['total'];
        }

        $vat = round($subtotal * 0.15, 2);
        $total = round($subtotal + $vat, 2);

        $data['totals'] = [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'in_words' => Arabic::tafqeet($total, 'SAR', only: true),
        ];

        return $data;
    },
];
```

How values are printed in the Word file:

- **Floats** get thousands separators and two decimals: `1450.0` becomes `1,450.00`. With a `currency` such as `KWD`, at the top level or in a group (`quote.currency`), they get that currency's decimals.
- **Whole numbers and strings** are printed as they are: the quantity `4` stays `4`, and a price stored as `1450` or `"1450.00"` would print without separators. That is why `prepare` turns `unit_price` into a float.
- **Dates** (`Carbon` and other date objects) print as `Y/m/d`; a date string prints as you pass it.
- Digits follow `->numerals()`, every value is escaped, and a value that contains `${...}` stays plain text.

The folder has no PDF page (`pdf.html.php`) or `layout.php`, so this template makes Word files only. The package finds it like any other template: `resources/doc-templates` is searched first, then the bundled templates.

### 4. Fill it from a controller

```php
// routes/web.php
use App\Http\Controllers\QuotationWordController;

Route::get('/quotations/{quotation}/word', [QuotationWordController::class, 'show'])
    ->middleware('auth')
    ->name('quotations.word');
```

```php
// app/Http/Controllers/QuotationWordController.php
namespace App\Http\Controllers;

use App\Models\Quotation;
use BiztechEG\EasyPdfWord\Facades\Doc;

class QuotationWordController extends Controller
{
    public function show(Quotation $quotation)
    {
        $quotation->load('customer', 'items', 'salesperson');

        return Doc::template('price-offer', [
            'date' => $quotation->issued_at,
            'quote' => [
                'number' => $quotation->number,
                'valid_until' => $quotation->valid_until,
                'notes' => $quotation->notes,
            ],
            'customer' => [
                'name' => $quotation->customer->name,
                'contact' => $quotation->customer->contact_name,
            ],
            'items' => $quotation->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]),
            'salesperson' => [
                'name' => $quotation->salesperson->name,
                'title' => $quotation->salesperson->job_title,
            ],
            'signature' => storage_path("app/signatures/{$quotation->salesperson_id}.png"),
        ])
            ->locale('ar')
            ->word()
            ->download("عرض-سعر-{$quotation->number}.docx");
    }
}
```

Notes on the data:

- `date` sits at the top level because `${doc.hijri_date}` is made from it. `issued_at` and `valid_until` are cast to dates on the model, so they print as `2026/10/08`.
- The letterhead's `${theme.company.*}` and `${theme.logo}` come from the theme in `config/easy-pdf-word.php`; pass `->theme([...])` to override them for one document.
- The signature is a file path. Images are read only from `public/`, `storage/app` and `resources/` by default, and only when they are real images. PNG, JPEG and GIF go in as they are, WebP and BMP are turned into PNG, and an SVG leaves the placeholder empty.
- When a salesperson has no signature file yet, the placeholder is left empty: a path the package cannot read (a missing file, or one outside the allowed folders) is never printed as text.
- Word files need `phpoffice/phpword`.

## Check a design before it goes live {#check}

When the manager sends a new `word.docx`, list the placeholders Word kept whole:

```bash
php artisan tinker
> (new \PhpOffice\PhpWord\TemplateProcessor(resource_path('doc-templates/price-offer/word.docx')))->getVariables();
```

A placeholder that is missing from the list, or shows up cut in two, was broken by Word (by autocorrect, for example): retype it in one go. Then keep a test that fills the file and fails if anything is left unfilled:

```php
// tests/Feature/PriceOfferTemplateTest.php
namespace Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Tests\TestCase;
use ZipArchive;

class PriceOfferTemplateTest extends TestCase
{
    public function test_the_price_offer_fills_every_placeholder(): void
    {
        $docx = Doc::template('price-offer', [
            'date' => '2026-10-08',
            'quote' => ['number' => 'QT-2026-0088', 'valid_until' => '2026-10-22', 'notes' => 'التوريد خلال 10 أيام عمل.'],
            'customer' => ['name' => 'شركة الأفق للمقاولات', 'contact' => 'م. خالد العتيبي'],
            'items' => [['description' => 'طابعة فواتير حرارية', 'quantity' => 4, 'unit_price' => 1450]],
            'salesperson' => ['name' => 'سارة القحطاني', 'title' => 'مديرة المبيعات'],
        ])->locale('ar')->word()->content();

        $file = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, $docx);
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        $this->assertStringNotContainsString('${', $xml);
        $this->assertStringContainsString('6,670.00', $xml);   // 4 × 1,450 + 15% VAT
    }
}
```

`word/document.xml` holds the body of the file; the header and footer are in `word/header1.xml` and `word/footer1.xml`.

## Variations {#variations}

### Your own Word design for a bundled template

Keep the bundled PDF of the tax invoice but send a Word file in your own design. Copy the template into your project, then add your `word.docx` to the copy:

```bash
php artisan doc:template invoice
# then save your design as resources/doc-templates/invoice/word.docx
```

`->word()` now fills your file, and `->pdf()` still uses the template's PDF layout. The placeholders are the invoice's data after its `prepare()`: `${invoice.number}`, `${seller.name}`, `${buyer.name}`, `${items.description}` and `${items.total}` in a table row, `${totals.total}`, and `${doc.qr:120:120}` for the QR code. The [Tax invoice](/templates/invoice) page lists every field.

### A PDF from the same folder

`->pdf()` on the `price-offer` template fails with "Template [price-offer] has no pdf.html.php or pdf.blade.php.", because Word files cannot be turned into PDFs. Add a `pdf.html.php` or a `layout.php` to the folder for the PDF; `word.docx` stays in charge of the Word file. See [Your own templates](/guide/custom-templates).

### Labels in two languages

Put the labels in `lang/ar.php` and `lang/en.php` in the template folder and write `${t.key}` in Word:

```php
<?php
// resources/doc-templates/price-offer/lang/en.php

return [
    'title' => 'Price offer',
    'valid_until' => 'Valid until',
];
```

`${t.title}` prints "Price offer" with `->locale('en')` and the Arabic label with `->locale('ar')`. Keep two `.docx` designs (two template folders) if the two languages need different layouts.

### Arabic digits

Add `->numerals('arabic')`: `6,670.00` becomes `٦,٦٧٠.٠٠` and dates `٢٠٢٦/١٠/٠٨`. Word files keep the `,` and `.` separators, as the font is chosen on the reader's machine.

## Related pages {#related}

- [Word files](/guide/word): Word output, fonts and right-to-left settings.
- [Your own templates](/guide/custom-templates): `template.php`, `prepare()` and which file makes which format.
- [Images](/guide/images): allowed folders, remote images and formats.
- [Arabic support](/guide/arabic): amounts in words, Hijri dates and digits.
- [Price quotation](/templates/quotation): the bundled quotation, if a code layout is enough.
