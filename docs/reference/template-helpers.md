# Template helpers

Everything you can use while writing a template or a document view: the `$doc` object, the Blade components and directives, the Arabic and ZATCA helpers, the keys of `template.php`, the layout closure and every `word.docx` placeholder. The outputs shown were produced by the package itself.

## What each file gets {#overview}

| File | What it can use |
| --- | --- |
| `pdf.blade.php`, `header.blade.php`, `footer.blade.php`, and views for `Doc::view()` | `$doc` (a [`DocContext`](#doc-context)), every top-level data key as a variable (`$invoice`, `$items` ...), the [components](#layout-component), [directives](#blade-directives) and [global helpers](#global-helpers) |
| `layout.php`, `word.php` | A closure that receives the builder, the data and `$doc`: see [layout.php and word.php](#layout-php) |
| `word.docx` | [`${placeholders}`](#word-placeholders) |
| `template.php` | The [manifest keys](#template-php) |
| `lang/ar.php`, `lang/en.php` ... | [Labels](#lang-files) read with `$doc->t()` |

For a template, the data is what you passed merged over the template's `defaults` and passed through its `prepare` callback, so computed values such as totals are there too. A data key named `doc` never replaces `$doc`.

## The $doc object {#doc-context}

`$doc` is a `BiztechEG\EasyPdfWord\Support\DocContext`. It is the same object in Blade (`$doc`) and in `layout.php` / `word.php` (the third argument), so one template works for Arabic and English, PDF and Word.

### Properties {#context-properties}

All public and read-only:

| Property | Type | Value |
| --- | --- | --- |
| `locale` | `string` | The locale as given: `ar`, `ar_EG`, `en` ... |
| `direction` | `string` | `rtl` or `ltr` |
| `font` | `string` | The document font, e.g. `cairo` |
| `numerals` | `string` | `latin` or `arabic` |
| `theme` | `array` | The resolved theme: config, then `template.php`, then `->theme()` |
| `engine` | `string` | The PDF engine's class, e.g. `BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver`; empty for Word files, headers and footers |
| `fontCss` | `string` | `@font-face` rules for engines that load fonts from CSS, else empty |

### Digits in the output {#digits}

The helpers return Latin digits. When the document uses `->numerals('arabic')`, the package converts every digit in the final text afterwards, so you never convert digits yourself. The separators depend on the font: Cairo and Tajawal keep `,` and `.`; Naskh draws the Arabic separators `٬` and `٫`. Word files always keep `,` and `.`.

Real results in an Arabic document:

| In the template | Returns | Printed with Arabic digits (Cairo) | Printed with Arabic digits (Naskh) |
| --- | --- | --- | --- |
| `$doc->number(1250.5)` | `1,250.50` | `١,٢٥٠.٥٠` | `١٬٢٥٠٫٥٠` |
| `$doc->number(-2.3)` | `-2.30` in a left-to-right `bdo` | `-٢.٣٠` | `-٢٫٣٠` |
| `$doc->money(1250.5, $doc->currency('EGP'))` | `1,250.50 ج.م` | `١,٢٥٠.٥٠ ج.م` | `١٬٢٥٠٫٥٠ ج.م` |
| `$doc->rate(14)` | `14` | `١٤` | `١٤` |
| `$doc->hijri('2026-10-08')` | `27 ربيع الآخر 1448 هـ` | `٢٧ ربيع الآخر ١٤٤٨ هـ` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `$doc->ltr('+20 100 000 0000')` | `+20 100 000 0000` in a left-to-right `bdo` | `+٢٠ ١٠٠ ٠٠٠ ٠٠٠٠` | `+٢٠ ١٠٠ ٠٠٠ ٠٠٠٠` |
| `$doc->text($date)` (a Carbon date) | `2026/10/08` | `٢٠٢٦/١٠/٠٨` | `٢٠٢٦/١٠/٠٨` |

E-mail addresses and links keep their Latin digits.

### isRtl() {#context-is-rtl}

```php
$doc->isRtl(): bool
```

`true` in right-to-left documents.

### start() / end() {#context-start-end}

```php
$doc->start(): string
$doc->end(): string
```

The physical side for "start" and "end": `start()` is `right` and `end()` is `left` in Arabic documents, the opposite in English ones. Use them in CSS so one template fits both directions.

```blade
<td style="text-align: {{ $doc->end() }};">{{ $doc->number($totals['total']) }}</td>
```

### theme() {#context-theme}

```php
$doc->theme(string $key, mixed $default = null): mixed
```

A theme value by dot key. `primary`, `text`, `muted` and `border` are always valid CSS colours.

```blade
<h1 style="color: {{ $doc->theme('primary') }};">{{ $doc->theme('company.name') }}</h1>
{{-- شركة بيزتك --}}
```

### t() {#context-t}

```php
$doc->t(string $key, array $replace = []): string
```

A label from the template's `lang/{language}.php` (dot keys for nested arrays), falling back to `lang/en.php`, then to the key itself. `:name` placeholders are replaced from `$replace`. Labels exist for templates only: in a `Doc::view()` view, `t()` returns the key.

```php
// lang/ar.php: 'page' => 'صفحة :current من :total'
$doc->t('page', ['current' => 3, 'total' => 5]);   // صفحة 3 من 5
// lang/en.php: 'page' => 'Page :current of :total'  → Page 3 of 5
```

Names that begin with another name work in any order: in `'صفحة :page من :pages'`, `$doc->t('page', ['page' => 3, 'pages' => 5])` gives `صفحة 3 من 5`, because the longer name is replaced first.

### translations() {#context-translations}

```php
$doc->translations(): array
```

All labels of the document's language, with English filling the gaps.

### ltr() {#context-ltr}

```php
$doc->ltr(int|float|string|null $value): HtmlString
```

Keeps a left-to-right value (phone, tax number, code, e-mail) in its own order inside Arabic text. The value is escaped.

```blade
{{ $doc->ltr('+20 100 000 0000') }}
{{-- <bdo dir="ltr">+20 100 000 0000</bdo> --}}
```

### text() {#context-text}

```php
$doc->text(mixed $value): string
```

Any value as display text: `null` and `false` give `''`, `true` gives `✓`, dates give `Y/m/d` (`2026/10/08`), backed enums their value, other enums their name, arrays and objects JSON.

### number() {#context-number}

```php
$doc->number(int|float|string|null $value, int $decimals = 2): HtmlString
```

A number with thousands separators and `$decimals` decimals (0 to 10). Strings such as `'1,250.50'` and `'١٬٢٥٠٫٥'` are read correctly. A negative number is wrapped in `<bdo dir="ltr">` so the minus stays in front in Arabic text. Returns HTML: print it with double braces. In `layout.php` and `word.php` use [`numberText()`](#context-number-text) instead.

| Call | Returns |
| --- | --- |
| `$doc->number(1250.5)` | `1,250.50` |
| `$doc->number(1250.5, 0)` | `1,251` |
| `$doc->number('١٬٢٥٠٫٥')` | `1,250.50` |
| `$doc->number(null)` | `0.00` |
| `$doc->number(-2.3)` | `<bdo dir="ltr">-2.30</bdo>` |

### numberText() {#context-number-text}

```php
$doc->numberText(int|float|string|null $value, int $decimals = 2): string
```

The same formatting as plain text, for builder blocks and Word files: `numberText(-2.3)` is `-2.30`, `numberText(1234.5678, 3)` is `1,234.568`.

### rate() {#context-rate}

```php
$doc->rate(int|float|string|null $value): string
```

A rate or percentage with only the decimals it needs: `rate(14)` is `14`, `rate(2.5)` is `2.5`, `rate('0.750')` is `0.75`, `rate('1,500')` is `1500`.

### money() {#context-money}

```php
$doc->money(int|float|string|null $value, ?string $currency = null, int $decimals = 2): HtmlString
```

`number()` followed by a space and the currency text (escaped).

| Call | Arabic document | English document |
| --- | --- | --- |
| `$doc->money(1250.5)` | `1,250.50` | `1,250.50` |
| `$doc->money(1250.5, 'EGP')` | `1,250.50 EGP` | `1,250.50 EGP` |
| `$doc->money(1250.5, $doc->currency('EGP'))` | `1,250.50 ج.م` | `1,250.50 EGP` |
| `$doc->money(1.125, 'KWD', $doc->decimals('KWD'))` | `1.125 KWD` | `1.125 KWD` |

### tafqeet() {#context-tafqeet}

```php
$doc->tafqeet(int|float|string $amount, string $currency, bool $only = true): string
```

The amount in Arabic words, in any document language, wrapped in `فقط ... لا غير` unless `$only` is `false`. Currencies Tafqeet knows (EGP, SAR, AED, QAR, KWD, USD, EUR and those in the `currencies` config) are read with their units; any other currency is read as a number followed by its code and the fraction.

| Call | Returns |
| --- | --- |
| `$doc->tafqeet(1250.5, 'EGP')` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` |
| `$doc->tafqeet(1250.5, 'EGP', false)` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `$doc->tafqeet(1150, 'SAR')` | `فقط ألف ومائة وخمسون ريالاً لا غير` |
| `$doc->tafqeet(150.25, 'GBP')` | `فقط مائة وخمسون GBP و25/100 لا غير` |

### inWords() {#context-in-words}

```php
$doc->inWords(int|float|string $amount, string $currency, bool $only = true): string
```

The amount in words in the document's language: Arabic tafqeet for Arabic documents, the `intl` spell-out for other languages. Returns `''` when `ext-intl` is missing in a non-Arabic document.

| Call | Arabic document | English document |
| --- | --- | --- |
| `$doc->inWords(1250.5, 'EGP')` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` | `one thousand two hundred fifty EGP and 50/100 only` |
| `$doc->inWords(1250.5, 'EGP', false)` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` | `one thousand two hundred fifty EGP and 50/100` |
| `$doc->inWords(1.125, 'KWD')` | `فقط دينار واحد ومائة وخمسة وعشرون فلساً لا غير` | `one KWD and 125/1000 only` |

### decimals() {#context-decimals}

```php
$doc->decimals(?string $currency): int
```

The decimal places of a currency's amounts: `3` for KWD, BHD, OMR, JOD, IQD, LYD and TND, `0` for JPY and KRW, `2` for the rest and for `null`.

### currency() {#context-currency}

```php
$doc->currency(string $code): string
```

A currency's short label: the template's `currencies.CODE` label when its `lang` file has one; otherwise, in right-to-left documents, a built-in Arabic label (EGP `ج.م`, SAR `ر.س`, AED `د.إ`, KWD `د.ك`, QAR `ر.ق`, BHD `د.ب`, OMR `ر.ع`, JOD `د.أ`, USD `دولار`, EUR `يورو`); otherwise the code in capitals.

| Call | Arabic document | English document |
| --- | --- | --- |
| `$doc->currency('EGP')` | `ج.م` | `EGP` |
| `$doc->currency('sar')` | `ر.س` | `SAR` |
| `$doc->currency('GBP')` | `GBP` | `GBP` |

### hasHijri() / hijri() {#context-hijri}

```php
$doc->hasHijri(): bool
$doc->hijri(mixed $date = null, string $pattern = 'd MMMM y'): string
```

`hijri()` gives the Umm al-Qura date of a Carbon or `DateTime` object or a date string (today when `null`), with Arabic month names and ` هـ` at the end. `$pattern` is an ICU date pattern. It returns Latin digits, which the document converts when it uses Arabic digits. Hijri dates need `ext-intl`: `hijri()` throws `RuntimeException` without it, so guard optional dates with `hasHijri()`.

| Call | Returns |
| --- | --- |
| `$doc->hijri('2026-10-08')` | `27 ربيع الآخر 1448 هـ` |
| `$doc->hijri('2026-10-08', 'd/M/y')` | `27/4/1448 هـ` |
| `$doc->hijri('2026-10-08', 'EEEE d MMMM y')` | `الخميس 27 ربيع الآخر 1448 هـ` |

```blade
@if ($doc->hasHijri())
    <p>{{ $doc->t('hijri_date') }}: {{ $doc->hijri($invoice['date']) }}</p>
@endif
```

### image() {#context-image}

```php
$doc->image(?string $source): ?string
```

An image ready for `<img src>`: a local file becomes a data URI, an allowed URL is returned as it is, an image data URI is kept. Returns `null` for anything that may not or cannot be used: a file outside the `images.paths` folders, a file that is not an image, a URL whose host `images.remote` does not allow, an SVG that loads other files, other schemes. See [Images](/guide/images).

```blade
@if ($logo = $doc->image($doc->theme('logo')))
    <img src="{{ $logo }}" style="height: 18mm;">
@endif
```

Builder blocks (`image()`, a cell's `image`) and `word.docx` images go through the same check, so pass them the raw path.

### usesCssFonts() {#context-uses-css-fonts}

```php
$doc->usesCssFonts(): bool
```

`true` when the engine loads fonts from CSS (Chromium, Gotenberg); `$doc->fontCss` then holds the `@font-face` rules. `x-doc::layout` prints them, and a view without the layout gets them added before `</head>`, so you rarely need this.

### toFloat() {#context-to-float}

```php
DocContext::toFloat(int|float|string|null $value): float
```

A static helper that reads formatted numbers: `'1,250.50'` and `'١٬٢٥٠٫٥٠'` both give `1250.5`.

## The layout component {#layout-component}

`x-doc::layout` gives a document view its HTML skeleton: `lang` and `dir` on `<html>`, the document font, direction and text colour on `<body>`, and base styles that work on every engine.

| Attribute or slot | Required | Value |
| --- | --- | --- |
| `:doc` | Yes | Pass `$doc` |
| `title` | No | The HTML `<title>` |
| `styles` slot | No | Your `<style>` block, placed in `<head>` after the base styles |
| Content | | The page body |

```blade
<x-doc::layout :doc="$doc" :title="$doc->t('title').' '.$invoice['number']">
    <x-slot:styles>
        <style>
            .total { color: {{ $doc->theme('primary') }}; font-weight: bold; }
        </style>
    </x-slot:styles>

    <h1>{{ $doc->t('title') }}</h1>
    <p class="text-end total">{{ $doc->money($totals['total'], $doc->currency($invoice['currency'])) }}</p>
</x-doc::layout>
```

Base styles: `body` at 10.5pt with line height 1.5, tables collapsed at full width, cells aligned to the top, and these classes:

| Class | Effect |
| --- | --- |
| `text-start` | Aligned to the start side (right in Arabic) |
| `text-end` | Aligned to the end side (left in Arabic) |
| `text-center` | Centred |
| `muted` | The theme's `muted` colour |
| `ltr` | Left-to-right text inside a right-to-left page |
| `nowrap` | No line breaks |

### QR component {#qr-component}

`x-doc::qr` prints a QR code image.

| Attribute | Default | Value |
| --- | --- | --- |
| `value` | required | The text to encode |
| `size` | `28mm` | Width and height |
| Others | | Passed on to the `<img>` tag |

```blade
<x-doc::qr :value="$verifyUrl" size="25mm" />
{{-- <img src="data:image/png;base64,..." alt="QR" style="width: 25mm; height: 25mm;"> --}}
```

## Blade directives {#blade-directives}

| Directive | Same as |
| --- | --- |
| `@tafqeet(1250.5, 'EGP')` | `Arabic::tafqeet(1250.5, 'EGP')`: `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `@hijri('2026-10-08')` | `Arabic::hijri('2026-10-08')`: `٢٧ ربيع الآخر ١٤٤٨ هـ` |

Both print escaped text and take the same arguments as the `Arabic` methods. Unlike `$doc->tafqeet()`, `@tafqeet` does not add `فقط ... لا غير` unless you pass `only: true`, and `@hijri` prints Arabic digits even in a document with Latin digits. Inside document templates, prefer the `$doc` methods, which follow the document's settings.

## Global helpers {#global-helpers}

Defined unless your app already has a function with the same name.

```php
tafqeet(int|float|string $amount, ?string $currency = null, bool $only = false): string
hijri_date(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = 'arabic'): string
arabic_numerals(string|int|float $value): string
```

| Call | Returns |
| --- | --- |
| `tafqeet(1250)` | `ألف ومائتان وخمسون` |
| `tafqeet(1250.5, 'EGP')` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `tafqeet(1250.5, 'EGP', true)` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` |
| `hijri_date('2026-10-08')` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `hijri_date('2026-10-08', 'd MMMM y', 'latin')` | `27 ربيع الآخر 1448 هـ` |
| `arabic_numerals('INV-2026-1024')` | `INV-٢٠٢٦-١٠٢٤` |
| `arabic_numerals(1250.5)` | `١٢٥٠٫٥` |

They work anywhere in your app: a Blade page, an API response, an SMS.

## Arabic {#arabic}

`BiztechEG\EasyPdfWord\Arabic\Arabic` is one entry point for the Arabic helpers.

```php
Arabic::tafqeet(int|float|string $amount, ?string $currency = null, bool $only = false): string
Arabic::hijri(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = Numerals::ARABIC): string
Arabic::numerals(string|int|float $value, string $style = Numerals::ARABIC): string
Arabic::direction(string $text): string
```

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::tafqeet(1250.5, 'EGP');            // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::tafqeet(3.03, 'SAR', only: true);  // فقط ثلاثة ريالات وثلاث هللات لا غير
Arabic::tafqeet(123456);                   // مائة وثلاثة وعشرون ألفاً وأربعمائة وستة وخمسون
Arabic::hijri('2026-10-08');               // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::hijri('2026-10-08', 'dd/MM/y');    // ٢٧/٠٤/١٤٤٨ هـ
Arabic::numerals('12,500.75');             // ١٢٬٥٠٠٫٧٥
Arabic::numerals('١٢٣', 'latin');          // 123
Arabic::direction('فاتورة Invoice');        // rtl (decided by the first letter)
Arabic::direction('Invoice فاتورة');        // ltr
```

## Tafqeet {#tafqeet}

`BiztechEG\EasyPdfWord\Arabic\Tafqeet` reads numbers and amounts in Arabic words, with the right noun forms (مائتا جنيه، ثلاثة ريالات، أحد عشر ديناراً).

```php
Tafqeet::words(int|float|string $number, string $gender = Tafqeet::MASCULINE): string
Tafqeet::amount(int|float|string $amount, string $currency = 'EGP', bool $only = false): string
Tafqeet::registerCurrency(string $code, array $definition): void
Tafqeet::currencies(): array
Tafqeet::decimals(string $currency): ?int
```

Constants: `Tafqeet::MASCULINE` (`'m'`), `Tafqeet::FEMININE` (`'f'`), `Tafqeet::MAX` (999,999,999,999,999, the largest number read). Strings may contain thousands separators and Arabic digits (`'١٢٣٫٤٥'`).

| Call | Returns |
| --- | --- |
| `Tafqeet::words(1250)` | `ألف ومائتان وخمسون` |
| `Tafqeet::words(1.05)` | `واحد فاصلة صفر خمسة` |
| `Tafqeet::words(3, Tafqeet::FEMININE)` | `ثلاث` |
| `Tafqeet::amount(1250.5, 'EGP')` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `Tafqeet::amount(3, 'SAR', true)` | `فقط ثلاثة ريالات لا غير` |
| `Tafqeet::amount(1234.567, 'KWD')` | `ألف ومائتان وأربعة وثلاثون ديناراً وخمسمائة وسبعة وستون فلساً` |
| `Tafqeet::currencies()` | `['EGP', 'SAR', 'AED', 'QAR', 'KWD', 'USD', 'EUR']` |
| `Tafqeet::decimals('KWD')` | `3` (`null` for an unknown currency) |

`amount()` throws `InvalidArgumentException` for an unknown currency; both methods throw it for text that is not a number or a number above `MAX`.

### Adding a currency {#tafqeet-currencies}

Each unit lists four Arabic forms: singular, dual, plural (3 to 10) and accusative (11 to 99). `gender` is `'m'` (default) or `'f'`, and `subunits` is how many minor units make one main unit (default 100). Add currencies for the whole app in `config/easy-pdf-word.php`, where they are registered when the app boots:

```php
'currencies' => [
    'JOD' => [
        'main' => ['forms' => ['دينار', 'ديناران', 'دنانير', 'ديناراً']],
        'sub' => ['forms' => ['فلس', 'فلسان', 'فلوس', 'فلساً']],
        'subunits' => 1000,
    ],
],
```

```php
Tafqeet::amount(5.25, 'JOD');       // خمسة دنانير ومائتان وخمسون فلساً
Tafqeet::amount(15, 'JOD', true);   // فقط خمسة عشر ديناراً لا غير
```

`Tafqeet::registerCurrency('JOD', [...])` does the same at runtime. A unit without four forms throws `InvalidArgumentException`.

## Hijri {#hijri}

`BiztechEG\EasyPdfWord\Arabic\Hijri` formats Umm al-Qura dates. Needs `ext-intl` (`RuntimeException` without it).

```php
Hijri::format(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = Numerals::ARABIC, bool $suffix = true): string
Hijri::parts(DateTimeInterface|string|int|null $date = null): array
```

| Call | Returns |
| --- | --- |
| `Hijri::format('2026-10-08')` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `Hijri::format('2026-10-08', numerals: 'latin')` | `27 ربيع الآخر 1448 هـ` |
| `Hijri::format('2026-10-08', suffix: false)` | `٢٧ ربيع الآخر ١٤٤٨` |
| `Hijri::parts('2026-10-08')` | `['year' => 1448, 'month' => 4, 'day' => 27]` |

Useful pattern letters: `d` / `dd` day, `M` / `MM` month number, `MMMM` month name, `y` year, `EEEE` weekday name.

## Numerals {#numerals}

`BiztechEG\EasyPdfWord\Arabic\Numerals` converts digits. Constants: `Numerals::LATIN` (`'latin'`) and `Numerals::ARABIC` (`'arabic'`).

```php
Numerals::toArabic(string|int|float $value, bool $separators = true): string
Numerals::toLatin(string|int|float $value): string
Numerals::convert(string|int|float $value, string $style, bool $separators = true): string
Numerals::convertHtml(string $html, string $style, bool $separators = true): string
Numerals::normalizeStyle(string $style): string
```

| Call | Returns |
| --- | --- |
| `Numerals::toArabic('12,500.75')` | `١٢٬٥٠٠٫٧٥` |
| `Numerals::toArabic('12,500.75', false)` | `١٢,٥٠٠.٧٥` |
| `Numerals::toArabic('info@biz2tech.com 2026')` | `info@biz2tech.com ٢٠٢٦` |
| `Numerals::toLatin('١٢٬٥٠٠٫٧٥')` | `12,500.75` (Persian digits are read too) |
| `Numerals::convert('٢٠٢٦', 'latin')` | `2026` |
| `Numerals::convertHtml('<p style="width: 50mm">المجموع 1,250.50</p>', 'arabic')` | `<p style="width: 50mm">المجموع ١٬٢٥٠٫٥٠</p>` |
| `Numerals::normalizeStyle('hindi')` | `arabic` |

`convertHtml()` changes text only: tags, attributes, `<style>`, `<script>`, comments and entities are left alone. `normalizeStyle()` accepts the same names as `->numerals()` and throws `InvalidArgumentException` for others.

## Direction {#direction}

`BiztechEG\EasyPdfWord\Arabic\Direction`. Constants: `Direction::RTL` (`'rtl'`), `Direction::LTR` (`'ltr'`).

```php
Direction::forLocale(?string $locale): string
Direction::isRtlLocale(?string $locale): bool
Direction::ofText(string $text, string $default = Direction::LTR): string
```

`forLocale('ar_EG')` is `rtl`, `forLocale('en')` is `ltr`. Right-to-left languages: `ar`, `arc`, `ckb`, `dv`, `fa`, `he`, `ku`, `ps`, `sd`, `ug`, `ur`, `yi`. `ofText()` follows the first letter, like `dir="auto"`: `ofText('123 فاتورة')` is `rtl`.

## ZatcaQr {#zatca-qr}

`BiztechEG\EasyPdfWord\Zatca\ZatcaQr` builds the QR payload of Saudi simplified tax invoices (ZATCA phase 1): five TLV fields, base64 encoded. The `invoice` and `credit-note` templates build it for you with `'qr' => 'zatca'`.

```php
ZatcaQr::make(string $sellerName, string $vatNumber, DateTimeInterface|string $timestamp, int|float|string $total, int|float|string $vatTotal): ZatcaQr
$qr->toTlv(): string
$qr->toBase64(): string
$qr->toDataUri(int $scale = 5): string
ZatcaQr::decode(string $base64): array
```

The public read-only properties `sellerName`, `vatNumber`, `timestamp`, `total` and `vatTotal` hold the values as encoded: the time in UTC as `2026-10-08T14:30:00Z` (a date without a time keeps its day), the amounts with two decimals (`'1,150.00'` is read as 1150). A field over 255 bytes throws `InvalidArgumentException`.

```php
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

$qr = ZatcaQr::make('شركة بيزتك', '300000000000003', '2026-10-08 14:30:00', 1150, 150);

$qr->toBase64();
// ARPYtNix2YPYqSDYqNmK2LLYqtmDAg8zMDAwMDAwMDAwMDAwMDMDFDIwMjYtMTAtMDhUMTQ6MzA6MDBaBAcxMTUwLjAwBQYxNTAuMDA=

ZatcaQr::decode($qr->toBase64());
// [1 => 'شركة بيزتك', 2 => '300000000000003', 3 => '2026-10-08T14:30:00Z', 4 => '1150.00', 5 => '150.00']
```

The time is read in the app's timezone and converted to UTC. In a template, draw it with `<x-doc::qr :value="$qr->toBase64()" />`, `<img src="{{ $qr->toDataUri() }}">`, or `$builder->qr($qr->toBase64(), 30)` in `layout.php`.

## Currency {#currency}

`BiztechEG\EasyPdfWord\Support\Currency` knows how many decimals a currency uses.

```php
Currency::decimals(?string $code): int
Currency::round(int|float|string|null $amount, ?string $code): float
```

| Call | Returns |
| --- | --- |
| `Currency::decimals('KWD')` | `3` |
| `Currency::decimals('OMR')` | `3` |
| `Currency::decimals('JPY')` | `0` |
| `Currency::decimals('EGP')` | `2` |
| `Currency::round(10.4567, 'KWD')` | `10.457` |
| `Currency::round(10.4567, 'EGP')` | `10.46` |

Use it in a template's `prepare` callback so totals round like the printed amounts.

## Qr {#qr}

```php
BiztechEG\EasyPdfWord\Support\Qr::dataUri(string $value, int $scale = 5): string
```

A QR code of any text as a PNG data URI (`data:image/png;base64,...`) that every engine can show. `x-doc::qr` uses it.

## template.php {#template-php}

`template.php` returns an array. Every key is optional. A folder with only a `pdf.blade.php` already works with `Doc::template()`, but `doc:templates` and the preview page list only folders that have a `template.php`.

| Key | Type | Used for | Default |
| --- | --- | --- | --- |
| `title` | `string` | `doc:templates`, the preview page, and the file's title property | The folder name |
| `description` | `string` | The preview page | `''` |
| `locales` | `array` | Languages listed in `doc:templates` and offered on the preview page | `['ar', 'en']` |
| `paper` | `string` or `array` | A paper name such as `'A4'`, `'A5'`, `'A4-L'` (A4 landscape), or `[width, height]` in mm | `pdf.paper` config |
| `orientation` | `string` | `'portrait'` or `'landscape'` | `pdf.orientation` config |
| `margins` | `array` | Millimetres, 1 to 4 values like `->margins()`: `[15, 12]` | `pdf.margins` config |
| `theme` | `array` | Theme values for this template, between the config and `->theme()` | `[]` |
| `fields` | `array` | Laravel validation rules for the data | `[]` |
| `defaults` | `array` | Data used when the caller does not pass it (merged deeply) | `[]` |
| `prepare` | `callable` | `fn (array $data, array $theme = []): array`, adds computed values after validation | none |
| `sample` | `array` or `callable` | Example data for the preview page, `doc:sample` and tests | `[]` |

```php
<?php

// resources/doc-templates/packing-list/template.php
return [
    'title' => 'Packing list',
    'description' => 'Boxes and items in one shipment.',
    'locales' => ['ar', 'en'],

    'paper' => 'A4',
    'orientation' => 'portrait',
    'margins' => [15, 12],

    'theme' => ['primary' => '#7C3AED'],

    'fields' => [
        'shipment.number' => ['required', 'string'],
        'shipment.date' => ['required', 'date'],
        'customer.name' => ['required', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:1'],
        'items.*.weight' => ['nullable', 'numeric', 'min:0'],
    ],

    'defaults' => [
        'shipment' => ['carrier' => 'Aramex'],
    ],

    'prepare' => function (array $data, array $theme = []): array {
        $data['items'] = array_values($data['items']);
        $data['total_weight'] = array_sum(array_map(fn ($item) => (float) ($item['weight'] ?? 0), $data['items']));
        $data['sender'] = $theme['company']['name'] ?? '';

        return $data;
    },

    'sample' => [
        'shipment' => ['number' => 'SHP-2026-0315', 'date' => '2026-10-08'],
        'customer' => ['name' => 'مؤسسة النور'],
        'items' => [
            ['description' => 'طابعة فواتير حرارية', 'quantity' => 2, 'weight' => 3.5],
            ['description' => 'ورق حراري 80 مم', 'quantity' => 40, 'weight' => 12],
        ],
    ],
];
```

## layout.php and word.php {#layout-php}

Both files return a closure that adds [builder blocks](/reference/api#document-builder):

```php
function (DocumentBuilder $builder, array $data, DocContext $doc): void
```

The first argument is a `BiztechEG\EasyPdfWord\Builder\DocumentBuilder`, the second the prepared data, the third the same [`$doc`](#doc-context) as in Blade. Name the first one as you like (`$list`, `$word` ...).

Which file makes which format:

| The folder has | PDF from | Word from |
| --- | --- | --- |
| `layout.php` | `layout.php` | `layout.php` |
| `pdf.blade.php` and `word.php` | `pdf.blade.php` | `word.php` |
| `pdf.blade.php` and `layout.php` | `pdf.blade.php` | `layout.php` |
| `word.php` without `pdf.blade.php` | `word.php` | `word.php` |
| `word.docx` (with any of the above) | as above | `word.docx` |

```php
<?php

// resources/doc-templates/packing-list/layout.php
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;

return function (DocumentBuilder $list, array $data, DocContext $doc): void {
    $shipment = $data['shipment'];

    $list->heading($doc->t('title'));
    $list->paragraph([
        ['text' => $doc->t('number').': ', 'bold' => true],
        ['text' => $shipment['number'], 'ltr' => true],
        '   '.$doc->t('carrier').': '.$shipment['carrier'],
    ]);
    $list->paragraph($doc->t('customer').': '.$data['customer']['name']);

    $rows = [[$doc->t('item'), $doc->t('quantity'), $doc->t('weight')]];

    foreach ($data['items'] as $item) {
        $rows[] = [$item['description'], $doc->numberText($item['quantity'], 0), $doc->numberText($item['weight'] ?? 0, 1)];
    }

    $rows[] = [$doc->t('total'), '', $doc->numberText($data['total_weight'], 1)];

    $list->table($rows, ['header' => true, 'footer' => true, 'columns' => [60, 20, ['width' => 20, 'align' => 'end']]]);
};
```

Builder text is escaped and is not HTML, so use `numberText()` rather than `number()` here, and the `ltr` style rather than `$doc->ltr()`.

## Header and footer files {#header-footer}

`header.blade.php` and `footer.blade.php` in a template folder are printed on every page. They get `$doc` and the data like `pdf.blade.php`, and `{page}` and `{pages}` become the page number and count. `->header()` and `->footer()` replace them for one document. In Word files they become plain text with page-number fields.

```blade
{{-- header.blade.php --}}
<div style="font-size: 8pt; color: #6B7280;">{{ $sender }} | {{ $doc->ltr($shipment['number']) }}</div>

{{-- footer.blade.php --}}
<div style="text-align: center; font-size: 8pt;">{{ $doc->t('page', ['current' => '{page}', 'total' => '{pages}']) }}</div>
```

With the packing list above, the English footer reads `Page 1 of 1`, and an Arabic document with `->numerals('arabic')` reads `صفحة ١ من ١`.

## Label files {#lang-files}

`lang/{language}.php` returns the labels for one language. The language part of the locale picks the file (`ar_EG` reads `ar.php`), and missing labels come from `en.php`.

```php
<?php

// resources/doc-templates/packing-list/lang/ar.php
return [
    'title' => 'قائمة التعبئة',
    'number' => 'رقم الشحنة',
    'carrier' => 'شركة الشحن',
    'customer' => 'العميل',
    'item' => 'الصنف',
    'quantity' => 'الكمية',
    'weight' => 'الوزن (كجم)',
    'total' => 'الإجمالي',
    'page' => 'صفحة :current من :total',
];
```

A `currencies` array of `CODE => label` entries is what [`$doc->currency()`](#context-currency) reads first.

## word.docx placeholders {#word-placeholders}

A `word.docx` designed in Word is filled with the template's prepared data. Write placeholders as `${name}` in the document.

| Placeholder | Value |
| --- | --- |
| `${invoice.number}`, `${buyer.name}` | A data value; dots for nested keys |
| `${items.description}` in a table row | The row is repeated for every entry of the `items` list; any list of arrays works |
| `${items.row_number}` | 1, 2, 3 ... in a repeated row |
| `${items.product.code}` | A nested value inside each row |
| `${tags}` | A list of plain values, joined with `، ` |
| `${theme.company.name}`, `${theme.primary}` | Theme values |
| `${theme.logo}`, `${logo}` | An image when the value is an image path or data URI |
| `${logo:120:60}` | The same with a size in pixels (width:height); width 120 when not given, ratio kept |
| `${t.title}`, `${t.labels.buyer}` | Labels from `lang/{language}.php` |
| `${doc.hijri_date}` | The Hijri date of `date` (or `invoice.date`); needs `ext-intl` |
| `${doc.today}` | Today as `Y/m/d` |
| `${doc.qr}` | A QR image of the `qr` value |

How values are written:

- Decimal numbers (floats) get thousands separators and the decimals of the document's currency, taken from a top-level `currency` or from a group's `currency` such as `invoice.currency` or `quote.currency` (`27501.0` gives `27,501.00`; KWD gives three decimals). Whole numbers and strings are written as they are (`25000`).
- `true` gives `✓`; `false`, `null` and missing values give an empty text. Date objects give `Y/m/d`.
- With `->numerals('arabic')` the digits become Arabic, with `,` and `.` kept: `٢٧,٥٠١.٠٠`, `INV-٢٠٢٦-١٠٢٤`.
- Values are escaped, and a `${...}` inside a value stays text.
- In right-to-left documents, a value without Arabic letters (a phone number, a date, a code) is marked left to right so Word keeps it in order; values of digits only are left as they are.
- Images follow the [image rules](/guide/images); an SVG, a missing file or an image that may not be read leaves the placeholder empty.

Set right-to-left direction for the paragraphs and tables in Word itself. For a step-by-step example, see [A template designed in Word](/recipes/word-designed-template).
