# Ready-made templates

The package ships twelve templates, each in Arabic and English, as PDF and Word. This page explains what all of them have in common: how to pass data, how it is checked, where the company details and labels come from, and how to list, copy and override templates. Each template's own fields are on its page in the [template gallery](/templates/).

## Make a document from a template {#usage}

Call `Doc::template()` with the template's name and your data, choose the language, then make the file:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$pdf = Doc::template('quotation', [
    'quote' => [
        'number' => 'QT-2026-0088',
        'date' => '2026-10-08',
        'valid_until' => '2026-11-07',
        'currency' => 'EGP',
        'tax_rate' => 14,
    ],
    'customer' => ['name' => 'مؤسسة النور للتجارة', 'phone' => '+20 122 555 0100'],
    'items' => [
        ['description' => 'تطوير النظام (Laravel)', 'unit' => 'مرحلة', 'quantity' => 1, 'unit_price' => 42000],
        ['description' => 'تدريب المستخدمين', 'unit' => 'يوم', 'quantity' => 3, 'unit_price' => 1500],
    ],
])->locale('ar')->pdf();
```

The data is a nested array whose keys follow the template's fields. Laravel collections and Eloquent models are turned into arrays first, so `'items' => $order->items` works when the attribute names match the fields (`description`, `quantity`, `unit_price`).

You can also add data step by step. `->data()` replaces whole top-level keys, and `->with()` sets one:

```php
$invoice = Doc::template('invoice')
    ->data($data)
    ->with('qr', 'zatca');   // the Saudi ZATCA QR code
```

The `Doc::template()` call returns the same object as every other document, so all the settings work: `->locale()`, `->numerals()`, `->theme()`, `->paper()`, `->footer()`, `->watermark()` and the rest. See [Page settings](/guide/page-settings).

## The twelve templates {#list-of-templates}

| Name | Page | Main data keys |
| --- | --- | --- |
| `invoice` | [Tax invoice](/templates/invoice) | `invoice`, `seller`, `buyer`, `items`, `qr` |
| `eg-invoice` | [Egyptian e-invoice](/templates/eg-invoice) | `document`, `issuer`, `receiver`, `lines` |
| `credit-note` | [Credit and debit note](/templates/credit-note) | `type`, `note`, `invoice`, `reason`, `buyer`, `items` |
| `quotation` | [Price quotation](/templates/quotation) | `quote`, `customer`, `items`, `terms`, `sender` |
| `purchase-order` | [Purchase order](/templates/purchase-order) | `order`, `supplier`, `delivery`, `items`, `terms` |
| `delivery-note` | [Delivery note](/templates/delivery-note) | `delivery`, `customer`, `items`, `transport` |
| `receipt` | [Receipt and payment voucher](/templates/receipt) | `type`, `number`, `date`, `amount`, `party`, `for`, `method` |
| `payslip` | [Payslip](/templates/payslip) | `period`, `employee`, `earnings`, `deductions` |
| `contract` | [Contract](/templates/contract) | `contract`, `parties`, `clauses`, `witnesses` |
| `certificate` | [Certificate](/templates/certificate) | `type`, `gender`, `recipient`, `course`, `signatures` |
| `letter` | [Official letter](/templates/letter) | `date`, `recipient`, `subject`, `body`, `sender` |
| `report` | [Table report](/templates/report) | `title`, `columns`, `rows`, `sum`, `summary` |

## The data is validated {#validation}

Each template lists Laravel validation rules for its data, under `fields` in its `template.php`. The data is checked before the file is rendered, and before a job is queued with `->queue()`. Data that does not pass throws Laravel's `Illuminate\Validation\ValidationException`, with the usual messages:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-1024', 'date' => '2026-02-30'],
    'items' => [],
])->pdf();
```

```text
Illuminate\Validation\ValidationException
The invoice.date field must be a valid date. (and 2 more errors)
```

`$e->errors()` lists every problem by field:

```php
[
    'invoice.date' => ['The invoice.date field must be a valid date.'],
    'buyer.name' => ['The buyer.name field is required.'],
    'items' => ['The items field is required.'],
]
```

Since it is a normal `ValidationException`, Laravel handles it as it handles a form: an API request gets a 422 JSON response with these errors, and a web request is redirected back with them. Usually the data comes from your database rather than a form, so an error here points to a bug in the code that builds the data.

Templates also check what rules cannot. In the invoice, quotation, purchase order, credit note and Egyptian e-invoice, a line discount larger than the line amount fails with `items.0.discount` (or `lines.0.discount`): "The discount cannot be more than the line amount (quantity × unit price)."

If you have already validated the data yourself, `->withoutValidation()` skips the rules. The template may then fail on a missing key with a less helpful error, so keep validation on unless you have a reason.

## Defaults and computed values {#defaults}

Templates fill in what you leave out. Their `defaults` are merged under your data before it is checked. The invoice, for example, defaults to Egyptian pounds and 14% VAT:

```php
Doc::template('invoice', [
    'invoice' => ['number' => 'INV-1025', 'date' => now()],
    'buyer' => ['name' => 'مؤسسة النور للتجارة'],
    'items' => [
        ['description' => 'استشارات تقنية', 'quantity' => 2, 'unit_price' => 1000],
    ],
])->locale('ar')->pdf();   // EGP and 14% VAT: the total is 2,280.00
```

For a Saudi invoice, pass the currency and rate: `'invoice' => ['number' => 'INV-1025', 'date' => now(), 'currency' => 'SAR', 'tax_rate' => 15]`.

