# Templates

The package ships twelve ready-made templates, each in Arabic and English, as a PDF and as a Word file. Use this page to find the one you need, then open its page for the fields, the options and a working example.

<div class="gallery">
  <a href="/templates/invoice"><img src="/previews/invoice-en.png" alt="Tax invoice preview"><strong>Tax invoice</strong><span>VAT, amount in words, ZATCA or link QR</span></a>
  <a href="/templates/eg-invoice"><img src="/previews/eg-invoice-en.png" alt="Egyptian e-invoice preview"><strong>Egyptian e-invoice</strong><span>ETA invoice, credit and debit notes</span></a>
  <a href="/templates/credit-note"><img src="/previews/credit-note-en.png" alt="Credit note preview"><strong>Credit and debit note</strong><span>Corrects an invoice already issued</span></a>
  <a href="/templates/quotation"><img src="/previews/quotation-en.png" alt="Price quotation preview"><strong>Price quotation</strong><span>Validity date, terms and signature</span></a>
  <a href="/templates/receipt"><img src="/previews/receipt-en.png" alt="Receipt voucher preview"><strong>Receipt and payment voucher</strong><span>Money in or out, in figures and words</span></a>
  <a href="/templates/purchase-order"><img src="/previews/purchase-order-en.png" alt="Purchase order preview"><strong>Purchase order</strong><span>Order to a supplier, with approvals</span></a>
  <a href="/templates/delivery-note"><img src="/previews/delivery-note-en.png" alt="Delivery note preview"><strong>Delivery note</strong><span>Delivered and remaining quantities, no prices</span></a>
  <a href="/templates/payslip"><img src="/previews/payslip-en.png" alt="Payslip preview"><strong>Payslip</strong><span>Earnings, deductions and net pay</span></a>
  <a href="/templates/contract"><img src="/previews/contract-en.png" alt="Contract preview"><strong>Contract</strong><span>Parties, numbered clauses, signatures</span></a>
  <a href="/templates/certificate"><img src="/previews/certificate-en.png" alt="Certificate preview"><strong>Certificate</strong><span>Landscape, with a verification QR</span></a>
  <a href="/templates/letter"><img src="/previews/letter-en.png" alt="Official letter preview"><strong>Official letter</strong><span>Letterhead, reference and Hijri date</span></a>
  <a href="/templates/report"><img src="/previews/report-en.png" alt="Table report preview"><strong>Table report</strong><span>Any rows, totals, header on every page</span></a>
</div>

Each picture is the first page of the template's sample data in English, with a company name and logo set in the theme. Click one to open its page, where the Arabic version is shown too.

## All templates at a glance {#at-a-glance}

The name in the first column is what you pass to `Doc::template()`.

| Template | What it is | Paper | Word layout |
| --- | --- | --- | --- |
| `invoice` | Tax invoice: seller, buyer, items, discount, VAT, total in words, Hijri date, ZATCA or link QR | A4 portrait | Yes, `word.php` (PDF from `pdf.html.php`) |
| `eg-invoice` | Egyptian Tax Authority (ETA) e-invoice, credit or debit note, with item codes and ETA tax types | A4 portrait | Yes, `layout.php` (one layout for both) |
| `credit-note` | Credit or debit note against an issued invoice, with the reason, VAT and an optional QR | A4 portrait | Yes, `layout.php` |
| `quotation` | Price quotation with optional VAT, validity date, terms and the sender's signature | A4 portrait | Yes, `layout.php` |
| `purchase-order` | Purchase order to a supplier with delivery details, payment terms and approval signatures | A4 portrait | Yes, `layout.php` |
| `delivery-note` | Delivery note without prices: ordered, delivered and remaining quantities, transport, signatures | A4 portrait | Yes, `layout.php` |
| `receipt` | Receipt voucher or payment voucher: amount in figures and words, cash, cheque, transfer or card | A5 landscape | Yes, `layout.php` |
| `payslip` | Monthly payslip: earnings and deductions, net pay in words, attendance, signatures | A4 portrait | Yes, `layout.php` |
| `contract` | Contract between two or more parties: preamble, numbered clauses, copies, signatures, witnesses | A4 portrait | Yes, `layout.php` |
| `certificate` | Certificate of completion, attendance, participation or appreciation, with a verification QR | A4 landscape | Yes, `word.php` (PDF from `pdf.html.php`) |
| `letter` | Official letter: letterhead, reference number, Gregorian and Hijri dates, signature and stamp | A4 portrait | Yes, `word.php` (PDF from `pdf.html.php`) |
| `report` | Table report from any rows: chosen columns, totals row, summary cards, header on every page | A4 portrait | Yes, `word.php` (PDF from `pdf.html.php`) |

Every template has a Word layout, so `->word()` works for all of them. Word files need `phpoffice/phpword`; see [Word files](/guide/word). The paper size is the template's default: change it per document with `->paper()` and `->landscape()` (see [Page settings](/guide/page-settings)).

