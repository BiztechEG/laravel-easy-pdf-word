# Blade views and HTML

When you already have a design in HTML and CSS, render your own Blade view or an HTML string to PDF. This page shows the layout component and the `$doc` helpers that make the same view work in Arabic and English, the CSS that works on every engine, and what to keep out of your HTML.

## Render a Blade view {#view}

`Doc::view()` takes any view name in your app and its data, like Laravel's `view()` helper:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::view('pdf.contract', ['contract' => $contract])
    ->locale('ar')
    ->pdf()
    ->download("عقد-{$contract->number}.pdf");
```

```blade
{{-- resources/views/pdf/contract.blade.php --}}
<x-doc::layout :doc="$doc" :title="'عقد رقم '.$contract->number">
    <x-slot:styles>
        <style>
            h1 { color: {{ $doc->theme('primary') }}; font-size: 18pt; margin-bottom: 4mm; }
            .parties td { width: 50%; padding: 3mm; border: 1px solid {{ $doc->theme('border') }}; }
            .amount { margin-top: 4mm; padding: 3mm; background-color: #F3F4F6; }
        </style>
    </x-slot:styles>

    <h1>عقد تقديم خدمات رقم {{ $doc->ltr($contract->number) }}</h1>

    <p>تحرر هذا العقد بتاريخ {{ $contract->signed_at->format('Y/m/d') }} بين كل من:</p>

    <table class="parties">
        <tr>
            <td><strong>الطرف الأول:</strong> {{ $contract->provider }}</td>
            <td><strong>الطرف الثاني:</strong> {{ $contract->client }}</td>
        </tr>
    </table>

    <div class="amount">
        <strong>قيمة العقد:</strong> {{ $doc->money($contract->amount, $doc->currency('EGP')) }}
        <div class="muted">{{ $doc->tafqeet($contract->amount, 'EGP') }}</div>
    </div>

    <p style="margin-top: 4mm;">للتواصل: {{ $doc->ltr($contract->phone) }}</p>
</x-doc::layout>
```

Each key of the data becomes a variable, as in any view, and the package adds `$doc`. A data key named `doc` does not replace it. All the document settings work with views: `->locale()`, `->numerals()`, `->theme()`, `->font()`, `->paper()`, `->header()`, `->footer()`, `->watermark()` and the rest.

Views make PDFs only. `->word()` on a view or on HTML throws `WordNotSupported`: "Word files are made from a template with word.php or word.docx, or from Doc::make(). Blade views and HTML only make PDFs." For a Word file, use [a template](/guide/custom-templates) or [the builder](/guide/builder).

## The layout component {#layout-component}

Wrap the view in `<x-doc::layout :doc="$doc">`. It writes the whole HTML page around your content:

- `<html lang="ar" dir="rtl">` (or `ltr`) from the locale, a UTF-8 charset and a `<title>` from the `title` attribute;
- the document font on the body, embedded in the page for the Chromium and Gotenberg engines;
- base styles: the theme's text colour, 10.5pt text with a line height of 1.5, full-width tables with collapsed borders, cells aligned to the top, and no margins around headings;
- helper classes: `text-start` and `text-end` (right and left in Arabic, the other way round in English), `text-center`, `muted` (the theme's muted colour), `ltr` (keeps a value left to right) and `nowrap`.

| Attribute or slot | What it is |
| --- | --- |
| `:doc="$doc"` | Required: the document context |
| `:title="..."` | The HTML title, optional |
| `<x-slot:styles>` | Your `<style>` block, placed in the page head after the base styles |

You can write your own `<html>` page without the component, but then you set `dir`, `lang` and the font yourself. The component is the easy way to get them right for every language.

## The $doc helpers {#doc-helpers}

`$doc` carries the document's settings and helpers for printing values correctly in any language:

| Helper | Gives |
| --- | --- |
| `$doc->theme('primary')`, `$doc->theme('company.name')` | A theme value, with dots for nested keys |
| `$doc->isRtl()`, `$doc->start()`, `$doc->end()` | The direction, and `right` / `left` (swapped in English) for `text-align` |
| `$doc->ltr($value)` | Keeps a phone number, code or e-mail left to right inside Arabic text |
| `$doc->number(1250.5)`, `$doc->money(1250.5, 'SAR')` | `1,250.50`, `1,250.50 SAR`; a minus sign stays before the digits |
| `$doc->currency('EGP')` | The short currency name for the language: `ج.م` in Arabic, `EGP` in English |
| `$doc->tafqeet(1250.5, 'EGP')` | فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير |
| `$doc->inWords(1250.5, 'EGP')` | The amount in words in the document's language |
| `$doc->hijri($date)`, `$doc->hasHijri()` | A Hijri date, and whether the `intl` extension is there to make one |
| `$doc->image($path)` | An image as a data URI, read only from the allowed folders |
| `$doc->t('key')` | A label from the template's `lang` files (in templates; in a plain view it prints the key) |
| `$doc->locale`, `$doc->direction`, `$doc->font`, `$doc->numerals` | The settings themselves |

For a QR code, use the component `<x-doc::qr :value="$url" size="30mm" />`. Blade also has `@tafqeet(1250.5, 'EGP')` and `@hijri($date)`. The [template helpers reference](/reference/template-helpers) lists every helper with its arguments.

You never convert digits yourself. With `->numerals('arabic')`, the digits in the text of the finished page become Arabic digits, while tags, CSS and links are left alone.

## An HTML string {#html}

`Doc::html()` takes HTML you have built yourself:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::html('<h1>إشعار استلام</h1><p>تم استلام طلبكم رقم 1024 وسيتم التواصل معكم خلال يومي عمل.</p>')
    ->locale('ar')
    ->pdf()
    ->stream('إشعار.pdf');
```

A fragment like this one is placed inside the layout component, so it gets the direction, font and base styles. A complete document, one that has an `<html>` tag, is used exactly as it is.

## CSS that works on every engine {#css}

The default engine, mPDF, supports CSS 2.1. Chromium supports everything, so CSS that works in mPDF works on both:

- Lay out with tables: a two-column header is a table with two cells and percentage widths. Floats work too. Flexbox and grid do not work in mPDF.
- Measure in `mm` and `pt`, which match the page.
- Use `$doc->start()` and `$doc->end()` in `text-align` instead of `left` and `right`, so the same view works in both directions.
- Put table headers in `<thead>`: both engines repeat them at the top of every page when a table runs over.
- Background colours are printed by both engines.
- Show images with `<img src="{{ $doc->image($path) }}">`: the data URI works on every engine, and the path is checked against the allowed folders. See [Images](/guide/images).

When you use Chromium in production, look at your view with both engines once: `->driver('mpdf')` and `->driver('chromium')`, or the [preview page](/guide/preview) for templates. [PDF engines](/guide/engines) has the details.

## Page breaks {#page-breaks}

Start a new page with an empty element:

```blade
<div style="page-break-before: always;"></div>
```

Long tables break across pages by themselves, with the `<thead>` repeated.

## Headers and footers {#header-footer}

A view has no header or footer file, so pass them as HTML. `{page}` and `{pages}` become the page number and count:

```php
Doc::view('pdf.contract', ['contract' => $contract])
    ->locale('ar')
    ->header('<div style="text-align: left; font-size: 8pt; color: #6B7280;">شركة بيزتك للحلول البرمجية</div>')
    ->footer(view('pdf.partials.footer', ['contract' => $contract])->render())
    ->pdf();
```

```blade
{{-- resources/views/pdf/partials/footer.blade.php --}}
<div style="text-align: center; font-size: 8pt; color: #6B7280;">
    عقد رقم {{ $contract->number }} - صفحة {page} من {pages}
</div>
```

Use inline styles: Chromium draws headers and footers apart from the page, without its CSS. The digits follow `->numerals()`, except that Chromium prints page numbers in Latin digits. A partial rendered with `view()` does not receive `$doc`; pass it what it needs. See [Page settings](/guide/page-settings) for margins and the header and footer area.

## Fonts {#fonts}

The layout component sets the document font, Cairo by default, on the whole page. Change it per document with `->font('naskh')`, `->font('tajawal')` or a font you registered:

```php
Doc::view('pdf.contract', ['contract' => $contract])->locale('ar')->font('naskh')->pdf();
```

You can also give one element another bundled or registered font in CSS, such as `font-family: 'naskh';`, with every engine: for Chromium and Gotenberg the package embeds each registered font the page's CSS names. See [Fonts](/guide/fonts) to register your own.

## Never pass user input as HTML {#trust}

::: danger Trusted HTML only
`Doc::html()` and `{!! !!}` in a view output HTML exactly as given. Never pass them anything a user typed or anything stored from a form. HTML from outside could change the document, or make the engine load files and addresses.

Print every value with `{{ }}`, which escapes it; Arabic text and numbers print correctly when escaped. To keep the line breaks of a text field, escape first and then add them: `{!! nl2br(e($customer->notes)) !!}`.
:::

```blade
<p>{{ $customer->notes }}</p>        {{-- safe: escaped --}}
<p>{!! $customer->notes !!}</p>      {{-- never do this with user data --}}
```

Templates and the builder escape every value for you. See [Security](/guide/security) for the rest of the rules.
