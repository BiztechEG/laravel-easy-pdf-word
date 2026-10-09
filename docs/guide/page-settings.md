# Page settings

Set the paper size, orientation, margins, header and footer of a document, print a watermark across its pages, or lock the PDF with a password.

## Paper size and orientation {#paper}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('report', $data)->paper('A3')->landscape()->pdf();

Doc::template('receipt', $data)->paper('A5', 'landscape')->pdf();

Doc::template('report', $data)->paper('A4-L')->pdf();   // A4 landscape, as mPDF writes it
```

| Name | Size (mm) |
| --- | --- |
| `A2` | 420 × 594 |
| `A3` | 297 × 420 |
| `A4` | 210 × 297 |
| `A5` | 148 × 210 |
| `A6` | 105 × 148 |
| `B4` | 250 × 353 |
| `B5` | 176 × 250 |
| `Letter` | 215.9 × 279.4 |
| `Legal` | 215.9 × 355.6 |
| `Tabloid` | 279.4 × 431.8 |
| `Executive` | 184.15 × 266.7 |

- Names are not case sensitive: `a4`, `letter`.
- `->landscape()` and `->portrait()` set the orientation. `->paper('A5', 'landscape')` does both in one call.
- A name ending in `-L` or `-P` (`A4-L`, `Letter-P`) carries the orientation too, in `->paper()`, in `template.php` and in the config.
- An unknown name throws at once: `Unknown paper size [Foolscap]. Use one of A2, A3, A4, A5, A6, B4, B5, LETTER, LEGAL, TABLOID, EXECUTIVE, or [width, height] in mm.`

The same settings apply to Word files made from the document: the Word page gets the same size, orientation and margins.

### A custom size {#custom-paper}

For a size that is not in the list, such as a label or a thermal receipt, pass `[width, height]` in millimetres:

```php
Doc::view('pdf.till-receipt', ['order' => $order])->paper([80, 200])->pdf();
```

The same array works as `paper` in a template's `template.php`, or as the default paper in `config/easy-pdf-word.php`:

```php
'pdf' => [
    'paper' => [80, 200],
    // ...
],
```

The orientation still applies: in portrait the shorter side is the width, and with `->landscape()` the longer side is. A size that is not two positive numbers throws `A paper size must be [width, height] in mm, for example [100, 150].`

## Margins {#margins}

Margins are in millimetres and work like CSS margins:

```php
->margins(15)                // 15 on every side
->margins(20, 15)            // 20 top and bottom, 15 right and left
->margins(25, 15, 20)        // 25 top, 15 right and left, 20 bottom
->margins(25, 15, 20, 10)    // top, right, bottom, left
```

The config and a template's `template.php` take the same forms as arrays: `[15]`, `[20, 15]`, `[25, 15, 20]` or `[25, 15, 20, 10]`.

Right and left are the sides of the paper, in Arabic documents too and with every engine: `->margins(15, 40, 15, 10)` leaves 40 mm on the right and 10 mm on the left.

## Header and footer {#header-footer}

`->header()` and `->footer()` take HTML that is printed on every page. `{page}` and `{pages}` become the page number and the page count:

```php
Doc::template('report', $data)
    ->locale('ar')
    ->margins(30, 15, 20, 15)
    ->header('<div style="text-align: left; font-size: 9pt; color: #6B7280;">شركة بيزتك - تقرير داخلي</div>')
    ->footer('<div style="text-align: center; font-size: 9pt;">صفحة {page} من {pages}</div>')
    ->pdf();
```

- Given here, they replace the template's own `header.blade.php` and `footer.blade.php`. Most bundled templates have a footer with page numbers.
- mPDF prints the header in the top margin and the footer in the bottom margin, so give those margins room for them, as `->margins(30, 15, 20, 15)` does above.
- Chromium and Gotenberg draw the header and footer apart from the page, in the document font at a small size (9px). Inline styles in your HTML still apply.
- In Word files, the header and footer become plain text, and `{page}` and `{pages}` become Word page fields (see [Word files](/guide/word)).

### Arabic digits in headers and footers {#header-digits}

With `->numerals('arabic')`, the digits in your header and footer text are converted too. mPDF also prints `{page}` and `{pages}` in Arabic digits (١، ٢، ٣). Chromium and Gotenberg always print `{page}` and `{pages}` in Latin digits.

## Watermark {#watermark}

`->watermark()` prints text diagonally across every page of the PDF:

```php
Doc::template('quotation', $data)->locale('ar')->watermark('مسودة')->pdf();

