# Arabic support

What the package does for Arabic documents, and the tools you can use anywhere in your app: Arabic or Latin digits, keeping codes and phone numbers in order, amounts in words (tafqeet) and Hijri dates.

## What `->locale('ar')` changes {#locale}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('invoice', $data)->locale('ar')->pdf();
```

One call sets three things:

- **Direction.** The document is laid out right to left: text, tables, page header and footer. Word files get right-to-left paragraphs and tables, with Arabic text marked as Arabic so Word picks the right font and size.
- **Font.** Right-to-left documents use the `fonts.default` font from the config (Cairo), and others `fonts.default_ltr` (also Cairo). Change it per document with `->font()`; see [Fonts](/guide/fonts).
- **Labels.** Templates read their labels from `lang/ar.php` in the template folder (فاتورة ضريبية، الإجمالي ...). A label missing there falls back to `lang/en.php`.

Arabic letters are joined (shaped) by the engine: mPDF and Chrome both do it, and Word does it itself.

Locales are names such as `ar`, `en`, `ar_EG`, `ar-SA` or `zh-Hant-TW`. `ar_EG` also sets the language of Arabic text in Word files to Arabic (Egypt); plain `ar` uses Arabic (Saudi Arabia). Anything that is not a locale name, such as a path, throws an `InvalidArgumentException`.

Without `->locale()`, the document uses `locale` from `config/easy-pdf-word.php`, and when that is `null` (the default) the app's current locale, `app()->getLocale()`. So a middleware that sets the locale per user also sets the language of their documents.

### Right-to-left languages {#rtl-languages}

These languages switch a document to right to left: Arabic (`ar`), Aramaic (`arc`), Central Kurdish (`ckb`), Dhivehi (`dv`), Persian (`fa`), Hebrew (`he`), Kurdish (`ku`), Pashto (`ps`), Sindhi (`sd`), Uyghur (`ug`), Urdu (`ur`) and Yiddish (`yi`), with any region (`fa_IR`, `ur-PK`). The bundled templates have Arabic and English labels; other languages need a template with their own `lang` file (see [Your own templates](/guide/custom-templates)).

To set the direction yourself, whatever the language:

```php
Doc::view('pdf.contract', $data)->locale('ar')->direction('ltr')->pdf();   // or ->ltr()
Doc::html($html)->rtl()->pdf();                                          // ->direction('rtl')
```

## Arabic or Latin digits {#numerals}

```php
Doc::template('invoice', $data)->locale('ar')->numerals('arabic')->pdf();   // ١٢٣
Doc::template('invoice', $data)->locale('ar')->numerals('latin')->pdf();    // 123
```

The default is `latin`, set by `numerals` in the config. Change it there for the whole app:

```php
// config/easy-pdf-word.php
'numerals' => 'arabic',
```

- Only text is converted. Tags, attributes, CSS sizes and colours, image data and scripts are left alone, so `width: 40mm` and `#0F766E` keep working.
- E-mail addresses and links keep their digits: `info@biz2tech.com` and `https://example.com/i/1024` stay as they are, and so do HTML entities.
- Headers, footers and the watermark follow the setting too. With mPDF, page numbers are Arabic digits as well; Chromium and Gotenberg print page numbers in Latin digits.
- In Word files the digits follow the setting as well.
- `->numerals()` also takes `arab`, `ar`, `eastern` or `hindi` for Arabic digits, and `latn`, `en` or `western` for Latin digits. Anything else throws `Unknown numerals style [roman]. Use "latin" or "arabic".`
- With `arabic`, Latin and Persian digits (۱۲۳) in your data are both converted, so mixed data comes out in one style. With `latin`, the text is left as it is: Arabic digits already in your data stay Arabic.

## Mixed Arabic and English {#mixed-text}

In right-to-left text, a value made of numbers split by spaces or dashes comes out in the wrong order: a phone number `+20 100 000 0000` shows as `0000 000 100 20+`, a tax number `123-456-789` as `789-456-123`, and a date `2026-10-08` as `08-10-2026`. Keep such values in their own order with `$doc->ltr()` in a Blade view:

```blade
<x-doc::layout :doc="$doc" title="عقد تقديم خدمات">
    <p>الهاتف: {{ $doc->ltr($customer['phone']) }}</p>
    <p>الرقم الضريبي: {{ $doc->ltr($customer['tax_number']) }}</p>
    <p>تاريخ البدء: {{ $doc->ltr('2026-10-08') }}</p>
</x-doc::layout>
```

