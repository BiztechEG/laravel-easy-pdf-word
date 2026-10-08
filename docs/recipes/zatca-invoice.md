# Saudi ZATCA invoice

Issue a simplified tax invoice for a Saudi shop: 15% VAT in riyals, the ZATCA phase 1 QR code, the Hijri date and Arabic digits. The recipe also shows how to build the QR yourself for your own layouts, and what phase 1 and phase 2 mean for your app.

## The situation {#situation}

A perfume shop in Riyadh, مؤسسة النور للتجارة, sells to walk-in and online customers. Every sale needs a simplified tax invoice (فاتورة ضريبية مبسطة) that the customer can print or keep on their phone. It must show:

- the seller's name, VAT registration number and commercial registration,
- the items, VAT at 15% and the total in Saudi riyals, with the amount in words,
- the Gregorian and Hijri dates, with Arabic digits (١٢٣),
- the phase 1 QR code of the Zakat, Tax and Customs Authority (هيئة الزكاة والضريبة والجمارك): seller name, VAT number, time of the invoice, total with VAT and VAT amount.

## The solution {#solution}

### 1. A copy of the invoice template with the simplified title

The bundled [tax invoice](/templates/invoice) is titled فاتورة ضريبية (Tax Invoice). Copy it for consumer sales and change only the title:

```bash
php artisan doc:template invoice --as=simplified-invoice
```

The copy is in `resources/doc-templates/simplified-invoice`. Change one line in each language file:

```php
// resources/doc-templates/simplified-invoice/lang/ar.php
'title' => 'فاتورة ضريبية مبسطة',

// resources/doc-templates/simplified-invoice/lang/en.php
'title' => 'Simplified Tax Invoice',
```

Fields, VAT and QR work as in the original. A copy does not receive later changes to the bundled template, so keep your edits small; see [Your own templates](/guide/custom-templates).

### 2. The seller in the config

The template reads the seller from the theme's company, and the QR takes the seller's name and VAT number from there. Write them exactly as registered with ZATCA:

```php
// config/easy-pdf-word.php
'theme' => [
    // primary, text, muted, border, logo ...
    'company' => [
        'name' => 'مؤسسة النور للتجارة',
        'address' => 'طريق الملك فهد، حي العليا، الرياض',
        'phone' => '+966 11 000 0000',
        'tax_number' => '300000000000003',       // VAT number, 15 digits
        'commercial_register' => '1010123456',
    ],
],
```

### 3. The document

The shop has a `Sale` model (`number`, `customer_name`, `created_at`) with `SaleItem` lines (`name`, `quantity`, `unit_price` before VAT, cast to `decimal:2`). One class turns a sale into the invoice:

```php
// app/Documents/SaleInvoice.php
namespace App\Documents;

use App\Models\Sale;
use App\Models\SaleItem;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class SaleInvoice
{
    public static function for(Sale $sale): PendingDocument
    {
        return Doc::template('simplified-invoice', [
            'invoice' => [
                'number' => $sale->number,
                'date' => $sale->created_at->timezone('Asia/Riyadh'),   // date and time, also in the QR
                'currency' => 'SAR',
                'tax_rate' => 15,
            ],
            'buyer' => ['name' => $sale->customer_name ?: 'عميل نقدي'],
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'description' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]),
            'qr' => 'zatca',
        ])
            ->locale('ar')
            ->numerals('arabic');
    }
}
```

What each part does:

- `'qr' => 'zatca'` makes the template build the phase 1 QR from the seller, `invoice.date` and the totals it has just calculated, so the QR always matches the printed amounts.
- `invoice.date` carries the time of the sale. The QR stores it in UTC (`2026-10-08T15:42:10Z`). `->timezone('Asia/Riyadh')` makes the printed date the Saudi one even when your app runs in UTC: a sale at 01:30 in Riyadh is still printed on that day.
- The template requires a buyer name. A walk-in customer is `عميل نقدي` (cash customer).
- `->numerals('arabic')` prints every digit as ١٢٣, including the invoice number and dates. The QR content is not changed: it keeps Latin digits, as ZATCA expects.
- The Hijri date is printed under the issue date for Arabic documents, in the Umm al-Qura calendar. It needs PHP's `intl` extension; without it the row is left out.