Doc::template('invoice', $data)->watermark('نسخة', opacity: 0.08, color: '#B91C1C')->pdf();
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `$text` | | Up to 100 characters. Arabic is shaped and digits follow `->numerals()`. Empty text throws. |
| `opacity` | `0.12` | From `0.01` (barely visible) to `1` (solid). |
| `color` | `'#000000'` | Any CSS colour: `#B91C1C`, `rgb(185, 28, 28)`, `red`. Anything else falls back to black. |

The watermark is drawn in the document font and sized to fit the page. It works with mPDF, Chromium and Gotenberg. Word files are made without it.

## Password {#password}

`->password()` encrypts the PDF:

```php
// Asked for when the file is opened
Doc::template('payslip', $data)->locale('ar')->password('19870412')->pdf();

// Opens freely, but readers may only print it
Doc::template('receipt', $data)->password('', owner: 'admin-secret', allow: ['print'])->pdf();
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `$user` | | The password asked for to open the file. `''` opens the file without one. |
| `owner` | random | The password that lifts the limits in `allow`. Without one, a random password is used, so nobody can lift them. |
| `allow` | `['print', 'print-highres', 'copy']` | What readers may do. |

The permissions you can put in `allow`:

| Permission | Allows |
| --- | --- |
| `print` | Printing |
| `print-highres` | Printing at full quality |
| `copy` | Copying text and images |
| `modify` | Changing the content |
| `annot-forms` | Adding comments and filling in forms |
| `fill-forms` | Filling in form fields |
| `extract` | Taking out text and images, for example for screen readers |
| `assemble` | Inserting, rotating and deleting pages |

An unknown name throws: `Unknown PDF permission [printing], expected any of: print, print-highres, copy, modify, annot-forms, fill-forms, extract, assemble.`

What to know:

- mPDF encrypts with 128-bit RC4, the strongest it offers. That keeps a file from being opened by chance; it does not protect real secrets. See [Security](/guide/security#passwords).
- Chromium cannot encrypt, so a Chromium or Gotenberg PDF is copied into an encrypted file by mPDF afterwards. `mpdf/mpdf` must be installed for that. The pages stay text, but links inside them stop working.
- Word files cannot take a password. `->word()` on a document with a password throws `Word files cannot take a password; ->password() works for PDF files only.`, so a file you believe is locked never goes out open.

## Title and author {#metadata}

The title of a PDF, shown in the viewer's title bar, comes from the first of these that is set:

1. `->title()` on the document.
2. The page title of the document's HTML. The invoice, report, letter and certificate templates set one in the document's language: `فاتورة ضريبية INV-2026-1024`, or `Tax Invoice INV-2026-1024` in English. In your own Blade view, pass a title to the layout component, as in `<x-doc::layout :doc="$doc" title="عقد عمل">`; see [Blade views and HTML](/guide/views-and-html).
3. The `title` in the template's `template.php`, such as `Receipt voucher` or `Payslip`, for a template whose page has no title of its own.

```php
Doc::template('receipt', $data)->locale('ar')->title('سند قبض RV-2026-0315')->pdf();
```

`->title()` sets the title of Word files too, which otherwise take the `title` from `template.php` (such as `Tax invoice`).

mPDF sets the author to the company name from the theme (`theme.company.name`, which defaults to `APP_NAME`), and so do Word files. Chromium sets no author.

## Where the defaults come from {#defaults}

Each setting is taken from the first place that has it:

1. A call on the document: `->paper()`, `->landscape()`, `->margins()`, `->header()`, `->footer()`.
2. The template's `template.php`: `paper`, `orientation` and `margins`, and its `header.blade.php` and `footer.blade.php`. The receipt template, for example, is A5 landscape and the certificate A4 landscape.
3. `config/easy-pdf-word.php`: `pdf.paper`, `pdf.orientation` and `pdf.margins`.
4. A4, portrait, 15 mm on every side.

```php
// config/easy-pdf-word.php
'pdf' => [
    'paper' => 'Letter',
    'orientation' => 'portrait',
    'margins' => [20, 15],
    // ...
],
```

Template folders are described in [Your own templates](/guide/custom-templates), and every config key in [Configuration](/guide/configuration).