`$doc->ltr()` escapes the value and wraps it in `<bdo dir="ltr">`. Use it for phone numbers, tax and commercial registration numbers, and dates written with dashes. Values that start with a Latin letter (`INV-2026-1024`, an IBAN, an e-mail address), dates with slashes (`2026/10/08`) and plain numbers (`1,250.00`) keep their order without it.

In a document built in code, give the text the `ltr` style:

```php
Doc::make()
    ->paragraph(['الهاتف: ', ['text' => '+20 100 000 0000', 'ltr' => true]])
    ->paragraph('123-456-789', ['ltr' => true])
    ->locale('ar')
    ->pdf();
```

Negative numbers are handled for you: `$doc->number(-2.3)` and numbers in builder text keep the minus sign in front (`-2.30`, not `2.30-`). The bundled templates already wrap their codes, phone numbers and tax numbers, and in a `word.docx` template every value without Arabic letters keeps its order by itself (see [Word files](/guide/word)). More helpers for views are listed in [Template helpers](/reference/template-helpers), and builder styles in [Building in code](/guide/builder).

To find out which way a piece of text runs, `Arabic::direction()` looks at its first letter, as `dir="auto"` does in a browser:

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::direction('فاتورة رقم 15');    // "rtl"
Arabic::direction('Invoice فاتورة');   // "ltr"
```

## Amounts in words (tafqeet) {#tafqeet}

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::tafqeet(1250.5, 'EGP');              // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::tafqeet(3.03, 'SAR', only: true);    // فقط ثلاثة ريالات وثلاث هللات لا غير
Arabic::tafqeet(15750.5, 'QAR', only: true); // فقط خمسة عشر ألفاً وسبعمائة وخمسون ريالاً وخمسون درهماً لا غير
Arabic::tafqeet(2000000, 'USD');             // مليونا دولار
Arabic::tafqeet(123456);                     // مائة وثلاثة وعشرون ألفاً وأربعمائة وستة وخمسون
```

`Arabic::tafqeet($amount, $currency = null, $only = false)`:

- With a currency, the amount is read as money, with the main and fractional units in the right grammatical form (ريالان، ثلاثة ريالات، أحد عشر ريالاً) and gender (ثلاث هللات).
- `only: true` wraps it as `فقط ... لا غير`, the usual form on invoices, cheques and receipts.
- Without a currency, the number is read as a number, and decimals after `فاصلة`: `Arabic::tafqeet(1.05)` is `واحد فاصلة صفر خمسة`.
- The amount can be a number or a string, with `,` thousands separators or Arabic digits: `'1,250.50'` and `'١٬٢٥٠٫٥٠'` both work.
- Negative amounts start with `سالب`: `Arabic::tafqeet(-250, 'EGP')` is `سالب مائتان وخمسون جنيهاً`, and `-0.5` is `سالب خمسون قرشاً`.
- Amounts are rounded to the currency's decimals, and read up to 999 trillion.

Currencies included:

| Code | Main unit | Fraction | Decimals |
| --- | --- | --- | --- |
| `EGP` | جنيه | قرش | 2 |
| `SAR` | ريال | هللة | 2 |
| `AED` | درهم | فلس | 2 |
| `QAR` | ريال | درهم | 2 |
| `KWD` | دينار | فلس | 3 |
| `USD` | دولار | سنت | 2 |
| `EUR` | يورو | سنت | 2 |

Three-decimal currencies read their fraction in thousandths: `Arabic::tafqeet(1.125, 'KWD')` is `دينار واحد ومائة وخمسة وعشرون فلساً`.

An unknown code throws `Unknown currency [GBP]. Register it with Tafqeet::registerCurrency().`

### Add or change a currency {#currencies}

Add currencies under `currencies` in `config/easy-pdf-word.php`. Each unit lists its four Arabic forms: singular, dual, plural (for 3 to 10) and the accusative singular used from 11 to 99, plus its gender (`m` or `f`). `subunits` is how many fractional units make one main unit:

```php
'currencies' => [
    'OMR' => [
        'main' => ['forms' => ['ريال عماني', 'ريالان عمانيان', 'ريالات عمانية', 'ريالاً عمانياً'], 'gender' => 'm'],
        'sub' => ['forms' => ['بيسة', 'بيستان', 'بيسات', 'بيسة'], 'gender' => 'f'],
        'subunits' => 1000,
    ],
    'JOD' => [
        'main' => ['forms' => ['دينار', 'ديناران', 'دنانير', 'ديناراً'], 'gender' => 'm'],
        'sub' => ['forms' => ['فلس', 'فلسان', 'فلوس', 'فلساً'], 'gender' => 'm'],
        'subunits' => 1000,
    ],
],
```

