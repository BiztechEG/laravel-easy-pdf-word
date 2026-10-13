# التركية واللغات الأخرى

تأتي القوالب المرفقة بالعربية والإنجليزية والتركية. توضّح هذه الصفحة ما الذي تغيّره اللغة في المستند، وكيف تضيف لغة من عندك.

## التركية {#turkish}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('invoice', $data)->locale('tr')->pdf();
```

تعمل `tr` و`tr_TR` كلتاهما. للقوالب الاثني عشر كلها تسميات تركية، ويتبع المستند الأعراف التركية:

| | العربية والإنجليزية | التركية |
| --- | --- | --- |
| التسميات | فاتورة ضريبية / Tax Invoice | Fatura, Ara toplam, KDV (%20) |
| التواريخ | 2026/10/08 | 08.10.2026 |
| الأرقام | 1,250.50 | 1.250,50 |
| الشهور والأيام | أكتوبر، الخميس / October, Thursday | Ekim, Perşembe |
| المبلغ كتابةً | فقط ألف ومائتان وخمسون جنيهاً لا غير / one thousand two hundred fifty EGP only | Yalnız bin iki yüz elli Türk Lirası |
| رمز العملة | ج.م / EGP | ‏TL لليرة التركية (TRY)، والرمز الدولي لغيرها |

الخط الافتراضي Cairo فيه كل الحروف التركية، وكذلك Noto Naskh ‏(`naskh`). **أما Tajawal فليس فيه** الحروف ğ وş وİ، فلا تستخدم `->font('tajawal')` مع التركية. ولا يحتوي أيٌّ من الخطوط المرفقة على علامة ₺، لذلك تظهر الليرة TL؛ ولطباعة ₺ [أضف خطاً](/ar/guide/fonts) يحتويها وغيّر الرمز بنفسك (انظر [بالأسفل](#change)).

يحتاج المبلغ كتابةً بالتركية، مثل كل اللغات غير العربية، إلى إضافة PHP ‏`intl`. ومن دونها يُحذف سطر المبلغ كتابةً.

تُطبع بياناتك كما تعطيها. الفاتورة التركية تحتاج أسماء أصناف واسم شركة بالتركية في بياناتك والـ theme.

## اللغات الأخرى {#other-locales}

يقبل `->locale()` أي اسم لغة. واللغة التي لا تعرفها الحزمة (`fr` أو `de` أو `fa`) تحصل على:

- اتجاه اللغة: الفارسية والأردية والعبرية من اليمين لليسار (انظر [اللغات التي تُكتب من اليمين](/ar/guide/arabic#rtl-languages))،
- التسميات الإنجليزية، والتواريخ بصيغة 2026/10/08، والأرقام بصيغة 1,250.50،
- أسماء الشهور والمبلغ كتابةً من `intl` بتلك اللغة، لكن بصياغة إنجليزية ("mille deux cents EUR only").

لتضبط التسميات والتواريخ والأرقام لمثل هذه اللغة، أضفها كما بالأسفل.

## ملفات اللغة {#files}

كل ما تعرفه الحزمة عن اللغة موجود في نوعين من ملفات JSON. واخترنا JSON بدلاً من PHP لكي تقرأ النسخ الأخرى من المكتبة (بلغات برمجة أخرى) الملفات نفسها.

- `vendor/biztecheg/easy-pdf-word/resources/lang/{language}.json`: اللغة نفسها، ويستخدمها كل قالب وكل view من عندك.
- `lang/{language}.json` داخل مجلد كل قالب: تسميات هذا القالب. انظر [التسميات](/ar/guide/custom-templates#lang).

ملف اللغة التركية:

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

| المفتاح | ما هو |
| --- | --- |
| `name` | اسم اللغة بلغتها |
| `direction` | ‏`rtl` أو `ltr` (الاتجاه نفسه يُحدَّد من رمز اللغة؛ والمفتاح يسجّله للنسخ الأخرى من المكتبة) |
| `months`، `days` | أسماء الشهور بدءاً من يناير، وأسماء الأيام بدءاً من الأحد |
| `date_format`، `datetime_format` | كيف يكتب `$doc->date()` التاريخ، بالحروف المبيّنة بالأسفل |
| `decimal_separator`، `thousands_separator` | تستخدمها `$doc->number()` و`$doc->money()` و`$doc->rate()` |
| `list_separator` | بين القيم التي تجمعها `$doc->join()` وفي متغيرات القوائم في `word.docx` |
| `amount_in_words` | كيف تُجمع الكلمات والعملة والكسر. ‏`:words` الرقم بالحروف، و`:currency` اسم العملة، و`:amount` النص حتى الآن، و`:fraction` و`:per` الكسر (50 و100) |
| `currencies` | الرموز المختصرة التي تُطبع بجوار المبالغ |
| `currency_names` | أسماء العملات في المبلغ كتابةً؛ والرمز الدولي للعملة غير المذكورة |

المفتاح الذي تتركه لغةٌ ما يؤخذ من الإنجليزية. أما المبلغ كتابةً بالعربية فيكتبه التفقيط الخاص بالحزمة، لا `amount_in_words`.

تستخدم صيغ التاريخ هذه الحروف، وهي نفسها في ICU وJava و.NET: ‏`yyyy` ‏2026، `yy` ‏26، `MM` ‏09، `M` ‏9، `dd` ‏08، `d` ‏8، `HH` ‏14، `H` ‏9، `mm` ‏05، `ss` ‏07. والنص بين علامتي تنصيص مفردتين يُطبع كما هو: `dd.MM.yyyy 'saat' HH:mm`. أسماء الشهور ليست جزءاً من الصيغة؛ استخدم `$doc->monthName()`.

## إضافة لغة {#add}

لنأخذ الفرنسية مثالاً.

**1. أضف ملف اللغة.** ضع `fr.json` في مجلد من تطبيقك، مثلاً `lang/easy-pdf-word/fr.json`:

```json
{
    "name": "Français",
    "direction": "ltr",
    "months": ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"],
    "days": ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"],
    "date_format": "dd/MM/yyyy",
    "datetime_format": "dd/MM/yyyy HH:mm",
    "decimal_separator": ",",
    "thousands_separator": " ",
    "amount_in_words": {
        "amount": ":words :currency",
        "fraction": ":amount et :fraction/:per",
        "only": "Arrêtée à la somme de :amount"
    },
    "currency_names": { "EUR": "euros", "MAD": "dirhams" }
}
```

(` ` مسافة لا تقسم الرقم على سطرين) ثم عرّف الحزمة بالمجلد في `AppServiceProvider::boot()`:

```php
use BiztechEG\EasyPdfWord\Support\Language;

