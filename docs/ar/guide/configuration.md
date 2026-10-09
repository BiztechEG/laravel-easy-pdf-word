# الإعدادات

كل إعداد في `config/easy-pdf-word.php`، وما يفعله وقيمته الافتراضية، وكل متغير في `.env` تقرؤه الحزمة.

تعمل الحزمة دون ملف إعدادات. ولتغيير إعداد، انشر الملف أولًا:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

تدمج الحزمة ملفها تحت ملفك بعمق مستوى واحد: القسم الذي تحذفه كله (مثل `currencies`) يحتفظ بقيمه الافتراضية، أما القسم الذي تبقيه، مثل `pdf`، فيُستخدم كما كتبته. لذلك أبقِ كل المفاتيح داخل الأقسام التي تعدّلها، كما هي في الملف المنشور.

## متغيرات البيئة {#env}

| المتغير | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `DOC_PDF_DRIVER` | `mpdf` | محرك PDF: `mpdf` أو `chromium` (أو `browsershot`) أو `gotenberg` أو محرك أضفته. |
| `DOC_PDF_FALLBACK` | `mpdf` | المحرك المستخدم حين يكون المحرك المختار غير موجود أو يفشل. والقيمة `null` تلغي المحرك الاحتياطي. |
| `DOC_CHROME_PATH` | فارغ | مسار Chrome أو Chromium لمحرك Chromium. والقيمة الفارغة تستخدم Chrome الذي نزّله Puppeteer. |
| `DOC_NODE_BINARY` | فارغ | مسار `node`، حين لا يكون في `PATH` الخاص بخادم الويب أو الـ worker. |
| `DOC_NPM_BINARY` | فارغ | مسار `npm`، بالطريقة نفسها. |
| `DOC_NODE_MODULES_PATH` | فارغ | مجلد `node_modules` العام (`npm root -g`). ويوفّر تشغيل ذلك الأمر مع كل مستند Chromium. |
| `DOC_CHROME_NO_SANDBOX` | `false` | تشغيل Chrome بلا الـ sandbox الخاص به. وهو لازم حين يعمل PHP بالمستخدم root. |
| `DOC_CHROME_JAVASCRIPT` | `false` | تشغيل JavaScript في صفحات Chromium وGotenberg. |
| `DOC_GOTENBERG_URL` | `http://localhost:3000` | عنوان خادم Gotenberg. |
| `DOC_REMOTE_IMAGES` | `false` | روابط الصور: `false` يتجاهلها، و`true` يسمح بأي رابط، أو قائمة نطاقات تفصلها فواصل (`cdn.biztech.example,*.amazonaws.com`). |
| `DOC_WORD_FONT` | `Arial` | خط ملفات Word. |
| `DOC_PREVIEW` | غير محدد | صفحة المعاينة: غير محدد (أو `null`) يعني مفعلة في `local` فقط؛ و`true` أو `false` يفعّلها أو يعطّلها في كل مكان. والقيمة الفارغة (`DOC_PREVIEW=`) تُعد `false`. |
| `APP_NAME` | | متغير Laravel نفسه، ويُستخدم اسمًا افتراضيًا للشركة في الهوية. |

```dotenv
DOC_PDF_DRIVER=chromium
DOC_PDF_FALLBACK=mpdf
DOC_CHROME_PATH=/usr/bin/chromium
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
DOC_REMOTE_IMAGES=cdn.biztech.example
DOC_WORD_FONT=Tahoma
```

بعد تغيير `.env` في بيئة الإنتاج، شغّل `php artisan config:cache` من جديد.

## اللغة والأرقام {#locale}