```php
Arabic::tafqeet(12.5, 'OMR');   // اثنا عشر ريالاً عمانياً وخمسمائة بيسة
```

`subunits` defaults to `100` and `gender` to `m`. A code that is already included, such as `SAR`, is replaced by your definition. You can also register a currency in code with `BiztechEG\EasyPdfWord\Arabic\Tafqeet::registerCurrency('OMR', [...])`.

In templates, `$doc->tafqeet()` also handles currencies that are not registered, by reading the number and adding the code: `فقط مائة وخمسون GBP و25/100 لا غير`. See [Template helpers](/reference/template-helpers).

## Hijri dates {#hijri}

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::hijri('2026-10-08');                       // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::hijri('2026-10-08', numerals: 'latin');    // 27 ربيع الآخر 1448 هـ
Arabic::hijri('2026-10-08', 'EEEE d MMMM y');      // الخميس ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::hijri('2026-10-08', 'd/M/y', 'latin');     // 27/4/1448 هـ
Arabic::hijri();                                   // today
```

`Arabic::hijri($date = null, $pattern = 'd MMMM y', $numerals = 'arabic')`:

- Dates follow the Umm al-Qura calendar, the one used in Saudi Arabia.
- `$date` is a `Carbon` or `DateTime`, a date string (`'2026-10-08'`, `'2026-10-08T10:30:00Z'`) or a timestamp. The date's own time zone is used.
- `$pattern` is an ICU date pattern: `d` day, `M` month number, `MMMM` month name, `y` year, `EEEE` weekday name.
- `هـ` is added at the end.

For the year, month and day as numbers, use the `Hijri` class:

```php
use BiztechEG\EasyPdfWord\Arabic\Hijri;

Hijri::parts('2026-10-08');   // ['year' => 1448, 'month' => 4, 'day' => 27]
Hijri::format('2026-10-08', 'd MMMM y', 'latin', suffix: false);   // 27 ربيع الآخر 1448
```

Hijri dates need the PHP `intl` extension. Without it, `Arabic::hijri()` throws `Hijri dates need the PHP intl extension.`, and the bundled templates leave the Hijri date out instead of failing.

## Arabic digits anywhere {#arabic-numerals}

`Arabic::numerals()` converts the digits of any value:

```php
Arabic::numerals('2026');                // ٢٠٢٦
Arabic::numerals('12,500.75');           // ١٢٬٥٠٠٫٧٥
Arabic::numerals('١٢٣', 'latin');        // 123
```

It uses the Arabic decimal and thousands separators (٫ ٬). To convert without them, use `BiztechEG\EasyPdfWord\Arabic\Numerals::toArabic('12,500.75', separators: false)`, which gives `١٢,٥٠٠.٧٥`.

## Global helpers and Blade directives {#helpers}

The same tools are available as global functions:

```php
tafqeet(1250.5, 'EGP');                   // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
tafqeet(1250.5, 'EGP', true);             // فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير
hijri_date('2026-10-08');                 // ٢٧ ربيع الآخر ١٤٤٨ هـ
hijri_date('2026-10-08', numerals: 'latin');
arabic_numerals(2026);                    // ٢٠٢٦
```

and as Blade directives in any view, including your emails and web pages:

```blade
<p>المبلغ: @tafqeet($payment->amount, 'SAR', true)</p>
<p>التاريخ: @hijri($payment->paid_at)</p>
```

The directives escape their output. Each function is only defined when your app has no function of the same name.

## Arabic separators and fonts {#separators}

With Arabic digits, numbers in a document can use the Arabic decimal separator `٫` and thousands separator `٬` (١٢٬٥٠٠٫٧٥) or keep `.` and `,` (١٢,٥٠٠.٧٥). It depends on the font, because not every font draws them clearly:

| Font | Separators with Arabic digits |
| --- | --- |
| Noto Naskh Arabic (`naskh`) | ٫ and ٬ |
| Cairo (`cairo`, the default) | `.` and `,`: Cairo draws both Arabic separators like commas |
| Tajawal (`tajawal`) | `.` and `,`: Tajawal has no Arabic separators |
| Your own fonts | `.` and `,`, unless you register them with `'arabic_separators' => true` |
| Word files | `.` and `,`, whatever the font, since Word uses the reader's fonts |

```php
Doc::template('invoice', $data)->locale('ar')->numerals('arabic')->font('naskh')->pdf();   // ١٢٬٥٠٠٫٧٥
Doc::template('invoice', $data)->locale('ar')->numerals('arabic')->pdf();                  // ١٢,٥٠٠.٧٥
```

Registering your own fonts is covered in [Fonts](/guide/fonts#custom-fonts).
