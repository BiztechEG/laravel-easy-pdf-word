# Building in code

`Doc::make()` describes a document block by block: headings, paragraphs, tables, images and QR codes. The same description makes a PDF and a Word file, so it suits documents assembled from your data, such as statements, reports and price lists. This page lists every block, option and style.

## A first document {#first}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$report = Doc::make()
    ->heading('تقرير المبيعات')
    ->paragraph([['text' => 'الفترة: ', 'bold' => true], 'سبتمبر 2026'])
    ->table([
        ['الفرع', 'الطلبات', 'الإيرادات'],
        ['القاهرة', '1,240', '486,500.75'],
        ['الجيزة', '980', '371,200.00'],
    ], ['header' => true, 'columns' => [50, 20, ['width' => 30, 'align' => 'end']]])
    ->locale('ar');

$report->pdf()->download('تقرير-المبيعات.pdf');
$report->word()->download('تقرير-المبيعات.docx');
```

Blocks are drawn in the order you add them. Settings such as `->locale()` can go anywhere in the chain. Every value is escaped, so data from users is safe to print.

## A complete example {#example}

A customer account statement (كشف حساب عميل) with a logo, a header block, a table of transactions with an opening balance and a totals row, a summary box with the amount in words and a QR code, a note and a footer with page numbers:

<div class="preview">
  <figure><img src="/images/guide-a/builder-statement-ar.png" alt="An Arabic customer account statement built with Doc::make()"><figcaption>The PDF made by this code</figcaption></figure>
</div>

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;
use BiztechEG\EasyPdfWord\Facades\Doc;

$transactions = [
    ['date' => '2026/09/03', 'description' => 'فاتورة مبيعات', 'reference' => 'INV-2026-0981', 'debit' => 8550.00, 'credit' => 0],
    ['date' => '2026/09/10', 'description' => 'تحصيل نقدي', 'reference' => 'RCT-2026-0412', 'debit' => 0, 'credit' => 10000.00],
    ['date' => '2026/09/18', 'description' => 'فاتورة مبيعات', 'reference' => 'INV-2026-1003', 'debit' => 4275.50, 'credit' => 0],
    ['date' => '2026/09/25', 'description' => 'إشعار دائن', 'reference' => 'CN-2026-0033', 'debit' => 0, 'credit' => 1500.00],
    ['date' => '2026/09/28', 'description' => 'تحويل بنكي', 'reference' => 'TRF-77120', 'debit' => 0, 'credit' => 6000.00],
];

$amount = fn (float $value) => $value > 0 ? number_format($value, 2) : '-';
$balance = 12500.00;

// The header row, then the opening balance across five columns.
$rows = [
    ['التاريخ', 'البيان', 'المرجع', 'مدين', 'دائن', 'الرصيد'],
    [['text' => 'رصيد أول المدة', 'colspan' => 5, 'italic' => true], number_format($balance, 2)],
];

foreach ($transactions as $row) {
    $balance += $row['debit'] - $row['credit'];

    $rows[] = [
        $row['date'],
        $row['description'],
        ['text' => $row['reference'], 'ltr' => true],
        $amount($row['debit']),
        $amount($row['credit']),
        number_format($balance, 2),
    ];
}

// The last row: totals, printed bold with 'footer' => true.
$rows[] = [
    ['text' => 'الإجمالي', 'colspan' => 3],
    number_format(array_sum(array_column($transactions, 'debit')), 2),
    number_format(array_sum(array_column($transactions, 'credit')), 2),
    number_format($balance, 2),
];

$statement = Doc::make()
    ->image(public_path('images/logo.png'), 35)
    ->heading('كشف حساب عميل')
    ->paragraph([['text' => 'العميل: ', 'bold' => true], 'مؤسسة النور للتجارة'], ['space_after' => 1])
    ->paragraph([['text' => 'رقم الحساب: ', 'bold' => true], ['text' => 'ACC-10457', 'ltr' => true]], ['space_after' => 1])
    ->paragraph([['text' => 'الفترة: ', 'bold' => true], 'من 2026/09/01 إلى 2026/09/30'])
    ->line()
    ->table($rows, [
        'header' => true,
        'footer' => true,
        'striped' => '#F9FAFB',
        'font_size' => 9.5,
        'columns' => [
            14,
            22,
            18,
            ['width' => 15, 'align' => 'end'],
            ['width' => 15, 'align' => 'end'],
            ['width' => 16, 'align' => 'end'],
        ],
    ])
    ->spacer(4)
    ->table([[
        [
            'lines' => [
                ['text' => 'الرصيد المستحق', 'color' => '#6B7280'],
                ['text' => number_format($balance, 2).' ج.م', 'bold' => true, 'size' => 16, 'color' => '#0F766E'],
                ['text' => Arabic::tafqeet($balance, 'EGP', only: true), 'size' => 9],
            ],
            'border' => '#0F766E',
        ],
        ['qr' => 'https://biztech.example/statements/ACC-10457/2026-09', 'width' => 26, 'align' => 'center'],
    ]], ['columns' => [72, 28], 'borders' => false])
    ->paragraph(
        'يُرجى مراجعة هذا الكشف وإبلاغنا بأي ملاحظات خلال 15 يوماً من تاريخه، وإلا اعتُبر الرصيد صحيحاً.',
        ['size' => 9, 'color' => '#6B7280', 'align' => 'justify', 'line_height' => 1.5],
    )
    ->locale('ar')
    ->footer('<div style="text-align: center; font-size: 8pt; color: #6B7280;">كشف حساب <bdo dir="ltr">ACC-10457</bdo> - صفحة {page} من {pages}</div>');

$statement->pdf()->download('كشف-حساب-ACC-10457.pdf');
$statement->word()->download('كشف-حساب-ACC-10457.docx');
```

