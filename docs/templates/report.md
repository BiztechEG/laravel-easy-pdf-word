# Table report

A table report (تقرير جدولي) for any list of rows that someone in the business asks for as a file: sales by branch, orders, stock, attendance. The `report` template takes the columns you choose, adds a totals row and summary cards, and repeats the table header on every page.

<div class="preview">
  <figure><a href="/samples/report-ar.pdf" target="_blank"><img src="/previews/report-ar.png" alt="Arabic monthly sales report: title and period, three summary cards, a table of four branches with orders, revenue and growth, and a totals row"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/report-en.pdf" target="_blank"><img src="/previews/report-en.png" alt="The same report with English labels"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- Monthly sales by branch or by salesperson for the management meeting.
- Lists of orders, invoices or payments for a period, for the accountant.
- Stock levels by warehouse, or a price list by category.
- Attendance and leave reports for HR.
- An "Export" button on any admin table, giving the same rows as PDF or Word.

## Quick example {#example}

Pass the title, the columns and the rows:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$report = Doc::template('report', [
    'title' => 'تقرير المبيعات الشهري',
    'subtitle' => 'الفترة من 1 سبتمبر حتى 30 سبتمبر 2026',
    'columns' => [
        ['key' => 'branch', 'label' => 'الفرع'],
        ['key' => 'manager', 'label' => 'المدير'],
        ['key' => 'orders', 'label' => 'عدد الطلبات', 'format' => 'number', 'decimals' => 0],
        ['key' => 'revenue', 'label' => 'الإيرادات (ج.م)', 'format' => 'number'],
        ['key' => 'growth', 'label' => 'النمو %', 'format' => 'number', 'decimals' => 1],
    ],
    'rows' => [
        ['branch' => 'القاهرة - مدينة نصر', 'manager' => 'سارة إبراهيم', 'orders' => 1240, 'revenue' => 486500.75, 'growth' => 12.4],
        ['branch' => 'الجيزة - الدقي', 'manager' => 'محمد علي', 'orders' => 980, 'revenue' => 371200, 'growth' => 8.1],
        ['branch' => 'الإسكندرية - سموحة', 'manager' => 'Omar Khaled', 'orders' => 765, 'revenue' => 290340.5, 'growth' => -2.3],
        ['branch' => 'المنصورة', 'manager' => 'هبة سمير', 'orders' => 410, 'revenue' => 150875, 'growth' => 4.7],
    ],
    'sum' => ['orders', 'revenue'],
    'summary' => [
        'إجمالي الإيرادات' => '1,298,916.25 ج.م',
        'أعلى فرع' => 'مدينة نصر',
        'عدد الفروع' => '4',
    ],
    'generated_at' => '2026-10-08 10:30',
])->locale('ar');
```

Then return the file you need:

::: code-group

```php [PDF]
return $report->pdf()->download('sales-2026-09.pdf');
```

```php [Word]
return $report->word()->download('sales-2026-09.docx');
```

:::

You get one A4 page, the same as the preview above:

- the title and subtitle, and on the other side the company name and تاريخ الإنشاء: 2026/10/08 10:30;
- three summary cards with the labels and values from `summary`;
- the table, with a `#` column numbering the rows from 1, the number columns aligned to the end, and -2.3 printed with its minus sign in the right place;
- a totals row, الإجمالي, with 3,395 orders and 1,298,916.25 revenue;
- a footer with the report title and صفحة 1 من 1.

## Fields {#fields}