### 4. The route and controller

```php
// routes/web.php
use App\Http\Controllers\SaleInvoiceController;

Route::get('/sales/{sale}/invoice', SaleInvoiceController::class)->name('sales.invoice');
```

```php
// app/Http/Controllers/SaleInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\SaleInvoice;
use App\Models\Sale;

class SaleInvoiceController extends Controller
{
    public function __invoke(Sale $sale)
    {
        return SaleInvoice::for($sale->load('items'))->pdf("فاتورة-{$sale->number}.pdf");
    }
}
```

Returning the PDF shows it in the browser, ready to print. Put the route behind your usual authentication, or give the customer a signed link. For a sale of 450.00, 2 × 85.00 and 25.00, the result is:

<div class="preview">
  <figure><a href="/images/recipes-a/zatca-invoice.png" target="_blank"><img src="/images/recipes-a/zatca-invoice.png" alt="A simplified tax invoice in Arabic with Arabic digits, the Hijri date, 15% VAT in riyals and the ZATCA QR code"></a><figcaption>Subtotal 645.00, VAT 96.75, total 741.75 SAR</figcaption></figure>
</div>

## What the QR contains {#qr}

The phase 1 QR is five fields in ZATCA's TLV format (tag, length, value), encoded in base64. For the sale above:

| Tag | Field | Value |
| --- | --- | --- |
| 1 | Seller name | مؤسسة النور للتجارة |
| 2 | VAT registration number | 300000000000003 |
| 3 | Time of the invoice (UTC) | 2026-10-08T15:42:10Z |
| 4 | Total with VAT | 741.75 |
| 5 | VAT amount | 96.75 |

Lengths are counted in bytes, so Arabic names are encoded correctly. Each value may be at most 255 bytes; a longer seller name throws an `InvalidArgumentException`. Totals are written with two decimals.

`ZatcaQr::decode()` reads a QR's content back into these fields, which is handy in tests:

```php
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

ZatcaQr::decode($base64);
// [1 => 'مؤسسة النور للتجارة', 2 => '300000000000003', 3 => '2026-10-08T15:42:10Z', 4 => '741.75', 5 => '96.75']
```

## Your own layouts: build the QR with ZatcaQr {#zatca-qr}

If you print receipts from your own Blade view, or build a document in code, make the QR with `ZatcaQr`:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

$qr = ZatcaQr::make(
    sellerName: 'مؤسسة النور للتجارة',
    vatNumber: '300000000000003',
    timestamp: $sale->created_at,
    total: 741.75,        // with VAT
    vatTotal: 96.75,
);

$qr->toBase64();    // the QR content, in ZATCA's TLV format
$qr->toDataUri();   // a PNG of the QR as a data URI, for an img tag

return Doc::view('pdf.pos-receipt', ['sale' => $sale, 'zatcaQr' => $qr->toBase64()])
    ->locale('ar')
    ->pdf();
```

`timestamp` takes a `Carbon` date or a string. A date without a time, such as `'2026-10-08'`, keeps its day. `total` and `vatTotal` also accept formatted strings like `'1,150.00'`. Pass the same totals you print; the QR does not calculate them.

In the view, the package's QR component draws the code:

```blade
{{-- resources/views/pdf/pos-receipt.blade.php --}}
<x-doc::layout :doc="$doc">
    <h2>فاتورة ضريبية مبسطة</h2>
    <p>رقم الفاتورة: {{ $doc->ltr($sale->number) }}</p>
    <x-doc::qr :value="$zatcaQr" size="30mm" />
</x-doc::layout>
```

In a document built in code, add it as a block. The same document also makes a Word file:

```php
Doc::make()
    ->heading('فاتورة ضريبية مبسطة')
    ->qr($qr->toBase64(), 30)
    ->locale('ar')
    ->pdf();
