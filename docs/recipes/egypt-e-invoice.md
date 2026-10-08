# Print an ETA e-invoice

Print the document you submitted to the Egyptian Tax Authority (ETA) with the `eg-invoice` template: map the ETA document to the template's data once, and every invoice, credit note and debit note prints with its tax registration numbers, item codes, ETA tax types and the portal QR code.

## The situation {#situation}

شركة بيزتك للحلول البرمجية is registered in Egypt's e-invoicing system (منظومة الفاتورة الإلكترونية). Its Laravel app builds each invoice as an ETA document (JSON), signs it and submits it to the ETA API. Customers still want a printed or PDF copy that:

- shows exactly what was submitted: issuer and receiver, item codes, units, discounts, VAT (T1) and withholding tax (T4),
- carries the ETA's electronic number (UUID) and a QR code that opens the document on the ETA portal,
- works the same way for credit notes and debit notes.

The app keeps the submitted document in an `eta_document` JSON column, and stores the UUID and long ID that ETA returns.

## The solution {#solution}

### 1. Keep ETA's answer

When ETA accepts a submission, it answers with each document's `uuid` and `longId`. The portal link in the QR needs both, so store them with the invoice:

```php
// In your submission code, after POST /api/v1/documentsubmissions
foreach ($response['acceptedDocuments'] as $accepted) {
    Invoice::where('number', $accepted['internalId'])->update([
        'eta_uuid' => $accepted['uuid'],
        'eta_long_id' => $accepted['longId'],
        'eta_submission_uuid' => $response['submissionId'],
    ]);
}
```

The `Invoice` model casts the stored document to an array:

```php
// app/Models/Invoice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// columns: id, number, eta_document (json), eta_uuid, eta_long_id, eta_submission_uuid
class Invoice extends Model
{
    protected function casts(): array
    {
        return ['eta_document' => 'array'];
    }
}
```

### 2. Map the ETA document to the template

The [Egyptian e-invoice template](/templates/eg-invoice) uses the ETA's own concepts, so the mapping is short. This class is the whole mapping:

```php
// app/Documents/EtaInvoicePrint.php
namespace App\Documents;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class EtaInvoicePrint
{
    public static function for(Invoice $invoice): PendingDocument
    {
        $eta = $invoice->eta_document;   // the document you submitted to ETA

        return Doc::template('eg-invoice', [
            'document' => [
                'type' => $eta['documentType'],                 // I, C or D
                'internal_id' => $eta['internalID'],
                'issued_at' => $eta['dateTimeIssued'],          // UTC, printed in the app's time zone
                'uuid' => $invoice->eta_uuid,
                'long_id' => $invoice->eta_long_id,
                'purchase_order' => $eta['purchaseOrderReference'] ?? null,
                'currency' => 'EGP',
            ],
            'issuer' => [
                'name' => $eta['issuer']['name'],
                'rin' => $eta['issuer']['id'],
                'branch_id' => $eta['issuer']['address']['branchID'] ?? null,
                'activity_code' => $eta['taxpayerActivityCode'],
                'address' => self::address($eta['issuer']['address'] ?? []),
            ],
            'receiver' => [
                'type' => $eta['receiver']['type'],             // B, P or F
                'id' => $eta['receiver']['id'] ?? null,
                'name' => $eta['receiver']['name'],
                'address' => self::address($eta['receiver']['address'] ?? []),
            ],
            'lines' => array_map(fn (array $line) => [
                'description' => $line['description'],
                'item_type' => $line['itemType'],
                'item_code' => $line['itemCode'],
                'unit' => $line['unitType'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unitValue']['amountEGP'],
                'discount' => $line['discount']['amount'] ?? 0,
                'taxes' => array_map(fn (array $tax) => [
                    'type' => $tax['taxType'],
                    'subtype' => $tax['subType'] ?? null,
                    'rate' => $tax['rate'] ?? null,
                    'amount' => $tax['amount'],
                ], $line['taxableItems'] ?? []),
            ], $eta['invoiceLines']),
            'extra_discount' => $eta['extraDiscountAmount'] ?? 0,
            'notes' => self::references($eta),
        ])->locale('ar');
    }

    /** "15 شارع التحرير، الدقي، الجيزة" from ETA's address fields. */
    private static function address(array $address): ?string
    {
        $street = trim(($address['buildingNumber'] ?? '').' '.($address['street'] ?? ''));

        return implode('، ', array_filter([$street, $address['regionCity'] ?? null, $address['governate'] ?? null])) ?: null;
    }

    /** A credit or debit note names the invoices it corrects. */
    private static function references(array $eta): ?string
    {
        if (empty($eta['references'])) {
            return null;
        }

        $numbers = Invoice::whereIn('eta_uuid', $eta['references'])->pluck('number');

        return 'عن الفاتورة رقم '.$numbers->implode('، ');
    }
}
```