Then the template's `prepare` step adds the computed values: line totals, subtotal, discount, tax and total, row numbers, the ZATCA QR code from `'qr' => 'zatca'`, and so on. You pass quantities and prices; you never pass totals. Values you put under keys the template computes are replaced.

Each template page lists its defaults and computed values. In tests, `Doc::fake()` lets you read the prepared data, for example `$doc->data('totals.total')`; see [Testing your app](/guide/testing).

## Sample data {#sample-data}

Every template carries sample data in its `template.php`. The [preview page](/guide/preview) and `php artisan doc:sample` use it, and you can too: it is the quickest way to see a template, and it shows the exact shape of the data.

```php
$sample = Doc::templates()->get('payslip')->sample();

return Doc::template('payslip', $sample)->locale('ar')->pdf()->stream();
```

`dd(Doc::templates()->get('invoice')->sample())` prints a complete, valid invoice array to copy from.

## Company details and logo {#theme}

Templates take your company name, address and logo from the **theme**, not from the data, so you set them once. The theme comes from `theme` in `config/easy-pdf-word.php`, and `->theme()` overrides it for one document:

| Key | Used for | Default |
| --- | --- | --- |
| `primary` | Titles, table headers, the total row | `#0F766E` |
| `text` | Body text | `#1F2937` |
| `muted` | Labels and secondary text | `#6B7280` |
| `border` | Table and box borders | `#E5E7EB` |
| `logo` | The logo: a file path, an allowed URL or a data URI | none |
| `company.name` | The company name | `APP_NAME` |
| `company.address`, `company.phone`, `company.email`, `company.tax_number` | Contact and tax details | none |

```php
Doc::template('quotation', $data)
    ->theme([
        'primary' => '#B45309',
        'logo' => storage_path('app/tenants/14/logo.png'),
        'company' => [
            'name' => 'مؤسسة النور للتجارة',
            'address' => 'طريق الملك فهد، الرياض',
            'phone' => '+966 11 000 0000',
            'tax_number' => '300000000000003',
        ],
    ])
    ->locale('ar')
    ->pdf();
```

`->theme()` is merged into the config theme key by key: setting only `company.name` keeps the address and phone from the config. Colours must be real colours (`#B45309`, `rgb(180, 83, 9)`, `red`); anything else falls back to the default. Use hex colours if you also make Word files, since Word takes only hex.

How the templates use the theme:

- The quotation, purchase order, delivery note, receipt, payslip, report and letter print `company` as their letterhead.
- The invoice and credit note print the seller from `seller` in your data, and fill each missing seller detail from `company`. With the company in the config, you can leave `seller` out.
- The certificate's issuer defaults to the company name.
- The Egyptian e-invoice and the contract take their parties from the data (`issuer`, `parties`).
- Every template shows the `logo` when one is set.

To brand documents per customer or tenant, pass their theme with each document; see the [Branding per customer](/recipes/multi-tenant-branding) recipe. Logo files are read only from `public/`, `storage/app` and `resources/` by default, and URLs only from hosts you allow; see [Images](/guide/images).

## Labels and languages {#labels}

The words a template prints (titles, column headers, "Total due") come from its `lang` folder: `lang/ar.php` and `lang/en.php`. The locale picks the file by its language, so `ar`, `ar_EG` and `ar-SA` all use `lang/ar.php`. A label missing from that file is taken from `lang/en.php`.

Any other locale works too. `->locale('fr')` makes a left-to-right document with the English labels, and `->locale('ur')` a right-to-left one. To print French labels, copy the template (see below) and add `lang/fr.php`.

Your data is printed as you give it: the package translates the labels, not your item names.

## List the templates {#doc-templates}

```bash
php artisan doc:templates
```

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| my-invoice     | Tax invoice        | ar, en  | PDF, Word | project |
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

`Source` says whether a template comes from the package or from your app (here a copy named `my-invoice`). Project templates are listed first. In code, `Doc::templates()->all()` returns the same list.

## Copy a template to change it {#copy}

To change a bundled template, copy it into your app under a new name:

```bash
php artisan doc:template invoice --as=my-invoice
```

This copies the whole folder to `resources/doc-templates/my-invoice/` (the first folder in `templates.paths`): `template.php`, `pdf.blade.php`, `word.php`, `footer.blade.php` and `lang/`. Edit any of them, then use the new name:

```php
Doc::template('my-invoice', $data)->locale('ar')->pdf();
```

[Your own templates](/guide/custom-templates) explains every file in the folder.

## Override a bundled template {#override}

Copy a template under its own name and your copy replaces the original everywhere in the app: every `Doc::template('invoice')` call, the preview page and `doc:sample`.

```bash
php artisan doc:template invoice
```

For example, to change the Arabic title of every invoice, edit `resources/doc-templates/invoice/lang/ar.php` and change one line, keeping the others:

```php
'title' => 'فاتورة ضريبية مبسطة',
```

Running the command again stops with "already exists. Use --force to overwrite it." With `--force` it copies the package's original files over your copy again; files you added to the folder stay. To go back to the bundled template for good, delete the folder.

::: tip Keep copies small
A copied template no longer receives fixes from package updates. Copy only the templates you really change, and check the [changelog](https://github.com/BiztechEG/laravel-easy-pdf-word/blob/main/CHANGELOG.md) when you update the package.
:::

## The gallery {#gallery}

The [template gallery](/templates/) shows every template with an Arabic and an English preview. Each template's page lists all its fields, which are required, their defaults, the computed values, the labels and a complete example. To try them with your own settings, open the [preview page](/guide/preview) at `/doc-preview` in your local environment.