public function boot(): void
{
    Language::addPath(lang_path('easy-pdf-word'));
}
```

**2. أضف التسميات.** انسخ كل قالب تستخدمه (انظر [استبدال قالب مرفق](/ar/guide/templates#override)) وأضف `lang/fr.json` بجوار `lang/en.json` بالمفاتيح نفسها:

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

التسمية التي تتركها تُطبع بالإنجليزية.

**3. تأكد من الخط.** اللغات اللاتينية تعمل بالخطوط المرفقة. أما الكتابات الأخرى (العبرية، الديفاناغرية، التايلاندية) فتحتاج خطاً فيه حروفها؛ انظر [الخطوط](/ar/guide/fonts).

بعدها يعطيك `->locale('fr')` مستندات فرنسية. وتبقى الأرقام العربية والتاريخ الهجري والتفقيط للعربية وحدها.

## تعديل لغة مرفقة {#change}

الملف الموجود في مجلد أضفته يستبدل المفاتيح التي فيه فقط. لكتابة التواريخ التركية بشرطة مائلة وطباعة ₺ (مع خط يحتويها):

::: code-group

```json [lang/easy-pdf-word/tr.json]
{
    "date_format": "dd/MM/yyyy",
    "currencies": { "TRY": "₺" }
}
```

:::

ولتغيير تسميات قالب، انسخ القالب وعدّل `lang/tr.json` فيه.

## في قوالبك {#helpers}

هذه الدوال في `$doc` تتبع لغة المستند، فيعمل القالب الواحد بكل اللغات:

```php
<?= $doc->e($doc->date($invoice['date'])) ?>            <!-- 08.10.2026 -->
<?= $doc->e($doc->date($invoice['issued_at'], true)) ?> <!-- 08.10.2026 14:30 -->
<?= $doc->money($total, $doc->currency('TRY')) ?>        <!-- 1.250,50 TL -->
<?= $doc->e($doc->inWords($total, 'TRY')) ?>             <!-- Yalnız bin iki yüz elli Türk Lirası ve 50/100 -->
<?= $doc->e($doc->join(['Ankara', 'İzmir'])) ?>          <!-- Ankara, İzmir -->
<?= $doc->e($doc->ucfirst('ikinci taraf')) ?>            <!-- İkinci taraf -->
<?= $doc->e($doc->language('name')) ?>                   <!-- Türkçe -->
```

انظر [دوال القوالب](/ar/reference/template-helpers) لكلها.