How the fields line up:

| ETA document | Template field |
| --- | --- |
| `documentType` (`I`, `C`, `D`) | `document.type`: the title becomes فاتورة ضريبية إلكترونية, إشعار دائن or إشعار مدين |
| `internalID`, `dateTimeIssued`, `purchaseOrderReference` | `document.internal_id`, `document.issued_at`, `document.purchase_order` |
| `issuer.id`, `issuer.address.branchID`, `taxpayerActivityCode` | `issuer.rin`, `issuer.branch_id`, `issuer.activity_code` |
| `receiver.type` (`B`, `P`, `F`), `receiver.id` | `receiver.type`, `receiver.id`: printed as tax registration number, national ID or passport number |
| `itemType`, `itemCode`, `unitType` | `item_type` (`EGS` or `GS1`), `item_code`, `unit` |
| `unitValue.amountEGP`, `discount.amount` | `unit_price`, `discount` (before tax) |
| `taxableItems[]` | `taxes[]`: `type`, `subtype`, `rate`, `amount` |
| `extraDiscountAmount` | `extra_discount` |

Each tax is passed with ETA's own `amount`. When a tax has an `amount`, the template uses it as it is, so the print shows exactly the amounts ETA accepted. Without `amount`, the template calculates each tax from its `rate` the way ETA does: for example VAT (T1) on the net amount plus table tax and taxable fees, and withholding (T4) on the net amount, deducted from the total.

The template prints `dateTimeIssued`, which is in UTC, in your app's time zone (`config/app.php`, for example `'timezone' => 'Africa/Cairo'`): `2026-10-08T09:45:00Z` prints as `2026/10/08 12:45`.

### 3. The route and controller

```php
// routes/web.php
use App\Http\Controllers\EtaInvoiceController;

Route::get('/invoices/{invoice}/eta-print', EtaInvoiceController::class)->name('invoices.eta-print');
```

```php
// app/Http/Controllers/EtaInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\EtaInvoicePrint;
use App\Models\Invoice;

class EtaInvoiceController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        abort_if($invoice->eta_uuid === null, 404);

        return EtaInvoicePrint::for($invoice)->pdf("فاتورة-{$invoice->number}.pdf");
    }
}
```

The controller prints only documents ETA has accepted: before that there is no UUID and no portal link. With the UUID and long ID set, the template builds the QR as `https://invoicing.eta.gov.eg/documents/{uuid}/share/{longId}`, and prints the UUID near the top of the page. The PDF opens in the browser; the [template page](/templates/eg-invoice) shows what a full invoice looks like.

::: details An ETA document, as the mapping reads it
Only the keys the mapping uses are shown. The full document also has `documentTypeVersion`, the line and document totals, and the signatures.

