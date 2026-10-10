# Word files

Every bundled template, every builder document and any `.docx` you design in Word can be made into a Word file, with real right-to-left paragraphs and tables for Arabic. This page covers the three ways to make one, what the package does for Arabic, the Word font, the placeholders of a Word-designed template, images, and what Word files cannot do.

Word files need PhpWord: `composer require phpoffice/phpword` (version 1.4 or newer), which also needs the PHP `zip` extension. See [Installation](/guide/installation#word).

## Three ways to a Word file {#routes}

| You start from | Call | Layout comes from |
| --- | --- | --- |
| A template | `Doc::template('invoice', $data)->word()` | The template's `word.docx`, `word.php` or `layout.php` |
| Code | `Doc::make()->heading(...)->word()` | The blocks you add |
| A `.docx` designed in Word | `Doc::template('my-quote', $data)->word()` | `word.docx` in the template folder, with `${placeholders}` |

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

// 1. A bundled template: the same data as the PDF
Doc::template('invoice', $data)->locale('ar')->word()->download('فاتورة-1024.docx');

// 2. A document built in code
Doc::make()
    ->heading('تقرير المبيعات')
    ->table([['الفرع', 'المبيعات'], ['القاهرة', '1,000']], ['header' => true])
    ->locale('ar')
    ->word()
    ->download('تقرير.docx');

// 3. A template whose folder has a word.docx designed in Word
Doc::template('quote-word', $data)->locale('ar')->word()->download('عرض-سعر.docx');
```

All twelve bundled templates make Word files. For your own templates, see [which file makes which format](/guide/custom-templates#precedence). Blade views and HTML strings make PDFs only.

`->word()` returns a file with the same methods as a PDF: `download()`, `stream()`, `save()` and `content()`, and it can be attached to mail or put in a ZIP with `Doc::zip()`. The name you give it gets `.docx` added when it is missing. See [Output and delivery](/guide/output).

## What the package does for Arabic {#arabic}

Word shapes Arabic letters itself; what matters is that the file tells Word the direction and the language. For a right-to-left document (`->locale('ar')`), the package:

- marks every paragraph right to left, so text starts on the right and punctuation lands in the right place;
- marks Arabic text as Arabic, with the language of your locale (`ar-SA` for `ar`, `ar-EG` for `ar_EG`), so Word uses its Arabic font settings, size and bold, and spell-checks it as Arabic;
- lays tables out from the right: the first column is on the right;
- repeats a table's header row at the top of every page, and keeps each row on one page;
- keeps a heading on the same page as the paragraph after it;
- turns `{page}` and `{pages}` in the header and footer into Word page-number fields;
- keeps `ltr` values (phone numbers, invoice numbers, e-mails) and negative numbers in their order, with Unicode direction marks;
- writes Arabic digits with `->numerals('arabic')`, keeping `,` and `.` as separators;
- applies the paper size, orientation and margins, and stores the title and the theme's company name as the file's title and author.

English and other left-to-right documents stay left to right.

## The Word font {#word-font}

Word files do not carry their fonts: Word shows the text in a font installed on the reader's computer. So Word files use a common font with Arabic letters, Arial by default, instead of Cairo. `->font()` changes the PDF only.

Change it for the whole app in `.env`:

```dotenv
DOC_WORD_FONT=Tahoma
```

or in `config/easy-pdf-word.php`, with the size in points:

```php
'word' => [
    'font' => env('DOC_WORD_FONT', 'Arial'),
    'font_size' => 11,
],
```

Fonts with Arabic letters that most Windows computers have include Arial, Tahoma, Times New Roman, Simplified Arabic and Sakkal Majalla. If your readers might not have a font, keep Arial.

## A file designed in Word: word.docx {#word-docx}

When the layout should be designed by someone in Word, put a `word.docx` in a template folder. You design the page in Word, write placeholders such as `${customer.name}` where values go, and the package fills them in.

1. Create a template folder with a `template.php` for the fields (see [Your own templates](/guide/custom-templates)).
2. Design the document in Word. For Arabic, set the paragraphs and tables to right to left in Word itself: the package fills values but keeps your layout as it is.
3. Type the placeholders, then save the file as `word.docx` in the folder.

For example, a quotation designed in Word, with a table whose second row repeats for every item:

```text
${theme.logo:160:48}
${t.title}
${t.number}: ${quote.number}    ${t.date}: ${date}    ${doc.hijri_date}
${t.customer}: ${customer.name}

┌─────────────────────┬──────────────────────┬───────────────────┬─────────────────────┬────────────────┐
│ #                   │ ${t.description}     │ ${t.quantity}     │ ${t.unit_price}     │ ${t.line_total} │
├─────────────────────┼──────────────────────┼───────────────────┼─────────────────────┼────────────────┤
│ ${items.row_number} │ ${items.description} │ ${items.quantity} │ ${items.unit_price} │ ${items.total} │
└─────────────────────┴──────────────────────┴───────────────────┴─────────────────────┴────────────────┘

${t.total}: ${totals.total} ${t.currency}
${t.terms}: ${terms}
${theme.company.name}
${doc.qr:100:100}
```

```php
<?php

// resources/doc-templates/quote-word/template.php

return [
    'title' => 'Quotation (Word design)',
    'locales' => ['ar'],

    'fields' => [
        'quote.number' => ['required', 'string'],
        'date' => ['required', 'date'],
        'customer.name' => ['required', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric'],
        'items.*.unit_price' => ['required', 'numeric'],
        'terms' => ['nullable', 'array'],
    ],

    // Amounts as floats, so word.docx prints them as 46,500.00.
    'prepare' => function (array $data): array {
        foreach ($data['items'] as $i => $item) {
            $data['items'][$i]['unit_price'] = (float) $item['unit_price'];
            $data['items'][$i]['total'] = round($item['quantity'] * $item['unit_price'], 2);
        }

        $data['totals']['total'] = (float) array_sum(array_column($data['items'], 'total'));

        return $data;
    },
];
```

```php
Doc::template('quote-word', [
    'quote' => ['number' => 'QT-2026-0091'],
    'date' => now(),
    'customer' => ['name' => 'مؤسسة النور للتجارة'],
    'items' => [
        ['description' => 'تطوير النظام (Laravel)', 'quantity' => 1, 'unit_price' => 42000],
        ['description' => 'تدريب المستخدمين', 'quantity' => 3, 'unit_price' => 1500],
    ],
    'terms' => ['الأسعار شاملة ضريبة القيمة المضافة', 'يسري العرض 30 يوماً'],
    'qr' => 'https://biztech.example/quotes/QT-2026-0091',
])->locale('ar')->word()->download('عرض-سعر-QT-2026-0091.docx');
```

The labels (`${t.title}` and the rest) come from the template's `lang/ar.php`, the logo and company from the theme, and the total (46,500.00) from `prepare`. The [A template designed in Word](/recipes/word-designed-template) recipe walks through a complete one.

### Placeholders {#placeholders}

| Placeholder | Is replaced with |
| --- | --- |
| `${customer.name}`, `${invoice.number}` | A value from the data, after the template's defaults and `prepare`. Dots reach into nested arrays. |
| `${items.description}` in a table row | That row is repeated for every item of the list `items`, and filled from each item. Any top-level list of arrays works this way. |
| `${items.row_number}` | 1, 2, 3 ... in a repeated row |
| `${terms}` | A list of plain values, joined with `، ` |
| `${t.title}` | A label from the template's `lang/{language}.php`, falling back to English |
| `${theme.company.name}`, `${theme.company.phone}` | A theme value |
| `${theme.logo}` | The theme's logo, as an image |
| `${logo}`, `${signature}`, any name | An image, when the value is an image file path (`.png`, `.jpg`, `.jpeg`, `.gif`, `.bmp`, `.webp`) or a data URI |
| `${logo:120:60}` | The same image, with a width and height in pixels. Without a size, images are 120 pixels wide; the proportions are kept. |
| `${doc.hijri_date}` | The Hijri date of `date`, or of `invoice.date` (needs the `intl` extension) |
| `${doc.qr}` | A QR code image of the `qr` value |
| `${doc.today}` | Today's date, as `2026/10/08` |

How values are written:

- Text is escaped, and a `${` inside a value stays text, so data cannot add placeholders.
- Decimal numbers (floats) get thousands separators and the decimals of the document's currency, read from a top-level `currency` or from a group's `currency` (`invoice.currency`, `quote.currency`, `document.currency` ...): three for KWD, two for most. Whole numbers and strings are printed as they are, so pass amounts as floats (`42000.0`) or format them yourself.
- Dates passed as date objects (`now()`, a Carbon date) print as `2026/10/08`. `true` prints ✓, and `false` and `null` print nothing.
- Digits follow `->numerals()`.
- A placeholder with no value in the data is removed. So is an image placeholder whose image cannot be used (an SVG, a missing file, or a file outside the allowed folders): it is left empty, and the path is never printed.
- In right-to-left documents, a value without Arabic letters, such as a phone number, a date or a code (`+20 100 000 0000`, `2026-10-08`, `INV-2026-1024`), is marked as left to right, so Word keeps its parts in order. Values made only of digits are left as they are.

A `word.docx` makes the Word file only. For the PDF, add a `pdf.html.php` or `layout.php` to the same folder; without one, `->pdf()` fails with "Template [quote-word] has no pdf.html.php or pdf.blade.php."

## Images in Word {#images}

Images in Word files follow the same rules as in PDFs: local files only from the allowed folders (`public/`, `storage/app` and `resources/` by default), and URLs only from the hosts you allow in `DOC_REMOTE_IMAGES`, fetched without following redirects and with a 10-second limit. See [Images](/guide/images).

Word itself shows JPEG, PNG and GIF. The package turns WebP and BMP images into PNG for it. SVG images are left out of Word files, because PHP cannot draw them, while PDFs show them. If a logo is an SVG, keep a PNG copy for documents you make as Word files.

In the builder, image widths are in millimetres; in a `word.docx`, sizes are in pixels.

## What Word files cannot do {#limits}

| Feature | In a Word file |
| --- | --- |
| `->watermark()` | Left out: the Word file is made without it. |
| `->password()` | Refused: `->word()` throws `LogicException` with "Word files cannot take a password; ->password() works for PDF files only." `->queue('….docx')` refuses it too. A file you meant to protect never goes out open. |
| Blade views and HTML | Not possible: `->word()` throws `WordNotSupported`. Use a template or the builder. |
| A template with only `pdf.html.php` (or `pdf.blade.php`) | Not possible: `WordNotSupported` asks you to add `layout.php`, `word.php` or `word.docx`. |
| Fonts | Not embedded: the [Word font](#word-font) must be on the reader's computer. `->font()` is ignored. |
| Header and footer | One centred line of small text, with page numbers. HTML styles, tables and images in them are left out. |
| CSS | `pdf.html.php` and its CSS are for the PDF only. The Word file comes from its own layout. |

To protect a document, send the PDF with a password and the Word file only to people who may edit it.
