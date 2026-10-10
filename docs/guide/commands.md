# Artisan commands

The package adds four Artisan commands: list the templates, copy one to customise it, start a new one, and render a template's sample data to a file.

| Command | What it does |
| --- | --- |
| [`doc:templates`](#doc-templates) | Lists the templates your app can use |
| [`doc:template`](#doc-template) | Copies a template into your app to customise it |
| [`doc:make-template`](#doc-make-template) | Creates a new template from a blank starter |
| [`doc:sample`](#doc-sample) | Renders a template with its sample data to a PDF, Word or HTML file |

## doc:templates {#doc-templates}

```bash
php artisan doc:templates
```

It takes no arguments or options, and lists every template: the bundled ones and those in your project's templates folder (`resources/doc-templates` by default).

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| my-invoice     | Tax invoice        | ar, en  | PDF, Word | project |
| packing-list   | packing-list       | ar, en  | PDF, Word | project |
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

| Column | Meaning |
| --- | --- |
| Name | What you pass to `Doc::template()` |
| Title | `title` from the template's `template.php` |
| Locales | `locales` from `template.php` (`ar, en` when it has none) |
| Formats | `PDF` when the template has `pdf.html.php`, `pdf.blade.php`, `layout.php` or `word.php`; `Word` when it has `layout.php`, `word.php` or `word.docx` |
| Source | `project` for a template in your app, `package` for a bundled one |

Your templates are listed first. A project template with the name of a bundled one replaces it, and the bundled one is no longer listed.

## doc:template {#doc-template}

Copies a template into your project's templates folder, where you can change it.

```bash
php artisan doc:template {name} {--as=} {--force}
```

| Argument or option | Meaning |
| --- | --- |
| `name` | The template to copy, for example `invoice`. |
| `--as=` | A new name for the copy. Without it, the copy keeps the same name and replaces the original in your app. |
| `--force` | Overwrite an existing copy. With the name of a bundled template, it restores the bundled files over your copy. |

```bash
# A new template next to the original
php artisan doc:template invoice --as=my-invoice

# Same name: your copy replaces the bundled letter everywhere in the app
php artisan doc:template letter

# Start your copy of the letter again from the bundled one
php artisan doc:template letter --force
```

```text
   INFO  Template copied to resources/doc-templates/my-invoice.
```

Errors you may see:

```text
   ERROR  resources/doc-templates/my-invoice already exists. Use --force to overwrite it.
   ERROR  Use letters, digits, dots, dashes or underscores for the template name.
   ERROR  resources/doc-templates/packing-list is the template itself. Use --as to copy it under another name.
   ERROR  Template [invoce] was not found. Run php artisan doc:templates to list them.
```

The third one comes from `--force` on a template that exists only in your project. The last one is an unknown template name; the command then copies nothing and exits with a failure code. The copy goes to the first folder in `templates.paths` in the config. What to change in a copied template is covered in [Your own templates](/guide/custom-templates).

## doc:make-template {#doc-make-template}

Creates a new template folder from a blank starter: `template.php`, `pdf.html.php`, `word.php`, `footer.html.php` and `lang/ar.php` and `lang/en.php`. The PDF page and the footer are [plain PHP](/guide/custom-templates#pdf-html), like the bundled templates.

```bash
php artisan doc:make-template {name} [--blade]
```

| Argument or option | Meaning |
| --- | --- |
| `name` | The folder name, which is also the template name, for example `packing-list`. Letters, digits, dots, dashes and underscores. |
| `--blade` | Writes the PDF page and the footer in Blade instead: `pdf.blade.php` and `footer.blade.php`. They work only in Laravel. |

```bash
php artisan doc:make-template packing-list
```

```text
   INFO  Template created in resources/doc-templates/packing-list.
```

The new template renders straight away, in PDF and Word:

```php
Doc::template('packing-list', ['title' => 'قائمة التعبئة'])->locale('ar')->pdf();
```

With the name of a bundled template, the command warns you that the new template replaces it:

```text
   WARN  This replaces the bundled [receipt] template in your app. To start from it instead, run php artisan doc:template receipt.

   INFO  Template created in resources/doc-templates/receipt.
```

A folder that already exists is never overwritten: `ERROR  resources/doc-templates/packing-list already exists.`

## doc:sample {#doc-sample}

Renders a template with the sample data from its `template.php` and writes the file, so you can see a template without writing code.

```bash
php artisan doc:sample {name} {--locale=ar} {--format=} {--numerals=latin} {--driver=} {--output=}
```

| Argument or option | Default | Meaning |
| --- | --- | --- |
| `name` | | The template, for example `invoice`. |
| `--locale=` | `ar` | The document's language. |
| `--format=` | from `--output`, else `pdf` | `pdf`, `docx` or `html`. Without it, the extension of `--output` decides when it is one of these. |
| `--numerals=` | `latin` | `latin` (123) or `arabic` (١٢٣). |
| `--driver=` | the default engine | The PDF engine: `mpdf`, `chromium` or `gotenberg`. |
| `--output=` | `storage/app/doc-samples/{name}-{locale}.{format}` | The file to write. A relative path is relative to the folder you run the command from. Missing folders are created. |

```bash
# storage/app/doc-samples/invoice-ar.pdf
php artisan doc:sample invoice

# English, Arabic digits, with Chromium
php artisan doc:sample invoice --locale=en --numerals=arabic --driver=chromium

# A Word file: the extension picks the format
php artisan doc:sample eg-invoice --output=eg-invoice.docx

# The HTML the PDF engine gets, to debug a layout
php artisan doc:sample report --format=html --output=report.html
```

```text
   INFO  Saved storage/app/doc-samples/invoice-ar.pdf.
```

Errors you may see:

```text
   ERROR  Template [invoce] was not found. Run php artisan doc:templates to list them.
   ERROR  Use --format=pdf, docx or html.
   ERROR  Invalid locale [../x], expected a name such as "ar", "en" or "ar_EG".
   ERROR  Unknown numerals style [roman]. Use "latin" or "arabic".
```

An `--output` with another extension, such as `report.xls`, is written as a PDF unless `--format` says otherwise. The engine's fallback applies here too, so check the log when you ask for `--driver=chromium` and want to be sure Chromium made the file.

## Publish the config {#publish-config}

Not one of the package's own commands, but the one you need to change its settings:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

It copies `config/easy-pdf-word.php` into your app. Every key is described in [Configuration](/guide/configuration).