```php
$eta = [
    'issuer' => [
        'type' => 'B',
        'id' => '123456789',
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => ['branchID' => '0', 'country' => 'EG', 'governate' => 'الجيزة', 'regionCity' => 'الدقي', 'street' => 'شارع التحرير', 'buildingNumber' => '15'],
    ],
    'receiver' => [
        'type' => 'B',
        'id' => '987654321',
        'name' => 'مؤسسة النور للتجارة',
        'address' => ['country' => 'EG', 'governate' => 'القاهرة', 'regionCity' => 'مدينة نصر', 'street' => 'شارع عباس العقاد', 'buildingNumber' => '22'],
    ],
    'documentType' => 'I',
    'dateTimeIssued' => '2026-10-08T09:45:00Z',
    'taxpayerActivityCode' => '6201',
    'internalID' => 'INV-2026-1024',
    'purchaseOrderReference' => 'PO-7781',
    'invoiceLines' => [
        [
            'description' => 'تطوير نظام إدارة المخزون',
            'itemType' => 'EGS', 'itemCode' => 'EG-123456789-1001', 'unitType' => 'EA',
            'quantity' => 1,
            'unitValue' => ['currencySold' => 'EGP', 'amountEGP' => 25000],
            'discount' => ['rate' => 0, 'amount' => 0],
            'taxableItems' => [
                ['taxType' => 'T1', 'amount' => 3500, 'subType' => 'V009', 'rate' => 14],
                ['taxType' => 'T4', 'amount' => 750, 'subType' => 'W010', 'rate' => 3],
            ],
        ],
        [
            'description' => 'استضافة سحابية - اشتراك شهري',
            'itemType' => 'EGS', 'itemCode' => 'EG-123456789-2002', 'unitType' => 'MON',
            'quantity' => 12,
            'unitValue' => ['currencySold' => 'EGP', 'amountEGP' => 450],
            'discount' => ['rate' => 0, 'amount' => 400],
            'taxableItems' => [
                ['taxType' => 'T1', 'amount' => 700, 'subType' => 'V009', 'rate' => 14],
            ],
        ],
    ],
    'extraDiscountAmount' => 0,
];
```

This document prints total sales 30,400.00, discounts 400.00, net 30,000.00, VAT (T1) 4,200.00, withholding (T4) -750.00 and a total of 33,450.00, the same as ETA's `totalAmount`.
:::

::: warning Discounts after tax
The template has fields for discounts before tax (`discount.amount` on a line) and for the document's `extraDiscountAmount`. ETA's `itemsDiscount` (a line discount after tax) and `valueDifference` have no field, so a document that uses them prints a different total. If you use them, compare the printed total with `totalAmount` before you rely on the print.
:::

## Credit and debit notes {#notes}

A credit note (`documentType` `C`) or debit note (`D`) goes through the same class. The title changes to إشعار دائن or إشعار مدين, and the footer repeats it with the note's number. ETA links a note to the invoices it corrects through `references` (their UUIDs); the template has no field for them, so `references()` in the class above looks up their numbers and prints them as a note: `عن الفاتورة رقم INV-2026-1024`.

<div class="preview">
  <figure><a href="/images/recipes-a/eta-credit-note.png" target="_blank"><img src="/images/recipes-a/eta-credit-note.png" alt="An Egyptian e-invoice credit note in Arabic with the ETA electronic number, item codes, VAT and the portal QR code"></a><figcaption>A credit note for two months of hosting, against INV-2026-1024</figcaption></figure>
</div>

For notes outside the ETA system, or for Saudi credit notes with a ZATCA QR, use the [credit and debit note template](/templates/credit-note) instead.

## Variations {#variations}

### Save the print when ETA accepts the document

Render the PDF on a queue worker right after storing ETA's answer, and keep it with the invoice:

```php
EtaInvoicePrint::for($invoice)->queue("eta/{$invoice->eta_uuid}.pdf", 's3');
```

The data is checked against the template's rules before the job is queued, so a mapping mistake fails in your submission code, not in the worker.

### A Word copy

The `eg-invoice` template also makes Word files (it needs `phpoffice/phpword`):

```php
return EtaInvoicePrint::for($invoice)->word("فاتورة-{$invoice->number}.docx")->download();
```

### An English copy for a foreign customer

For a receiver of type `F`, print in English. The amount in words then uses PHP's `intl` extension (thirty-three thousand four hundred fifty EGP only):

```php
return EtaInvoicePrint::for($invoice)->locale('en')->pdf();
```

### Testing against the pre-production portal

The QR links to the production portal. While you test against ETA's pre-production system, point it at the pre-production portal with `qr`, which replaces the built link:

```php
EtaInvoicePrint::for($invoice)
    ->with('qr', "https://preprod.invoicing.eta.gov.eg/documents/{$invoice->eta_uuid}/share/{$invoice->eta_long_id}")
    ->pdf();
```

## Related pages {#related}

- [Egyptian e-invoice](/templates/eg-invoice): every field of the template, with the tax types T1 to T20.
- [Credit and debit note](/templates/credit-note): notes outside the ETA system.
- [Output and delivery](/guide/output): downloads, saving to a disk and queued saving.
- [Download, preview or store](/recipes/controller-responses): more ways to send the PDF from a controller.