```php
'locale' => null,
'numerals' => 'latin',
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `locale` | `null` | لغة المستندات التي لا تستدعي `->locale()`. و`null` تستخدم لغة التطبيق. واللغات التي تُكتب من اليمين إلى اليسار (`ar` و`fa` و`ur` و`he` ...) تجعل المستند من اليمين إلى اليسار. |
| `numerals` | `'latin'` | `'latin'` تطبع 0123456789، و`'arabic'` تطبع ٠١٢٣٤٥٦٧٨٩ في نص المستند. وغيّرها لكل مستند بـ `->numerals()`. |

انظر [دعم اللغة العربية](/ar/guide/arabic).

## PDF {#pdf}

```php
'pdf' => [
    'driver' => env('DOC_PDF_DRIVER', 'mpdf'),
    'fallback' => env('DOC_PDF_FALLBACK', 'mpdf'),
    'paper' => 'A4',
    'orientation' => 'portrait',
    'margins' => [15, 15, 15, 15],
    'drivers' => [ /* below */ ],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `pdf.driver` | `'mpdf'` | المحرك الافتراضي. |
| `pdf.fallback` | `'mpdf'` | المحرك المستخدم حين يفشل المحرك المختار؛ و`null` تلغيه. انظر [المحرك الاحتياطي](/ar/guide/engines#fallback). |
| `pdf.paper` | `'A4'` | اسم مقاس الورق (من `A2` إلى `A6`، و`B4` و`B5` و`Letter` و`Legal` و`Tabloid` و`Executive`) أو `[width, height]` بالمليمتر. |
| `pdf.orientation` | `'portrait'` | `'portrait'` (بالطول) أو `'landscape'` (بالعرض). |
| `pdf.margins` | `[15, 15, 15, 15]` | بالمليمتر: أعلى، يمين، أسفل، يسار. وتعمل الصيغ الأقصر كما في CSS: `[15]` و`[20, 15]` و`[25, 15, 20]`. |

يُقدَّم ملف `template.php` الخاص بالقالب والاستدعاءات على المستند على هذه القيم؛ انظر [إعدادات الصفحة](/ar/guide/page-settings#defaults).

### mPDF {#mpdf}

```php
'mpdf' => [
    'temp_dir' => null,
    'use_kashida' => 75,
    'auto_lang_to_font' => false,
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `temp_dir` | `null` | مجلد ذاكرة الخطوط المؤقتة وملفات العمل لـ mPDF. و`null` تستخدم مجلدًا خاصًا بكل مستخدم في النظام داخل المجلد المؤقت للنظام (`/tmp/easy-pdf-word-{uid}`). وحدد مجلدًا يستطيع خادم الويب والـ workers الكتابة فيه إن لم يكن `/tmp` صالحًا للاستخدام. |
| `use_kashida` | `75` | مقدار ما يتم بالكشيدة (ـ) من تمديد النص العربي المضبوط بدلًا من توسيع المسافات، من 0 إلى 100. |
| `auto_lang_to_font` | `false` | اختيار خط لكل نظام كتابة، للمستندات التي تخلط العربية بأنظمة كتابة لا يحتويها خط المستند (الصينية، الهندية ...). ويتجاهل `font-family` في CSS الخاص بك. |

### Chromium {#browsershot}

```php
'browsershot' => [
    'node_binary' => env('DOC_NODE_BINARY'),
    'npm_binary' => env('DOC_NPM_BINARY'),
    'node_modules_path' => env('DOC_NODE_MODULES_PATH'),
    'chrome_path' => env('DOC_CHROME_PATH'),
    'no_sandbox' => env('DOC_CHROME_NO_SANDBOX', false),
    'javascript' => env('DOC_CHROME_JAVASCRIPT', false),
    'timeout' => 60,
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `node_binary` | `null` | مسار `node`. |
| `npm_binary` | `null` | مسار `npm`. |
| `node_modules_path` | `null` | مجلد `node_modules` العام؛ وإن كان فارغًا يُبحث عنه بـ `npm root -g` مع كل إنشاء. |
| `chrome_path` | `null` | مسار Chrome أو Chromium. |
| `no_sandbox` | `false` | تشغيل Chrome بالخيار `--no-sandbox`. |
| `javascript` | `false` | تشغيل JavaScript في الصفحة. |
| `timeout` | `60` | عدد الثواني قبل التخلي عن الإنشاء. |

انظر [محركات PDF](/ar/guide/engines#chromium).

### Gotenberg {#gotenberg}

```php
'gotenberg' => [
    'url' => env('DOC_GOTENBERG_URL', 'http://localhost:3000'),
    'javascript' => env('DOC_CHROME_JAVASCRIPT', false),
    'timeout' => 60,
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `url` | `'http://localhost:3000'` | عنوان Gotenberg. |
| `javascript` | `false` | تشغيل JavaScript في الصفحة. ويشترك في `DOC_CHROME_JAVASCRIPT` مع Chromium. |
| `timeout` | `60` | عدد ثواني انتظار Gotenberg. |

## الخطوط {#fonts}

```php
'fonts' => [
    'default' => 'cairo',
    'default_ltr' => 'cairo',
    'custom' => [],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `fonts.default` | `'cairo'` | خط المستندات التي تُكتب من اليمين إلى اليسار. |
| `fonts.default_ltr` | `'cairo'` | خط بقية المستندات. |
| `fonts.custom` | `[]` | خطوطك الخاصة بأسمائها: `'almarai' => ['regular' => ..., 'bold' => ...]`. ويأخذ كل خط `regular` (مطلوب) و`bold` و`italic` و`bold_italic` و`arabic_separators` و`arabic`. |

الخطوط المرفقة هي `cairo` و`tajawal` و`naskh`. انظر [الخطوط](/ar/guide/fonts).

## الصور {#images}

```php
'images' => [
    'paths' => [
        public_path(),
        storage_path('app'),
        resource_path(),
    ],
    'remote' => env('DOC_REMOTE_IMAGES', false),
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `images.paths` | `public` و`storage/app` و`resources` | المجلدات الوحيدة التي تُقرأ منها الصور المحلية. و`null` تسمح بأي مجلد. |
| `images.remote` | `false` | روابط الصور: `false` أو `true` أو النطاقات المسموح بها في مصفوفة (`['cdn.biztech.example', '*.amazonaws.com']`) أو نص تفصله فواصل. |

انظر [الصور](/ar/guide/images).

## Word {#word}

```php
'word' => [
    'font' => env('DOC_WORD_FONT', 'Arial'),
    'font_size' => 11,
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `word.font` | `'Arial'` | خط ملفات Word. ولا يضمّن Word الخطوط، فاستخدم خطًا موجودًا لدى قرائك ويغطي العربية. |
| `word.font_size` | `11` | حجم النص بالنقاط. والعناوين أكبر. |

انظر [ملفات Word](/ar/guide/word) و[الخطوط](/ar/guide/fonts#word).

## صفحة المعاينة {#preview}

```php
'preview' => [
    'enabled' => env('DOC_PREVIEW'),
    'path' => 'doc-preview',
    'middleware' => ['web'],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `preview.enabled` | `null` | `null`: مفعلة في `local` فقط. و`true` أو `false`: مفعلة أو معطلة في كل مكان. وخارج `local` تحتاج الصفحة أيضًا إلى الـ gate المسمى `viewDocPreview`. |
| `preview.path` | `'doc-preview'` | رابط الصفحة. |
| `preview.middleware` | `['web']` | الـ middleware لـ routes الصفحة، مثل `['web', 'auth']`. |

انظر [صفحة المعاينة](/ar/guide/preview).

## القوالب {#templates}

```php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
    ],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `templates.paths` | `resources/doc-templates` | المجلدات التي يُبحث فيها عن القوالب بالترتيب، قبل القوالب المرفقة. وأول تطابق هو المعتمد، فقالب المشروع يحل محل القالب المرفق الذي يحمل الاسم نفسه. ويكتب `doc:template` و`doc:make-template` في أول مجلد. |

انظر [قوالبك الخاصة](/ar/guide/custom-templates).

## الهوية {#theme}

```php
'theme' => [
    'primary' => '#0F766E',
    'text' => '#1F2937',
    'muted' => '#6B7280',
    'border' => '#E5E7EB',
    'logo' => null,
    'company' => [
        'name' => env('APP_NAME'),
        'address' => null,
        'phone' => null,
        'email' => null,
        'tax_number' => null,
    ],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `theme.primary` | `'#0F766E'` | اللون الرئيسي: العناوين ورؤوس الجداول والمجاميع. |
| `theme.text` | `'#1F2937'` | لون النص. |
| `theme.muted` | `'#6B7280'` | النص الثانوي، مثل التسميات والتذييلات. |
| `theme.border` | `'#E5E7EB'` | الخطوط وحدود الجداول. |
| `theme.logo` | `null` | شعار الشركة: مسار أو رابط أو data URI، وفق [قواعد الصور](/ar/guide/images). |
| `theme.company.*` | `APP_NAME` ثم `null` | اسم شركتك وعنوانها وهاتفها وبريدها الإلكتروني ورقمها الضريبي. تطبعها القوالب، وتستخدمها الفاتورة بائعًا حين لا تمرر بائعًا. |

يجب أن تكون الألوان ألوان CSS حقيقية؛ وأي شيء آخر يرجع إلى القيمة الافتراضية. وغيّر الهوية لكل مستند بـ `->theme([...])`؛ انظر [القوالب الجاهزة](/ar/guide/templates).

## العملات {#currencies}

```php
'currencies' => [],
```

عملات تُضاف إلى التفقيط (المبلغ كتابةً) أو تحل محل عملات فيه. وتذكر كل عملة الصيغ العربية لوحدتها الأساسية والجزئية، وجنسهما، وعدد الوحدات الجزئية في الوحدة الأساسية. انظر [دعم اللغة العربية](/ar/guide/arabic#currencies).