```

In a template designed in Word, pass the base64 text as `qr` and put `${doc.qr}` where the image goes; see [A template designed in Word](/recipes/word-designed-template).

## Phase 1 and phase 2 {#phases}

ZATCA's e-invoicing (الفوترة الإلكترونية) has two phases:

- **Phase 1, generation** (in force since 4 December 2021): invoices are issued by a system, not by hand, with the required fields, and simplified invoices carry the QR code above. This is what the package covers: the printed invoice and the phase 1 QR.
- **Phase 2, integration** (rolled out in waves since 1 January 2023): each invoice is also an XML file (UBL 2.1), signed with a cryptographic stamp and chained by hash, and sent to ZATCA's FATOORA platform: standard invoices are cleared before they go to the buyer, simplified invoices are reported within 24 hours. The phase 2 QR has more fields, such as the invoice hash and the signature.

::: warning Phase 2 is not part of the package
The package does not create the XML, sign it, or talk to FATOORA. If your business is in a phase 2 wave, use a ZATCA-compliant solution (the ZATCA SDK or a provider) for that. It gives you the QR content for each invoice: pass it as `qr` instead of `'zatca'`, and the template prints it as it is.

```php
'qr' => $phase2Qr,   // base64 text from your phase 2 solution
```
:::

## Variations {#variations}

### Prices that include VAT

Shelf prices in Saudi shops usually include VAT, while the template takes prices before VAT. Divide by 1.15 and let the template round each line:

```php
'unit_price' => $item->price_with_vat / 1.15,
```

::: warning Check the total
The template rounds each line to halalas and calculates VAT on the invoice total. Most baskets come back to the shelf price, but not all: three separate items at 10.00 riyals each give lines of 8.70, a VAT of 3.92 and a total of 30.02. If your system stores prices with VAT, compare the invoice total with the amount you charged, or store prices before VAT.
:::

### A standard tax invoice for a business customer

For a VAT-registered business buyer, use the bundled `invoice` template, titled فاتورة ضريبية, and add the buyer's address and VAT number. The QR is required on simplified invoices; you can keep it on standard ones:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-2026-000093', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15],
    'buyer' => [
        'name' => 'شركة الأفق للمقاولات',
        'address' => 'حي الملقا، الرياض',
        'tax_number' => '311111111100003',
    ],
    'items' => [
        ['description' => 'عطور ضيافة للمكاتب', 'quantity' => 10, 'unit_price' => 120],
    ],
    'qr' => 'zatca',
])->locale('ar')->numerals('arabic')->pdf();
```

### A return: credit note

A refund needs a credit note against the original invoice. The [credit note template](/templates/credit-note) builds the same phase 1 QR:

```php
Doc::template('credit-note', [
    'type' => 'credit',
    'note' => ['number' => 'CN-2026-000012', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15],
    'invoice' => ['number' => $sale->number, 'date' => $sale->created_at],
    'reason' => 'إرجاع منتج',
    'buyer' => ['name' => 'عميل نقدي'],
    'items' => [
        ['description' => 'بخور معطر', 'quantity' => 1, 'unit_price' => 85],
    ],
    'qr' => 'zatca',
])->locale('ar')->numerals('arabic')->pdf();
```

### Email it or keep it

The invoice is an ordinary document: attach it to a mail or save it to a disk as in [Email an invoice](/recipes/email-invoice) and [Download, preview or store](/recipes/controller-responses).

## Related pages {#related}

- [Tax invoice](/templates/invoice) and [Credit and debit note](/templates/credit-note): the templates and all their fields.
- [Arabic support](/guide/arabic): Arabic digits, Hijri dates and amounts in words.
- [Your own templates](/guide/custom-templates): copying a template and changing its labels.
- [Template helpers](/reference/template-helpers): `$doc->ltr()`, `$doc->hijri()` and the QR component.
