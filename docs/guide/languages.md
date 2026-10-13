# Turkish and other languages

The bundled templates come in Arabic, English and Turkish. This page shows what a language changes in a document, and how to add one of your own.

## Turkish {#turkish}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('invoice', $data)->locale('tr')->pdf();
```

`tr` and `tr_TR` both work. All twelve templates have Turkish labels, and the document follows Turkish conventions:

| | Arabic and English | Turkish |
| --- | --- | --- |
| Labels | فاتورة ضريبية / Tax Invoice | Fatura, Ara toplam, KDV (%20) |
| Dates | 2026/10/08 | 08.10.2026 |
| Numbers | 1,250.50 | 1.250,50 |
| Months and days | أكتوبر، الخميس / October, Thursday | Ekim, Perşembe |
| Amount in words | فقط ألف ومائتان وخمسون جنيهاً لا غير / one thousand two hundred fifty EGP only | Yalnız bin iki yüz elli Türk Lirası |
| Currency label | ج.م / EGP | TL for TRY, the code for others |

The default font, Cairo, has every Turkish letter, and so does Noto Naskh (`naskh`). **Tajawal does not** (ğ, ş, İ are missing), so don't use `->font('tajawal')` for Turkish. None of the bundled fonts has the ₺ sign, which is why TRY is labelled TL; to print ₺, [add a font](/guide/fonts) that has it and set your own label (see [below](#change)).

Amounts in words in Turkish, like every language other than Arabic, need the PHP `intl` extension. Without it the amount-in-words line is left out.

Your data is printed as you give it. A Turkish invoice needs Turkish item names and a Turkish company name in your data and theme.

## Other locales {#other-locales}

Any locale name works with `->locale()`. A language the package does not know (`fr`, `de`, `fa`) gets:

- the direction of the language: Persian, Urdu and Hebrew are right to left (see [Right-to-left languages](/guide/arabic#rtl-languages)),
- the English labels, dates written 2026/10/08 and numbers 1,250.50,
- month names and amounts in words from `intl` in that language, worded the English way ("mille deux cents EUR only").

To get labels, dates and numbers right for such a language, add it as below.

## The language files {#files}

Everything the package knows about a language is in two kinds of JSON file. JSON, rather than PHP, so other versions of the library can read the same files.

- `vendor/biztecheg/easy-pdf-word/resources/lang/{language}.json`: the language itself, used by every template and by your own views.
- `lang/{language}.json` in each template folder: that template's labels. See [Labels](/guide/custom-templates#lang).

The Turkish language file:

```json
{
    "name": "Türkçe",
    "direction": "ltr",
    "months": ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"],
    "days": ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"],
    "date_format": "dd.MM.yyyy",
    "datetime_format": "dd.MM.yyyy HH:mm",
    "decimal_separator": ",",
    "thousands_separator": ".",
    "list_separator": ", ",
    "amount_in_words": {
        "amount": ":words :currency",
        "fraction": ":amount ve :fraction/:per",
        "only": "Yalnız :amount"
    },
    "currencies": { "TRY": "TL" },
    "currency_names": { "TRY": "Türk Lirası", "USD": "ABD Doları", "EUR": "Avro" }
}
```

| Key | What it is |
| --- | --- |
| `name` | The language's own name |
| `direction` | `rtl` or `ltr` (the direction itself comes from the language code; this records it for other versions of the library) |
| `months`, `days` | Month names from January, day names from Sunday |
| `date_format`, `datetime_format` | How `$doc->date()` writes a date, with the letters below |
| `decimal_separator`, `thousands_separator` | Used by `$doc->number()`, `$doc->money()` and `$doc->rate()` |
| `list_separator` | Between values joined by `$doc->join()` and list placeholders in a `word.docx` |
| `amount_in_words` | How words, currency and fraction are put together. `:words` is the number in words, `:currency` the currency name, `:amount` the text so far, `:fraction` and `:per` the cents (50 and 100) |
| `currencies` | Short labels, as printed next to amounts |
| `currency_names` | Names used in the amount in words; the code when a currency is not listed |

A key a language leaves out is taken from English. Arabic amounts in words are written by the package's own tafqeet, not by `amount_in_words`.

Date patterns use these letters, the same as ICU, Java and .NET: `yyyy` 2026, `yy` 26, `MM` 09, `M` 9, `dd` 08, `d` 8, `HH` 14, `H` 9, `mm` 05, `ss` 07. Text in single quotes is printed as it is: `dd.MM.yyyy 'saat' HH:mm`. Month names are not part of a pattern; use `$doc->monthName()`.

## Add a language {#add}

Take French as the example.

**1. Add a language file.** Put `fr.json` in a folder of your app, for example `lang/easy-pdf-word/fr.json`:

```json
{
    "name": "Français",
    "direction": "ltr",
    "months": ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"],
    "days": ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"],
    "date_format": "dd/MM/yyyy",
    "datetime_format": "dd/MM/yyyy HH:mm",
    "decimal_separator": ",",
    "thousands_separator": "\u00a0",
    "amount_in_words": {
        "amount": ":words :currency",
        "fraction": ":amount et :fraction/:per",
        "only": "Arrêtée à la somme de :amount"
    },
    "currency_names": { "EUR": "euros", "MAD": "dirhams" }
}
```

(`\u00a0` is a space that keeps the number on one line) and tell the package about the folder in `AppServiceProvider::boot()`:

```php
use BiztechEG\EasyPdfWord\Support\Language;

public function boot(): void
{
    Language::addPath(lang_path('easy-pdf-word'));
}
```

**2. Add the labels.** Copy each template you use (see [Override a bundled template](/guide/templates#override)) and add `lang/fr.json` next to `lang/en.json`, with the same keys:

```bash
php artisan doc:template invoice
```

```json
{
    "title": "Facture",
    "number": "N° de facture",
    "date": "Date d'émission",
    "subtotal": "Sous-total",
    "vat": "TVA (:rate %)",
    "total": "Total à payer"
}
```

A label you leave out is printed in English.

**3. Check the font.** Latin languages work with the bundled fonts. Other scripts (Hebrew, Devanagari, Thai) need a font that has their letters; see [Fonts](/guide/fonts).

Then `->locale('fr')` gives French documents. Arabic numerals, the Hijri date and tafqeet stay Arabic-only.

## Change a bundled language {#change}

A file in a folder you added replaces only the keys it has. To write Turkish dates with slashes and print ₺ (with a font that has it):

::: code-group

```json [lang/easy-pdf-word/tr.json]
{
    "date_format": "dd/MM/yyyy",
    "currencies": { "TRY": "₺" }
}
```

:::

To change a template's labels, copy the template and edit its `lang/tr.json`.

## In your own templates {#helpers}

These `$doc` helpers follow the document's language, so one template works in every language:

```php
<?= $doc->e($doc->date($invoice['date'])) ?>            <!-- 08.10.2026 -->
<?= $doc->e($doc->date($invoice['issued_at'], true)) ?> <!-- 08.10.2026 14:30 -->
<?= $doc->money($total, $doc->currency('TRY')) ?>        <!-- 1.250,50 TL -->
<?= $doc->e($doc->inWords($total, 'TRY')) ?>             <!-- Yalnız bin iki yüz elli Türk Lirası ve 50/100 -->
<?= $doc->e($doc->join(['Ankara', 'İzmir'])) ?>          <!-- Ankara, İzmir -->
<?= $doc->e($doc->ucfirst('ikinci taraf')) ?>            <!-- İkinci taraf -->
<?= $doc->e($doc->language('name')) ?>                   <!-- Türkçe -->
```

See [Template helpers](/reference/template-helpers) for all of them.