The data is validated before anything is drawn. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `title` | Yes | text | | The report title, at the top and in the footer. |
| `subtitle` | No | text | | A grey line under the title, such as the period. |
| `columns` | Yes | list or map, at least 1 | | The columns to show, in order. See [Columns](#columns). |
| `rows` | Yes, may be empty | list of arrays, models or objects | | The rows. An empty list prints لا توجد بيانات / No data. |
| `sum` | No | list of column keys | empty | The columns to total in the last row. |
| `summary` | No | map of label => value | empty | Summary cards above the table, in order. |
| `generated_at` | No | date and time | now | Printed at the top as تاريخ الإنشاء / Generated, as `2026/10/08 10:30`. |

Each column takes these options:

| Option | Default | What it does |
| --- | --- | --- |
| `key` | the array key | Where to read the value in each row. Dots reach into nested data: `customer.name`. |
| `label` | the key | The column heading. |
| `format` | text | `number` or `money`: thousands separators and `decimals` places. `date`: printed as `2026/09/03`. Anything else: the value as text. |
| `decimals` | `2` | Decimal places for `number` and `money` (capped at 10). |
| `align` | `end` for `number` and `money`, `start` for the rest | `start`, `end` or `center`. In Arabic, `start` is the right side. |

`money` formats like `number` and adds no currency sign, so put the currency in the label, as in الإيرادات (ج.م).

The template also calculates `totals` from `sum`; you do not pass it.

### Theme values {#theme}

| Theme key | Where it shows |
| --- | --- |
| `company.name` | Top corner, opposite the title. |
| `primary` | The title, the header row, the summary values and the line above the totals. |
| `muted` | The subtitle, the company name, the generated date and the summary labels. |
| `border` | The row lines and the summary card borders. |

The report has no logo or letterhead: it is meant to be read as data. Copy the template (see [Customise it](#customise)) if you need one.

## Variants and options {#options}

### Columns {#columns}

Write each column as a full array, as in the example, or use the short form `key => label` for plain text columns. The two forms can be mixed, and a full array without `key` uses its array key:

```php
'columns' => [
    'number' => 'رقم الطلب',
    'customer.name' => 'العميل',
    'created_at' => ['label' => 'التاريخ', 'format' => 'date'],
    'total' => ['label' => 'الإجمالي (ج.م)', 'format' => 'money'],
],
```

### Rows from your database {#rows}

`rows` takes arrays, Eloquent models, collections and query builder results. Models are turned into arrays with their loaded relations, so a dotted key such as `customer.name` reads a relation:

```php
use App\Models\Order;
use BiztechEG\EasyPdfWord\Facades\Doc;

$report = Doc::template('report', [
    'title' => 'طلبات شهر سبتمبر',
    'columns' => [
        'number' => 'رقم الطلب',
        'customer.name' => 'العميل',
        'created_at' => ['label' => 'التاريخ', 'format' => 'date'],
        'total' => ['label' => 'الإجمالي (ج.م)', 'format' => 'money'],
    ],
    'rows' => Order::with('customer')->orderBy('created_at')->get(),
    'sum' => ['total'],
])->locale('ar');
```

Give date columns `'format' => 'date'`: a model turns its dates into long strings such as `2026-09-03T10:00:00.000000Z`, and the `date` format prints them as `2026/09/03`. In text columns, `true` prints as ✓ and an array as JSON.

### Totals row {#totals}

`sum` lists the column keys to total. Each total uses its column's format, and the word الإجمالي / Total goes in the first column, unless the first column is itself summed. Values written as text are read the way they look: `"1,240"` counts as 1240, and Arabic digits are read too.

### Summary cards {#summary}

`summary` is a map of label => value. Each pair becomes a card above the table, side by side in the order given. The values are printed as you pass them, so format them first: `'1,298,916.25 ج.م'`.

### Landscape pages {#landscape}

Wide tables fit better on a landscape page:

```php
return $report->landscape()->pdf()->download('sales-2026-09.pdf');
```

The Word file follows the same setting. See [Page settings](/guide/page-settings) for paper sizes and margins.

### Header on every page {#repeated-header}

When the table runs past one page, its header row is repeated at the top of each page, and the footer shows the title with the page number, such as صفحة 2 من 5. The rows stay numbered across pages and striped for easier reading.

### Large reports {#large}

mPDF keeps the whole table in memory while it lays it out, so a report of more than a few hundred rows needs a higher `memory_limit` for the request or job that renders it, or the Chromium engine, which needs far less memory. See [PDF engines](/guide/engines).

## Word file {#word}

The report has a `pdf.blade.php` for the PDF and a separate `word.php` for Word, built from the same data. The Word file has the same title block, summary cards, numbered and striped rows and totals row, and its header row is marked to repeat on every page in Word too. Numbers are formatted the same way, and the page orientation follows `->landscape()`. The footer becomes a line of small grey text with Word page numbers.

Readers who want to add a comment or a chart can do it in the Word file. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template report --as=my-report
```

This copies the template to `resources/doc-templates/my-report/`:

- `template.php`: the fields, the defaults and the page settings; add `'orientation' => 'landscape'` to make every report landscape;
- `pdf.blade.php`: the PDF layout and its CSS;
- `word.php`: the Word layout;
- `footer.blade.php`: the footer with the title and page numbers;
- `lang/ar.php` and `lang/en.php`: the labels الإجمالي، لا توجد بيانات and تاريخ الإنشاء.

Edit `pdf.blade.php` and `word.php` together, so both files stay alike. Use the copy with `Doc::template('my-report', $data)`. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Sales report from a query](/recipes/sales-report): a full report from a database query, with a controller.
- [A price list built in code](/recipes/price-list-builder): when you need more than one table on the page.
- [Building in code](/guide/builder): build a document block by block with `Doc::make()`.
- [Page settings](/guide/page-settings): landscape pages, paper sizes and margins.
- [PDF engines](/guide/engines): Chromium for very long reports.