The Word file has the same content: right-to-left paragraphs, the table laid out from the right with its header repeated on every page, the merged cells, the boxed summary, the QR image and Word page numbers in the footer.

## Blocks {#blocks}

| Block | Defaults | What it adds |
| --- | --- | --- |
| `heading($text, $level = 1, $style = [])` | Level 1 | A heading. Levels 1, 2 and 3 are 18, 14 and 12 pt, bold; level 1 takes the theme's `primary` colour. A heading stays on the same page as the block after it. |
| `paragraph($text, $style = [])` | | A paragraph: a string, or a list of runs (see below). |
| `table($rows, $options = [])` | | A table: a list of rows, each a list of cells. See [Tables](#tables). |
| `image($source, $widthMm = 40, $align = 'start')` | 40 mm, start | An image from a file path, an allowed URL or a data URI. |
| `qr($value, $sizeMm = 30, $align = 'start')` | 30 mm, start | A QR code of any text or link. |
| `spacer($heightMm = 5)` | 5 mm | Empty vertical space. |
| `line($color = null)` | Theme `border` | A thin horizontal rule. |
| `pageBreak()` | | Starts a new page. |

`$align` is `start`, `end` or `center`. `start` is the right side in Arabic and the left side in English, so the same code works in both directions.

```php
Doc::make()
    ->heading('عقد تقديم خدمات')
    ->heading('البند الأول: التمهيد', 2)
    ->paragraph('يلتزم الطرف الأول بتنفيذ الأعمال الموضحة في عرض السعر المرفق.', ['align' => 'justify', 'line_height' => 1.5, 'space_after' => 3])
    ->image(public_path('images/logo.png'), 30, 'center')
    ->qr('https://biztech.example/verify/CT-2026-0031', 25, 'end')
    ->spacer(10)
    ->line('#0F766E')
    ->pageBreak()
    ->paragraph('الصفحة الثانية')
    ->locale('ar');
```

Images follow the same rules as everywhere in the package: local files only from the allowed folders, URLs only from allowed hosts, and SVG images are left out of Word files. See [Images](/guide/images).

## Paragraphs and runs {#runs}

A paragraph is a string, or a list of **runs**: pieces of text with their own style. A run is a string or an array with `text` and style keys:

```php
->paragraph([
    ['text' => 'المبلغ: ', 'bold' => true],
    ['text' => '71,250.00', 'color' => '#0F766E'],
    ' جنيه مصري، ',
    ['text' => 'شامل الضريبة', 'italic' => true, 'size' => 9],
])
```

The second argument styles the whole paragraph: `->paragraph('نص', ['align' => 'justify', 'size' => 11])`. A run's own style wins over the paragraph's. A line break in the text (`"السطر الأول\nالسطر الثاني"`) starts a new line in both formats.

## Styles {#styles}

| Key | Used on | What it does |
| --- | --- | --- |
| `bold` | Headings, paragraphs, runs, cells | `true` for bold |
| `italic` | Headings, paragraphs, runs, cells | `true` for italic, when the font has an italic |
| `size` | Headings, paragraphs, runs, cells | Font size in points |
| `color` | Headings, paragraphs, runs, cells | Text colour, as hex: `#0F766E` |
| `align` | Headings, paragraphs, cells, cell lines | `start`, `end`, `center` or `justify` |
| `ltr` | Headings, paragraphs, runs, cells | `true` keeps a phone number, code or e-mail in left-to-right order inside Arabic text |
| `space_after` | Paragraphs | Space below the paragraph, in millimetres |
| `line_height` | Paragraphs | Line spacing: `1.5` is one and a half lines |
| `background` | Cells | Background colour |
| `border` | Cells | A colour; draws a box around the cell |
| `colspan` | Cells | The number of columns the cell spans |

Colours can be hex (`#0F766E`, or `#0F766E80` with the alpha dropped in Word), `rgb()` or `hsl()`, in the PDF and the Word file alike. Colour names such as `teal` work in the PDF only; Word leaves them out. Invalid colours are ignored.

You rarely need `ltr` for numbers: a negative number such as `-2.5` keeps its minus sign in front in Arabic text by itself, and ranges like `2020 - 2021` stay as written. Use it for values mixing letters, digits and symbols, such as `INV-2026-1024` or `+20 100 000 0000`.

## Tables {#tables}

`table($rows, $options)` takes a list of rows. The options:

| Option | Default | What it does |
| --- | --- | --- |
| `header` | `false` | The first row is a header: bold, coloured, and repeated at the top of every page. |
| `header_background` | Theme `primary` | Background of the header row |
| `header_color` | `#FFFFFF` | Text colour of the header row |
| `borders` | `true` | A line under every row. `false` for a layout table without lines. |
| `border_color` | Theme `border` | Colour of those lines |
| `striped` | none | A background colour for every other row, such as `#F9FAFB` |
| `footer` | `false` | The last row is bold, for totals |
| `font_size` | The document size | Font size of the whole table, in points |
| `columns` | none | One entry per column: a width in percent (`30`), or `['width' => 30, 'align' => 'end']` |

```php
->table([
    ['الصنف', 'الكمية', 'السعر'],
    ['ورق تصوير A4', '40', '950.00'],
    ['حبر طابعة', '10', '3,200.00'],
    ['كرسي مكتب', '6', '4,750.00'],
    [['text' => 'الإجمالي', 'colspan' => 2], '98,500.00'],
], [
    'header' => true,
    'header_background' => '#1F2937',
    'header_color' => '#FACC15',
    'border_color' => '#D1D5DB',
    'striped' => '#F3F4F6',
    'footer' => true,
    'font_size' => 10,
    'columns' => [50, ['width' => 20, 'align' => 'center'], ['width' => 30, 'align' => 'end']],
])
```

A column's `align` applies to every cell in it, the header too. Columns without a width share the space that is left: equally in Word, by content in the PDF.

A table without borders is also the way to place things side by side, such as a company block next to the document details, as in the [complete example](#example).

### Cells {#cells}

A cell is a string, or an array:

| Cell | What it shows |
| --- | --- |
| `'1,250.00'` | Text |
| `['text' => '1,250.00', 'bold' => true, 'align' => 'end']` | Styled text, with any [style](#styles) key |
| `['text' => 'الإجمالي', 'colspan' => 2]` | A cell across two columns |
| `['text' => 'مدفوع', 'background' => '#16A34A', 'color' => '#FFFFFF']` | A coloured cell |
| `['text' => 'ملاحظة', 'border' => '#DC2626']` | A cell with a box around it |
| `['lines' => [...]]` | Several lines in one cell (below) |
| `['image' => $path, 'width' => 30]` | An image, width in millimetres (30 by default) |
| `['qr' => $value, 'width' => 20]` | A QR code, width in millimetres (30 by default) |

Each entry of `lines` is a line in the cell. A line is a string, a styled run, a list of runs, or an image:

```php
['lines' => [
    ['text' => 'شركة بيزتك', 'bold' => true],
    'الدقي، الجيزة',
    ['الرقم الضريبي: ', ['text' => '123-456-789', 'ltr' => true]],
    ['image' => public_path('images/stamp.png'), 'width' => 25],
]]
```

## Language and settings {#settings}

A builder document takes every document setting, in any place in the chain:

```php
$priceList = Doc::make()
    ->heading('Price list')
    ->locale('en')                         // or 'ar' for right to left
    ->numerals('arabic')                   // ١٢٣ in the text
    ->theme(['primary' => '#B45309'])      // heading and table header colour
    ->font('tajawal')                      // PDF font
    ->paper('A5')->landscape()->margins(12)
    ->title('Price list 2026')             // stored in the PDF and the Word file
    ->footer('<p>Page {page} of {pages}</p>');
```

Paper, orientation, margins, header, footer, title, locale, digits and theme colours apply to both formats. The font applies to the PDF; Word files use the Word font, see [Word files](/guide/word#word-font). `->watermark()` and `->password()` are for PDFs only. See [Page settings](/guide/page-settings) and [Arabic support](/guide/arabic).

## Adding blocks in a loop {#loops}

Each block method returns the document, so you can keep adding to it in loops and conditions:

```php
$report = Doc::make()->locale('ar')->heading('مبيعات الفروع');

foreach ($branches as $branch) {
    $report->heading($branch['name'], 2)
        ->table([['الشهر', 'المبيعات'], ...$branch['rows']], ['header' => true]);
}

return $report->pdf()->download('مبيعات-الفروع.pdf');
```

`->pdf()` and `->word()` take a copy of the document as it is at that moment: blocks added afterwards do not change a file you already asked for.

## Output {#output}

| Call | Gives |
| --- | --- |
| `->pdf()` | A PDF: `download()`, `stream()`, `save()`, `content()` |
| `->word()` | A Word file with the same methods (needs `phpoffice/phpword`) |
| `->queue('reports/sales.docx', disk: 's3')` | Renders and saves on a queue worker; the extension picks the format |
| `->toHtml()` | The HTML given to the PDF engine, for debugging |

See [Output and delivery](/guide/output).

## In a template {#in-templates}

A template's `layout.php` receives the same builder, so everything on this page works there too, with the template's labels and validated data. That is how most bundled templates are built. See [Your own templates](/guide/custom-templates#layout-php).