## Which template do I need? {#which-template}

- **You bill a customer** and need a tax invoice with VAT: `invoice`. It covers Egypt (14% VAT, the default), Saudi Arabia (15% VAT with the ZATCA QR) and other Gulf currencies.
- **You submit invoices to the Egyptian Tax Authority** and want the printed copy with tax registration numbers, item codes and ETA tax types (T1 to T20): `eg-invoice`. It also prints ETA credit and debit notes.
- **You correct an invoice you already issued** (a return, a cancelled service, a price difference): `credit-note`, as a credit note or a debit note.
- **You offer prices before a sale**: `quotation`.
- **You buy from a supplier**: `purchase-order`.
- **You hand over goods** and need a signed record without prices: `delivery-note`.
- **You receive or pay out money**: `receipt`, as a receipt voucher (سند قبض) or a payment voucher (سند صرف).
- **You pay salaries**: `payslip`.
- **Two or more parties sign an agreement**: `contract`.
- **You run a course or an event**: `certificate`.
- **You write formal correspondence**: `letter`.
- **You print a list of rows** from a query (sales, stock, attendance): `report`.

None fits? Copy the closest one and change it, or build your own: see [Your own templates](/guide/custom-templates).

## Use a template {#use}

Pass the template's name and its data, pick the language, then ask for a PDF or a Word file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$quote = Doc::template('quotation', $data)->locale('ar');

return $quote->pdf()->download('QT-2026-0088.pdf');
// or: return $quote->word()->download('QT-2026-0088.docx');
```

Three things happen before anything is drawn:

1. The template's defaults fill the keys you left out (for example the currency).
2. The data is validated against the template's `fields` rules. Missing or wrong data throws Laravel's `ValidationException`, so in a request the user gets the usual validation errors.
3. The template's `prepare()` step adds the computed values: line totals, VAT, the total, the QR code and so on.

Your company name, logo and colours come from the theme: set them once in `config/easy-pdf-word.php` under `theme`, or per document with `->theme([...])`. [Ready-made templates](/guide/templates) explains data, themes and languages in more detail.

## List the templates {#list}

```bash
php artisan doc:templates
```

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| certificate    | Certificate        | ar, en  | PDF, Word | package |
| contract       | Contract           | ar, en  | PDF, Word | package |
| credit-note    | Credit note        | ar, en  | PDF, Word | package |
| delivery-note  | Delivery note      | ar, en  | PDF, Word | package |
| eg-invoice     | Egyptian e-invoice | ar, en  | PDF, Word | package |
| invoice        | Tax invoice        | ar, en  | PDF, Word | package |
| letter         | Official letter    | ar, en  | PDF, Word | package |
| payslip        | Payslip            | ar, en  | PDF, Word | package |
| purchase-order | Purchase order     | ar, en  | PDF, Word | package |
| quotation      | Price quotation    | ar, en  | PDF, Word | package |
| receipt        | Receipt voucher    | ar, en  | PDF, Word | package |
| report         | Table report       | ar, en  | PDF, Word | package |
+----------------+--------------------+---------+-----------+---------+
```

Templates you copied or made yourself appear in the same list with `project` as their source.

## Preview them {#preview}

In the `local` environment, open `/doc-preview` in your app. It lists every template and shows it with its sample data; switch the language, the digits and the PDF engine, open the PDF or download the Word file. Outside `local` the page is off unless you turn it on and allow it with a gate: see [Preview page](/guide/preview).

Without a browser, render a template's sample data to a file:

```bash
# storage/app/doc-samples/eg-invoice-ar.pdf
php artisan doc:sample eg-invoice

# The extension of --output picks the format: .pdf, .docx or .html
php artisan doc:sample invoice --locale=en --output=invoice-en.docx

# Arabic digits, another engine
php artisan doc:sample quotation --numerals=arabic --driver=chromium
```

`--locale` defaults to `ar`. The sample data lives in each template's `template.php`, and you can use it in code too, for example in a test:

```php
$sample = Doc::templates()->get('invoice')->sample();

$pdf = Doc::template('invoice', $sample)->locale('ar')->pdf();
```

## Copy and customise {#copy}

To change a template's layout, labels or defaults, copy it into your app:

```bash
php artisan doc:template invoice --as=my-invoice
```

This copies the whole folder to `resources/doc-templates/my-invoice`. Edit it there and use it by its new name:

```php
Doc::template('my-invoice', $data)->locale('ar')->pdf();
```

Copying without `--as` keeps the same name, and your copy then replaces the bundled template everywhere in the app. Add `--force` to overwrite a copy you made earlier. Each template page ends with what to edit in its copy; [Your own templates](/guide/custom-templates) explains the folder and how to build a template from scratch.
