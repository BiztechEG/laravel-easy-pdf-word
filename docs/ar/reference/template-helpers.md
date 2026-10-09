# أدوات القوالب

كل ما يمكنك استخدامه أثناء كتابة قالب أو view لمستند: الكائن `$doc`، ومكونات Blade وتوجيهاته، والأدوات العربية وأدوات هيئة الزكاة والضريبة والجمارك، ومفاتيح `template.php`، ودالة التخطيط، وكل متغيرات `word.docx`. النتائج المعروضة في هذه الصفحة أنتجتها المكتبة نفسها.

## ما يحصل عليه كل ملف {#overview}

| الملف | ما يمكنه استخدامه |
| --- | --- |
| `pdf.blade.php` و`header.blade.php` و`footer.blade.php` وملفات view المستخدمة مع `Doc::view()` | `$doc` (كائن [`DocContext`](#doc-context))، وكل مفتاح أعلى في البيانات كمتغير (`$invoice` و`$items` ...)، و[المكونات](#layout-component) و[التوجيهات](#blade-directives) و[الدوال العامة](#global-helpers) |
| `layout.php` و`word.php` | دالة تستقبل كائن البناء والبيانات و`$doc`: راجع [layout.php و word.php](#layout-php) |
| `word.docx` | [`${placeholders}`](#word-placeholders) |
| `template.php` | [مفاتيح الملف](#template-php) |
| `lang/ar.php` و`lang/en.php` ... | [النصوص](#lang-files) التي تقرؤها بـ `$doc->t()` |

في القوالب، البيانات هي ما مررته مدمجًا فوق `defaults` الخاصة بالقالب وبعد مروره على دالة `prepare`، فتجد فيها القيم المحسوبة مثل الإجماليات. ومفتاح بيانات اسمه `doc` لا يحل أبدًا محل `$doc`.

## الكائن $doc {#doc-context}

`$doc` كائن من الفئة `BiztechEG\EasyPdfWord\Support\DocContext`. هو الكائن نفسه في Blade (`$doc`) وفي `layout.php` و`word.php` (المعامل الثالث)، فيعمل القالب الواحد بالعربية والإنجليزية وبصيغتي PDF و Word.

### الخصائص {#context-properties}

كلها عامة وللقراءة فقط:

| الخاصية | النوع | القيمة |
| --- | --- | --- |
| `locale` | `string` | اللغة كما مُررت: `ar` أو `ar_EG` أو `en` ... |
| `direction` | `string` | `rtl` أو `ltr` |
| `font` | `string` | خط المستند، مثل `cairo` |
| `numerals` | `string` | `latin` أو `arabic` |
| `theme` | `array` | الهوية النهائية: الإعدادات ثم `template.php` ثم `->theme()` |
| `engine` | `string` | فئة محرك PDF، مثل `BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver`، وتكون فارغة في ملفات Word ورأس الصفحة وتذييلها |
| `fontCss` | `string` | قواعد `@font-face` للمحركات التي تحمّل الخطوط من CSS، وإلا فارغة |

### الأرقام في المخرجات {#digits}

تعيد الأدوات أرقامًا لاتينية. وحين يستخدم المستند `->numerals('arabic')` تحوّل المكتبة كل الأرقام في النص النهائي بعد ذلك، فلا تحتاج أبدًا إلى تحويل الأرقام بنفسك. تعتمد الفواصل على الخط: Cairo و Tajawal يُبقيان `,` و`.`، أما Naskh فيرسم الفاصلتين العربيتين `٬` و`٫`. وتُبقي ملفات Word دائمًا `,` و`.`.

نتائج حقيقية في مستند عربي:

| في القالب | تعيد | المطبوع بالأرقام العربية (Cairo) | المطبوع بالأرقام العربية (Naskh) |
| --- | --- | --- | --- |
| `$doc->number(1250.5)` | `1,250.50` | `١,٢٥٠.٥٠` | `١٬٢٥٠٫٥٠` |
| `$doc->number(-2.3)` | `-2.30` داخل `bdo` من اليسار إلى اليمين | `-٢.٣٠` | `-٢٫٣٠` |
| `$doc->money(1250.5, $doc->currency('EGP'))` | `1,250.50 ج.م` | `١,٢٥٠.٥٠ ج.م` | `١٬٢٥٠٫٥٠ ج.م` |
| `$doc->rate(14)` | `14` | `١٤` | `١٤` |
| `$doc->hijri('2026-10-08')` | `27 ربيع الآخر 1448 هـ` | `٢٧ ربيع الآخر ١٤٤٨ هـ` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `$doc->ltr('+20 100 000 0000')` | `+20 100 000 0000` داخل `bdo` من اليسار إلى اليمين | `+٢٠ ١٠٠ ٠٠٠ ٠٠٠٠` | `+٢٠ ١٠٠ ٠٠٠ ٠٠٠٠` |
| `$doc->text($date)` (تاريخ Carbon) | `2026/10/08` | `٢٠٢٦/١٠/٠٨` | `٢٠٢٦/١٠/٠٨` |

تحتفظ عناوين البريد الإلكتروني والروابط بأرقامها اللاتينية.

### isRtl() {#context-is-rtl}

```php
$doc->isRtl(): bool
```

تعيد `true` في المستندات من اليمين إلى اليسار.

### start() / end() {#context-start-end}

```php
$doc->start(): string
$doc->end(): string
```

الجهة الفعلية للبداية والنهاية: `start()` تعيد `right` و`end()` تعيد `left` في المستندات العربية، والعكس في الإنجليزية. استخدمهما في CSS ليناسب القالب الواحد الاتجاهين.

```blade
<td style="text-align: {{ $doc->end() }};">{{ $doc->number($totals['total']) }}</td>
```

### theme() {#context-theme}

```php
$doc->theme(string $key, mixed $default = null): mixed
```

قيمة من الهوية بمفتاح بالنقاط. القيم `primary` و`text` و`muted` و`border` ألوان CSS صالحة دائمًا.

```blade
<h1 style="color: {{ $doc->theme('primary') }};">{{ $doc->theme('company.name') }}</h1>
{{-- شركة بيزتك --}}
```

### t() {#context-t}

```php
$doc->t(string $key, array $replace = []): string
```

نص من ملف `lang/{language}.php` الخاص بالقالب (والمفاتيح بالنقاط للمصفوفات المتداخلة)، ثم من `lang/en.php`، ثم المفتاح نفسه. تُستبدل المتغيرات `:name` من `$replace`. النصوص موجودة في القوالب فقط: في view تستخدمه مع `Doc::view()` تعيد `t()` المفتاح كما هو.

```php
// lang/ar.php: 'page' => 'صفحة :current من :total'
$doc->t('page', ['current' => 3, 'total' => 5]);   // صفحة 3 من 5
// lang/en.php: 'page' => 'Page :current of :total'  → Page 3 of 5
```

الأسماء التي يبدأ أحدها باسم آخر تعمل بأي ترتيب: في `'صفحة :page من :pages'` يعطي `$doc->t('page', ['page' => 3, 'pages' => 5])` النتيجة `صفحة 3 من 5`، لأن الاسم الأطول يُستبدل أولًا.

### translations() {#context-translations}

```php
$doc->translations(): array
```

كل نصوص لغة المستند، وتكمل الإنجليزية ما ينقص منها.

### ltr() {#context-ltr}

```php
$doc->ltr(int|float|string|null $value): HtmlString
```

يحافظ على ترتيب قيمة تُكتب من اليسار إلى اليمين (هاتف أو رقم ضريبي أو كود أو بريد) داخل النص العربي. القيمة تُرمَّز قبل طباعتها.

```blade
{{ $doc->ltr('+20 100 000 0000') }}
{{-- <bdo dir="ltr">+20 100 000 0000</bdo> --}}
```

### text() {#context-text}

```php
$doc->text(mixed $value): string
```

يحوّل أي قيمة إلى نص للعرض: `null` و`false` يعطيان `''`، و`true` يعطي `✓`، والتواريخ تُكتب `Y/m/d` (`2026/10/08`)، والـ enum المدعوم بقيمة يعطي قيمته، وغيره يعطي اسمه، والمصفوفات والكائنات تُكتب JSON.

### number() {#context-number}

```php
$doc->number(int|float|string|null $value, int $decimals = 2): HtmlString
```

رقم بفواصل الآلاف وعدد `$decimals` من المنازل العشرية (من 0 إلى 10). يقرأ النصوص مثل `'1,250.50'` و`'١٬٢٥٠٫٥'` قراءة صحيحة. الرقم السالب يوضع داخل `<bdo dir="ltr">` لتبقى علامة السالب في مكانها داخل النص العربي. تعيد HTML، فاطبعها بالأقواس المزدوجة. وفي `layout.php` و`word.php` استخدم [`numberText()`](#context-number-text) بدلًا منها.

| الاستدعاء | تعيد |
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

التنسيق نفسه كنص عادي، لكتل البناء وملفات Word: `numberText(-2.3)` تعطي `-2.30`، و`numberText(1234.5678, 3)` تعطي `1,234.568`.

### rate() {#context-rate}

```php
$doc->rate(int|float|string|null $value): string
```

نسبة أو معدل بالمنازل العشرية اللازمة فقط: `rate(14)` تعطي `14`، و`rate(2.5)` تعطي `2.5`، و`rate('0.750')` تعطي `0.75`، و`rate('1,500')` تعطي `1500`.

### money() {#context-money}

```php
$doc->money(int|float|string|null $value, ?string $currency = null, int $decimals = 2): HtmlString
```

ناتج `number()` تليه مسافة ثم نص العملة (بعد ترميزه).

| الاستدعاء | مستند عربي | مستند إنجليزي |
| --- | --- | --- |
| `$doc->money(1250.5)` | `1,250.50` | `1,250.50` |
| `$doc->money(1250.5, 'EGP')` | `1,250.50 EGP` | `1,250.50 EGP` |
| `$doc->money(1250.5, $doc->currency('EGP'))` | `1,250.50 ج.م` | `1,250.50 EGP` |
| `$doc->money(1.125, 'KWD', $doc->decimals('KWD'))` | `1.125 KWD` | `1.125 KWD` |

### tafqeet() {#context-tafqeet}

```php
$doc->tafqeet(int|float|string $amount, string $currency, bool $only = true): string
```

التفقيط (المبلغ كتابةً) بالعربية مهما كانت لغة المستند، محاطًا بعبارة `فقط ... لا غير` ما لم يكن `$only` يساوي `false`. العملات التي يعرفها التفقيط (EGP و SAR و AED و QAR و KWD و USD و EUR وما تضيفه في الإعداد `currencies`) تُقرأ بوحداتها، وأي عملة أخرى يُقرأ مبلغها رقمًا يليه رمز العملة والكسر.

| الاستدعاء | تعيد |
| --- | --- |
| `$doc->tafqeet(1250.5, 'EGP')` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` |
| `$doc->tafqeet(1250.5, 'EGP', false)` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `$doc->tafqeet(1150, 'SAR')` | `فقط ألف ومائة وخمسون ريالاً لا غير` |
| `$doc->tafqeet(150.25, 'GBP')` | `فقط مائة وخمسون GBP و25/100 لا غير` |

### inWords() {#context-in-words}

```php
$doc->inWords(int|float|string $amount, string $currency, bool $only = true): string
```

المبلغ كتابةً بلغة المستند: التفقيط العربي في المستندات العربية، والتهجئة من امتداد `intl` في اللغات الأخرى. تعيد `''` إن غاب `ext-intl` في مستند غير عربي.

| الاستدعاء | مستند عربي | مستند إنجليزي |
| --- | --- | --- |
| `$doc->inWords(1250.5, 'EGP')` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` | `one thousand two hundred fifty EGP and 50/100 only` |
| `$doc->inWords(1250.5, 'EGP', false)` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` | `one thousand two hundred fifty EGP and 50/100` |
| `$doc->inWords(1.125, 'KWD')` | `فقط دينار واحد ومائة وخمسة وعشرون فلساً لا غير` | `one KWD and 125/1000 only` |

### decimals() {#context-decimals}

```php
$doc->decimals(?string $currency): int
```

عدد المنازل العشرية لمبالغ العملة: `3` للدينار الكويتي والبحريني والأردني والعراقي والليبي والتونسي والريال العماني (KWD و BHD و JOD و IQD و LYD و TND و OMR)، و`0` لـ JPY و KRW، و`2` لباقي العملات ولـ `null`.

### currency() {#context-currency}

```php
$doc->currency(string $code): string
```

الاختصار المطبوع للعملة: نص `currencies.CODE` من ملف `lang` الخاص بالقالب إن وُجد، وإلا ففي المستندات من اليمين إلى اليسار اختصار عربي مدمج (EGP `ج.م` و SAR `ر.س` و AED `د.إ` و KWD `د.ك` و QAR `ر.ق` و BHD `د.ب` و OMR `ر.ع` و JOD `د.أ` و USD `دولار` و EUR `يورو`)، وإلا فرمز العملة بأحرف كبيرة.

| الاستدعاء | مستند عربي | مستند إنجليزي |
| --- | --- | --- |
| `$doc->currency('EGP')` | `ج.م` | `EGP` |
| `$doc->currency('sar')` | `ر.س` | `SAR` |
| `$doc->currency('GBP')` | `GBP` | `GBP` |

### hasHijri() / hijri() {#context-hijri}

```php
$doc->hasHijri(): bool
$doc->hijri(mixed $date = null, string $pattern = 'd MMMM y'): string
```

تعطي `hijri()` التاريخ الهجري (تقويم أم القرى) لكائن Carbon أو `DateTime` أو لنص تاريخ (واليوم الحالي إن كان `null`)، بأسماء الشهور العربية وفي آخره ` هـ`. المعامل `$pattern` نمط تاريخ بصيغة ICU. تعيد أرقامًا لاتينية يحوّلها المستند إن استخدم الأرقام العربية. يحتاج التاريخ الهجري إلى `ext-intl`، وبدونه ترمي `hijri()` الاستثناء `RuntimeException`، فاحمِ التواريخ الاختيارية بـ `hasHijri()`.

| الاستدعاء | تعيد |
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

صورة جاهزة للاستخدام في `<img src>`: الملف المحلي يتحول إلى data URI، والرابط المسموح يُعاد كما هو، وdata URI لصورة يبقى كما هو. تعيد `null` لكل ما لا يجوز أو لا يمكن استخدامه: ملف خارج مجلدات `images.paths`، أو ملف ليس صورة، أو رابط لا يسمح `images.remote` بنطاقه، أو SVG يحمّل ملفات أخرى، أو أي بروتوكول آخر. راجع [الصور](/ar/guide/images).

```blade
@if ($logo = $doc->image($doc->theme('logo')))
    <img src="{{ $logo }}" style="height: 18mm;">
@endif
```

كتل البناء (`image()` و`image` في الخلية) وصور `word.docx` تمر بالفحص نفسه، فمرر إليها المسار كما هو.

### usesCssFonts() {#context-uses-css-fonts}

```php
$doc->usesCssFonts(): bool
```

تعيد `true` حين يحمّل المحرك الخطوط من CSS (Chromium و Gotenberg)، وعندها يحمل `$doc->fontCss` قواعد `@font-face`. يطبعها `x-doc::layout`، والـ view الذي لا يستخدم هذا المكون تُضاف إليه قبل `</head>`، فنادرًا ما تحتاج إليها.

### toFloat() {#context-to-float}

```php
DocContext::toFloat(int|float|string|null $value): float
```

دالة ثابتة تقرأ الأرقام المنسقة: `'1,250.50'` و`'١٬٢٥٠٫٥٠'` كلاهما يعطي `1250.5`.

## مكون التخطيط {#layout-component}

يعطي `x-doc::layout` ملف view المستند هيكل HTML كاملًا: الخاصيتان `lang` و`dir` على `<html>`، وخط المستند واتجاهه ولون النص على `<body>`، وتنسيقات أساسية تعمل على كل المحركات.

| الخاصية أو الـ slot | إلزامي | القيمة |
| --- | --- | --- |
| `:doc` | نعم | مرر `$doc` |
| `title` | لا | قيمة `<title>` في HTML |
| الـ slot المسمى `styles` | لا | كتلة `<style>` الخاصة بك، توضع داخل `<head>` بعد التنسيقات الأساسية |
| المحتوى | | جسم الصفحة |

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

التنسيقات الأساسية: `body` بحجم 10.5pt وارتفاع سطر 1.5، والجداول بعرض كامل وحدود مدمجة، والخلايا محاذاة للأعلى، وهذه الفئات:

| الفئة | الأثر |
| --- | --- |
| `text-start` | محاذاة لجهة البداية (يمين في العربية) |
| `text-end` | محاذاة لجهة النهاية (يسار في العربية) |
| `text-center` | توسيط |
| `muted` | لون `muted` من الهوية |
| `ltr` | نص من اليسار إلى اليمين داخل صفحة من اليمين إلى اليسار |
| `nowrap` | بدون كسر للسطر |

### مكون QR {#qr-component}

يطبع `x-doc::qr` صورة رمز QR.

| الخاصية | الافتراضي | القيمة |
| --- | --- | --- |
| `value` | إلزامية | النص المراد ترميزه |
| `size` | `28mm` | العرض والارتفاع |
| غيرها | | تُمرر إلى وسم `<img>` |

```blade
<x-doc::qr :value="$verifyUrl" size="25mm" />
{{-- <img src="data:image/png;base64,..." alt="QR" style="width: 25mm; height: 25mm;"> --}}
```

## توجيهات Blade {#blade-directives}

| التوجيه | يكافئ |
| --- | --- |
| `@tafqeet(1250.5, 'EGP')` | `Arabic::tafqeet(1250.5, 'EGP')`: `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `@hijri('2026-10-08')` | `Arabic::hijri('2026-10-08')`: `٢٧ ربيع الآخر ١٤٤٨ هـ` |

كلاهما يطبع نصًا مرمَّزًا ويقبل معاملات دوال `Arabic` نفسها. وبخلاف `$doc->tafqeet()` لا يضيف `@tafqeet` عبارة `فقط ... لا غير` إلا إن مررت `only: true`، ويطبع `@hijri` أرقامًا عربية حتى في مستند بالأرقام اللاتينية. داخل قوالب المستندات فضّل دوال `$doc` لأنها تتبع إعدادات المستند.

## الدوال العامة {#global-helpers}

تُعرَّف ما لم يكن في تطبيقك دالة بالاسم نفسه.

```php
tafqeet(int|float|string $amount, ?string $currency = null, bool $only = false): string
hijri_date(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = 'arabic'): string
arabic_numerals(string|int|float $value): string
```

| الاستدعاء | تعيد |
| --- | --- |
| `tafqeet(1250)` | `ألف ومائتان وخمسون` |
| `tafqeet(1250.5, 'EGP')` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `tafqeet(1250.5, 'EGP', true)` | `فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير` |
| `hijri_date('2026-10-08')` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `hijri_date('2026-10-08', 'd MMMM y', 'latin')` | `27 ربيع الآخر 1448 هـ` |
| `arabic_numerals('INV-2026-1024')` | `INV-٢٠٢٦-١٠٢٤` |
| `arabic_numerals(1250.5)` | `١٢٥٠٫٥` |

تعمل في أي مكان من تطبيقك: صفحة Blade، أو رد API، أو رسالة SMS.

## Arabic {#arabic}

الفئة `BiztechEG\EasyPdfWord\Arabic\Arabic` مدخل واحد للأدوات العربية.

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

الفئة `BiztechEG\EasyPdfWord\Arabic\Tafqeet` تكتب الأرقام والمبالغ بالحروف العربية بصيغ المعدود الصحيحة (مائتا جنيه، ثلاثة ريالات، أحد عشر ديناراً).

```php
Tafqeet::words(int|float|string $number, string $gender = Tafqeet::MASCULINE): string
Tafqeet::amount(int|float|string $amount, string $currency = 'EGP', bool $only = false): string
Tafqeet::registerCurrency(string $code, array $definition): void
Tafqeet::currencies(): array
Tafqeet::decimals(string $currency): ?int
```

الثوابت: `Tafqeet::MASCULINE` (`'m'`) و`Tafqeet::FEMININE` (`'f'`) و`Tafqeet::MAX` (999,999,999,999,999، أكبر رقم يُقرأ). يمكن أن تحتوي النصوص على فواصل الآلاف وأرقام عربية (`'١٢٣٫٤٥'`).

| الاستدعاء | تعيد |
| --- | --- |
| `Tafqeet::words(1250)` | `ألف ومائتان وخمسون` |
| `Tafqeet::words(1.05)` | `واحد فاصلة صفر خمسة` |
| `Tafqeet::words(3, Tafqeet::FEMININE)` | `ثلاث` |
| `Tafqeet::amount(1250.5, 'EGP')` | `ألف ومائتان وخمسون جنيهاً وخمسون قرشاً` |
| `Tafqeet::amount(3, 'SAR', true)` | `فقط ثلاثة ريالات لا غير` |
| `Tafqeet::amount(1234.567, 'KWD')` | `ألف ومائتان وأربعة وثلاثون ديناراً وخمسمائة وسبعة وستون فلساً` |
| `Tafqeet::currencies()` | `['EGP', 'SAR', 'AED', 'QAR', 'KWD', 'USD', 'EUR']` |
| `Tafqeet::decimals('KWD')` | `3` (و`null` لعملة غير معروفة) |

ترمي `amount()` الاستثناء `InvalidArgumentException` لعملة غير معروفة، وترميه الدالتان لنص ليس رقمًا أو لرقم أكبر من `MAX`.

### إضافة عملة {#tafqeet-currencies}

لكل وحدة أربع صيغ عربية: المفرد، والمثنى، والجمع (من 3 إلى 10)، والمنصوب (من 11 إلى 99). القيمة `gender` إما `'m'` (الافتراضي، مذكر) أو `'f'` (مؤنث)، و`subunits` عدد الوحدات الصغرى في الوحدة الكبرى (الافتراضي 100). أضف العملات للتطبيق كله في `config/easy-pdf-word.php`، فتُسجَّل عند تشغيل التطبيق:

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

يفعل `Tafqeet::registerCurrency('JOD', [...])` الشيء نفسه أثناء التشغيل. والوحدة التي لا تحمل أربع صيغ ترمي `InvalidArgumentException`.

## Hijri {#hijri}

الفئة `BiztechEG\EasyPdfWord\Arabic\Hijri` تنسّق التاريخ الهجري بتقويم أم القرى. تحتاج إلى `ext-intl` (وبدونه `RuntimeException`).

```php
Hijri::format(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = Numerals::ARABIC, bool $suffix = true): string
Hijri::parts(DateTimeInterface|string|int|null $date = null): array
```

| الاستدعاء | تعيد |
| --- | --- |
| `Hijri::format('2026-10-08')` | `٢٧ ربيع الآخر ١٤٤٨ هـ` |
| `Hijri::format('2026-10-08', numerals: 'latin')` | `27 ربيع الآخر 1448 هـ` |
| `Hijri::format('2026-10-08', suffix: false)` | `٢٧ ربيع الآخر ١٤٤٨` |
| `Hijri::parts('2026-10-08')` | `['year' => 1448, 'month' => 4, 'day' => 27]` |

حروف مفيدة في النمط: `d` و`dd` لليوم، و`M` و`MM` لرقم الشهر، و`MMMM` لاسم الشهر، و`y` للسنة، و`EEEE` لاسم اليوم.

## Numerals {#numerals}

الفئة `BiztechEG\EasyPdfWord\Arabic\Numerals` تحوّل الأرقام. الثوابت: `Numerals::LATIN` (`'latin'`) و`Numerals::ARABIC` (`'arabic'`).

```php
Numerals::toArabic(string|int|float $value, bool $separators = true): string
Numerals::toLatin(string|int|float $value): string
Numerals::convert(string|int|float $value, string $style, bool $separators = true): string
Numerals::convertHtml(string $html, string $style, bool $separators = true): string
Numerals::normalizeStyle(string $style): string
```

| الاستدعاء | تعيد |
| --- | --- |
| `Numerals::toArabic('12,500.75')` | `١٢٬٥٠٠٫٧٥` |
| `Numerals::toArabic('12,500.75', false)` | `١٢,٥٠٠.٧٥` |
| `Numerals::toArabic('info@biz2tech.com 2026')` | `info@biz2tech.com ٢٠٢٦` |
| `Numerals::toLatin('١٢٬٥٠٠٫٧٥')` | `12,500.75` (وتقرأ الأرقام الفارسية أيضًا) |
| `Numerals::convert('٢٠٢٦', 'latin')` | `2026` |
| `Numerals::convertHtml('<p style="width: 50mm">المجموع 1,250.50</p>', 'arabic')` | `<p style="width: 50mm">المجموع ١٬٢٥٠٫٥٠</p>` |
| `Numerals::normalizeStyle('hindi')` | `arabic` |

تغيّر `convertHtml()` النص فقط، وتترك الوسوم والخصائص و`<style>` و`<script>` والتعليقات والكيانات كما هي. وتقبل `normalizeStyle()` الأسماء نفسها التي تقبلها `->numerals()` وترمي `InvalidArgumentException` لغيرها.

## Direction {#direction}

الفئة `BiztechEG\EasyPdfWord\Arabic\Direction`. الثوابت: `Direction::RTL` (`'rtl'`) و`Direction::LTR` (`'ltr'`).

```php
Direction::forLocale(?string $locale): string
Direction::isRtlLocale(?string $locale): bool
Direction::ofText(string $text, string $default = Direction::LTR): string
```

`forLocale('ar_EG')` تعطي `rtl`، و`forLocale('en')` تعطي `ltr`. اللغات التي تُكتب من اليمين إلى اليسار: `ar` و`arc` و`ckb` و`dv` و`fa` و`he` و`ku` و`ps` و`sd` و`ug` و`ur` و`yi`. وتتبع `ofText()` أول حرف في النص مثل `dir="auto"`: `ofText('123 فاتورة')` تعطي `rtl`.

## ZatcaQr {#zatca-qr}

الفئة `BiztechEG\EasyPdfWord\Zatca\ZatcaQr` تبني بيانات رمز QR للفواتير الضريبية المبسطة في السعودية (المرحلة الأولى لهيئة الزكاة والضريبة والجمارك): خمسة حقول TLV بترميز base64. ويبنيه القالبان `invoice` و`credit-note` تلقائيًا مع `'qr' => 'zatca'`.

```php
ZatcaQr::make(string $sellerName, string $vatNumber, DateTimeInterface|string $timestamp, int|float|string $total, int|float|string $vatTotal): ZatcaQr
$qr->toTlv(): string
$qr->toBase64(): string
$qr->toDataUri(int $scale = 5): string
ZatcaQr::decode(string $base64): array
```

تحمل الخصائص العامة للقراءة فقط `sellerName` و`vatNumber` و`timestamp` و`total` و`vatTotal` القيم كما تُرمَّز: الوقت بتوقيت UTC بصيغة `2026-10-08T14:30:00Z` (والتاريخ بدون وقت يحتفظ بيومه)، والمبالغ بمنزلتين عشريتين (`'1,150.00'` تُقرأ 1150). الحقل الذي يتجاوز 255 بايت يرمي `InvalidArgumentException`.

```php
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

$qr = ZatcaQr::make('شركة بيزتك', '300000000000003', '2026-10-08 14:30:00', 1150, 150);

$qr->toBase64();
// ARPYtNix2YPYqSDYqNmK2LLYqtmDAg8zMDAwMDAwMDAwMDAwMDMDFDIwMjYtMTAtMDhUMTQ6MzA6MDBaBAcxMTUwLjAwBQYxNTAuMDA=

ZatcaQr::decode($qr->toBase64());
// [1 => 'شركة بيزتك', 2 => '300000000000003', 3 => '2026-10-08T14:30:00Z', 4 => '1150.00', 5 => '150.00']
```

يُقرأ الوقت بالمنطقة الزمنية للتطبيق ثم يُحوَّل إلى UTC. في القالب ارسمه بـ `<x-doc::qr :value="$qr->toBase64()" />` أو `<img src="{{ $qr->toDataUri() }}">`، أو بـ `$builder->qr($qr->toBase64(), 30)` في `layout.php`.

## Currency {#currency}

الفئة `BiztechEG\EasyPdfWord\Support\Currency` تعرف عدد المنازل العشرية لكل عملة.

```php
Currency::decimals(?string $code): int
Currency::round(int|float|string|null $amount, ?string $code): float
```

| الاستدعاء | تعيد |
| --- | --- |
| `Currency::decimals('KWD')` | `3` |
| `Currency::decimals('OMR')` | `3` |
| `Currency::decimals('JPY')` | `0` |
| `Currency::decimals('EGP')` | `2` |
| `Currency::round(10.4567, 'KWD')` | `10.457` |
| `Currency::round(10.4567, 'EGP')` | `10.46` |

استخدمها في دالة `prepare` بالقالب ليتطابق تقريب الإجماليات مع المبالغ المطبوعة.

## Qr {#qr}

```php
BiztechEG\EasyPdfWord\Support\Qr::dataUri(string $value, int $scale = 5): string
```

رمز QR لأي نص كـ data URI بصيغة PNG (`data:image/png;base64,...`) تعرضه كل المحركات. يستخدمها `x-doc::qr`.

## template.php {#template-php}

يعيد `template.php` مصفوفة، وكل مفاتيحها اختيارية. المجلد الذي فيه `pdf.blade.php` فقط يعمل مع `Doc::template()`، لكن الأمر `doc:templates` وصفحة المعاينة لا يعرضان إلا المجلدات التي فيها `template.php`.

| المفتاح | النوع | يُستخدم في | الافتراضي |
| --- | --- | --- | --- |
| `title` | `string` | `doc:templates` وصفحة المعاينة وخاصية العنوان في الملف | اسم المجلد |
| `description` | `string` | صفحة المعاينة | `''` |
| `locales` | `array` | اللغات المعروضة في `doc:templates` والمتاحة في صفحة المعاينة | `['ar', 'en']` |
| `paper` | `string` أو `array` | اسم مقاس الورق مثل `'A4'` و`'A5'` و`'A4-L'` (A4 بالعرض)، أو `[width, height]` بالمليمتر | الإعداد `pdf.paper` |
| `orientation` | `string` | `'portrait'` أو `'landscape'` | الإعداد `pdf.orientation` |
| `margins` | `array` | بالمليمتر، من قيمة إلى أربع قيم مثل `->margins()`: `[15, 12]` | الإعداد `pdf.margins` |
| `theme` | `array` | قيم الهوية لهذا القالب، بين الإعدادات و`->theme()` | `[]` |
| `fields` | `array` | قواعد التحقق من البيانات في Laravel | `[]` |
| `defaults` | `array` | بيانات تُستخدم إن لم يمررها المستدعي (دمج عميق) | `[]` |
| `prepare` | `callable` | `fn (array $data, array $theme = []): array`، تضيف القيم المحسوبة بعد التحقق | لا شيء |
| `sample` | `array` أو `callable` | البيانات التجريبية لصفحة المعاينة والأمر `doc:sample` والاختبارات | `[]` |

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

## layout.php و word.php {#layout-php}

يعيد كل من الملفين دالة تضيف [كتل البناء](/ar/reference/api#document-builder):

```php
function (DocumentBuilder $builder, array $data, DocContext $doc): void
```

المعامل الأول كائن `BiztechEG\EasyPdfWord\Builder\DocumentBuilder`، والثاني البيانات بعد تجهيزها، والثالث [`$doc`](#doc-context) نفسه المستخدم في Blade. سمِّ المعامل الأول كما تشاء (`$list` أو `$word` ...).

أي ملف ينتج أي صيغة:

| في المجلد | ملف PDF من | ملف Word من |
| --- | --- | --- |
| `layout.php` | `layout.php` | `layout.php` |
| `pdf.blade.php` و`word.php` | `pdf.blade.php` | `word.php` |
| `pdf.blade.php` و`layout.php` | `pdf.blade.php` | `layout.php` |
| `word.php` بدون `pdf.blade.php` | `word.php` | `word.php` |
| `word.docx` (مع أي مما سبق) | كما سبق | `word.docx` |

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

نص كتل البناء يُرمَّز وليس HTML، لذا استخدم هنا `numberText()` لا `number()`، وتنسيق `ltr` لا `$doc->ltr()`.

## ملفا رأس الصفحة وتذييلها {#header-footer}

يُطبع `header.blade.php` و`footer.blade.php` الموجودان في مجلد القالب على كل صفحة. يحصلان على `$doc` والبيانات مثل `pdf.blade.php`، ويتحول `{page}` و`{pages}` إلى رقم الصفحة وعدد الصفحات. تحل `->header()` و`->footer()` محلهما لمستند واحد. وفي ملفات Word يصبحان نصًا عاديًا مع حقول أرقام الصفحات.

```blade
{{-- header.blade.php --}}
<div style="font-size: 8pt; color: #6B7280;">{{ $sender }} | {{ $doc->ltr($shipment['number']) }}</div>

{{-- footer.blade.php --}}
<div style="text-align: center; font-size: 8pt;">{{ $doc->t('page', ['current' => '{page}', 'total' => '{pages}']) }}</div>
```

مع قائمة التعبئة السابقة يظهر التذييل في المستند الإنجليزي `Page 1 of 1`، وفي المستند العربي مع `->numerals('arabic')` يظهر `صفحة ١ من ١`.

## ملفات النصوص {#lang-files}

يعيد `lang/{language}.php` نصوص لغة واحدة. جزء اللغة من الـ locale يحدد الملف (`ar_EG` يقرأ `ar.php`)، والنصوص الناقصة تؤخذ من `en.php`.

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

المصفوفة `currencies` بعناصر `CODE => label` هي أول ما تقرؤه [`$doc->currency()`](#context-currency).

## متغيرات word.docx {#word-placeholders}

يُملأ ملف `word.docx` المصمم في Word ببيانات القالب بعد تجهيزها. اكتب المتغيرات في المستند بالشكل `${name}`.

| المتغير | القيمة |
| --- | --- |
| `${invoice.number}` و`${buyer.name}` | قيمة من البيانات، والنقاط للمفاتيح المتداخلة |
| `${items.description}` داخل صف جدول | يتكرر الصف لكل عنصر في القائمة `items`، وتعمل أي قائمة مصفوفات |
| `${items.row_number}` | 1 و2 و3 ... في الصف المتكرر |
| `${items.product.code}` | قيمة متداخلة داخل كل صف |
| `${tags}` | قائمة قيم بسيطة مفصولة بـ `، ` |
| `${theme.company.name}` و`${theme.primary}` | قيم الهوية |
| `${theme.logo}` و`${logo}` | صورة إن كانت القيمة مسار صورة أو data URI |
| `${logo:120:60}` | الشيء نفسه بحجم بالبكسل (العرض:الارتفاع)، والعرض 120 إن لم يُحدد مع الحفاظ على النسبة |
| `${t.title}` و`${t.labels.buyer}` | نصوص من `lang/{language}.php` |
| `${doc.hijri_date}` | التاريخ الهجري للقيمة `date` (أو `invoice.date`)، ويحتاج `ext-intl` |
| `${doc.today}` | تاريخ اليوم بصيغة `Y/m/d` |
| `${doc.qr}` | صورة رمز QR للقيمة `qr` |

كيف تُكتب القيم:

- الأرقام العشرية (floats) تأخذ فواصل الآلاف ومنازل عملة المستند العشرية، والعملة من `currency` في المستوى الأعلى أو من `currency` داخل مجموعة مثل `invoice.currency` أو `quote.currency` (`27501.0` تصبح `27,501.00`، والدينار الكويتي بثلاث منازل). الأرقام الصحيحة والنصوص تُكتب كما هي (`25000`).
- `true` تعطي `✓`، و`false` و`null` والقيم المفقودة تعطي نصًا فارغًا. وكائنات التاريخ تُكتب `Y/m/d`.
- مع `->numerals('arabic')` تصبح الأرقام عربية مع بقاء `,` و`.`: `٢٧,٥٠١.٠٠` و`INV-٢٠٢٦-١٠٢٤`.
- القيم تُرمَّز، و`${...}` داخل قيمة يبقى نصًا.
- في المستندات التي تُكتب من اليمين إلى اليسار، تُعلَّم القيمة التي ليس فيها حروف عربية (رقم هاتف أو تاريخ أو رمز) بأنها من اليسار إلى اليمين فيحافظ Word على ترتيبها؛ أما القيم المكونة من أرقام فقط فتبقى كما هي.
- تتبع الصور [قواعد الصور](/ar/guide/images)، وصورة SVG أو ملف غير موجود أو صورة لا يجوز قراءتها تترك المتغير فارغًا.

اضبط اتجاه الفقرات والجداول من اليمين إلى اليسار في Word نفسه. ولمثال كامل خطوة بخطوة راجع [قالب مصمم في Word](/ar/recipes/word-designed-template).
