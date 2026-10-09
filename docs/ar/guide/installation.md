# التثبيت

تثبّت هذه الصفحة الحزمة ومحرك PDF ودعم ملفات Word، ثم تتأكد من أن كل شيء يعمل. يستغرق ذلك دقائق قليلة على أي خادم يشغّل Laravel.

## تثبيت الحزمة {#install}

```bash
composer require biztecheg/laravel-easy-pdf-word
```

يكتشف Laravel الـ service provider والـ facade ‏`Doc` تلقائياً. وإذا كان تطبيقك يوقف الاكتشاف التلقائي للحزم، فأضف `BiztechEG\EasyPdfWord\EasyPdfWordServiceProvider` إلى `bootstrap/providers.php`.

تحتاج الحزمة إلى PHP 8.2 أو أحدث، وLaravel 12 أو 13. محركات PDF ومكتبة PhpWord حزم منفصلة، فلا تثبّت إلا ما تستخدمه.

## اختيار محرك PDF {#engines}

ثبّت محركاً واحداً على الأقل. محرك mPDF هو الافتراضي ويكفي معظم التطبيقات، ويمكنك إضافة Chromium لاحقاً والتبديل بينهما لكل مستند.

::: code-group

```bash [mPDF (الافتراضي)]
composer require mpdf/mpdf
```

```bash [Chromium (Browsershot)]
composer require spatie/browsershot
npm install --global puppeteer
```

```bash [Gotenberg]
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

:::

### mPDF {#mpdf}

مكتوب بلغة PHP بالكامل، ولا يحتاج إلى تثبيت أي شيء آخر. يشكّل الحروف العربية بنفسه ويعمل على الاستضافة المشتركة. يلزم الإصدار 8.2 أو أحدث. لا يحتاج إلى أي إعداد: فهو المحرك الافتراضي.

### Chromium عبر Browsershot {#chromium}

أفضل عرض ودعم كامل لـ CSS الحديثة، عبر `spatie/browsershot` بالإصدار 5.4 أو أحدث. يحتاج الخادم إلى Node.js وPuppeteer ومتصفح Chrome أو Chromium. ينزّل Puppeteer نسخته الخاصة من Chrome عند تثبيته، ولاستخدام متصفح موجود على الخادم بالفعل، وجّه الحزمة إليه. ثم اجعل Chromium المحرك الافتراضي في `.env`:

```dotenv
DOC_PDF_DRIVER=chromium

# اختيارية: عندما لا يكون Node أو npm أو Chrome حيث يبحث عنها Browsershot
DOC_NODE_BINARY=/usr/bin/node
DOC_NPM_BINARY=/usr/bin/npm
DOC_CHROME_PATH=/usr/bin/google-chrome
# المجلد الذي يطبعه `npm root -g`، وتحديده يوفّر البحث عنه مع كل مستند
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
# يلزم عندما يعمل Chrome بصلاحيات root، كما في Docker
DOC_CHROME_NO_SANDBOX=true
```

### Gotenberg {#gotenberg}

يشغّل [Gotenberg](https://gotenberg.dev) متصفح Chromium داخل حاوية Docker ويحوّل HTML عبر HTTP، فلا يحتاج خادم التطبيق إلى Node ولا Chrome. تتصل به الحزمة عبر HTTP client الخاص بـ Laravel، فلا توجد حزمة Composer تضيفها. شغّل الحاوية (بالأمر أعلاه)، ثم:

```dotenv
DOC_PDF_DRIVER=gotenberg
DOC_GOTENBERG_URL=http://localhost:3000
```

### المحرك الاحتياطي {#fallback}

إذا لم يكن المحرك المختار مثبتاً أو تعطل، يُرسم المستند بالمحرك الاحتياطي، وهو mPDF افتراضياً، ويُسجَّل تحذير في السجل. أبقِ `mpdf/mpdf` مثبتاً إن أردت شبكة الأمان هذه، أو أوقفها:

```dotenv
DOC_PDF_FALLBACK=null
```

تقارن صفحة [محركات PDF](/ar/guide/engines) بين المحركات وتسرد كل خياراتها.

## ملفات Word {#word}

تحتاج ملفات Word ‏(.docx) إلى PhpWord بالإصدار 1.4 أو أحدث:

```bash
composer require phpoffice/phpword
```

بدونها يتوقف `->word()` بالرسالة "The [word] engine needs the phpoffice/phpword package. Run: composer require phpoffice/phpword". ولا تحتاج ملفات PDF إليها. راجع [ملفات Word](/ar/guide/word).

## إضافات PHP {#extensions}

| الإضافة | متى تلزم | الغرض منها |
| --- | --- | --- |
| `mbstring` | دائماً | النص العربي وغيره من النصوص متعددة البايت |
| `gd` | دائماً | رموز QR والصور، ويحتاج إليها mPDF أيضاً، وتستخدمها ملفات Word لتحويل صور WebP وBMP إلى PNG |
| `intl` | للتاريخ الهجري | التاريخ الهجري، وكتابة المبالغ بالحروف في اللغات غير العربية. بدونها تُسقط القوالب التاريخ الهجري. |
| `zip` | لملفات ZIP وWord | أرشيفات `Doc::zip()`، وتحتاج إليها PhpWord أيضاً لكتابة ملفات `.docx` |

اعرف الإضافات المتاحة لديك بالأمر `php -m`. وعلى Ubuntu أو Debian تُثبَّت الإضافة الناقصة بحزمة مثل `php8.3-intl` أو `php8.3-zip`.

## نشر ملف الإعدادات {#config}

تعمل القيم الافتراضية دون ملف إعدادات. ولتغييرها (اللغة الافتراضية، والأرقام، وألوان الهوية وبيانات الشركة، والخطوط، والمحركات)، انشر ملف الإعدادات:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

ينشئ هذا الأمر الملف `config/easy-pdf-word.php`، وهو الملف الوحيد الذي تنشره الحزمة: أما القوالب فتُنسخ إلى تطبيقك بالأمر `php artisan doc:template` (راجع [القوالب الجاهزة](/ar/guide/templates)). وتشرح صفحة [الإعدادات](/ar/guide/configuration) كل مفتاح فيه.

ولمعظم الإعدادات متغير في `.env` أيضاً، فقد لا تحتاج إلى الملف أصلاً:

```dotenv
DOC_PDF_DRIVER=mpdf
DOC_WORD_FONT=Arial
DOC_REMOTE_IMAGES=false
DOC_PREVIEW=false
```

## التأكد من أن كل شيء يعمل {#check}

اعرض قائمة القوالب، ثم أنشئ أحدها ببياناته التجريبية:

```bash
php artisan doc:templates

php artisan doc:sample invoice --output=storage/app/invoice-sample.pdf
php artisan doc:sample invoice --locale=en --output=storage/app/invoice-sample.docx
```

افتح `storage/app/invoice-sample.pdf`: يجب أن ترى فاتورة ضريبية عربية بحروف متصلة ومن اليمين إلى اليسار. أما الأمر الثاني فينشئ الفاتورة الإنجليزية في ملف Word، إذ تتبع الصيغة امتداد `--output`. وبدون `--output` تُحفظ الملفات في `storage/app/doc-samples/`. راجع [أوامر Artisan](/ar/guide/commands) لكل الخيارات.

وفي بيئة `local` يمكنك أيضاً فتح `/doc-preview` في المتصفح لرؤية كل قالب بالعربية والإنجليزية، وبأي من المحركين. راجع [صفحة المعاينة](/ar/guide/preview).

## الاستضافة المشتركة {#shared-hosting}

تعمل الحزمة على الاستضافة المشتركة مع محرك mPDF المكتوب بلغة PHP بالكامل:

- أبقِ المحرك الافتراضي: `DOC_PDF_DRIVER=mpdf`. يحتاج Chromium إلى Node وعملية متصفح، ونادراً ما تسمح بهما الاستضافة المشتركة. وخادم Gotenberg في مكان آخر خيار متاح إن كان لديك واحد.
- تأكد من توفر الإضافات `mbstring` و`gd` و`intl` و`zip` لدى الاستضافة، فمعظم لوحات التحكم تتيح تفعيلها لكل إصدار من PHP.
- يحتفظ mPDF بذاكرة مؤقتة للخطوط في مجلد خاص به داخل المجلد المؤقت للنظام. فإن لم تسمح الاستضافة بذلك، تذكر رسالة الخطأ الإعداد الذي يجب تغييره، فوجّهه إلى مجلد داخل تطبيقك في `config/easy-pdf-word.php`:

  ```php
  'pdf' => [
      'drivers' => [
          'mpdf' => [
              'temp_dir' => storage_path('app/mpdf'),
              // ...
          ],
      ],
  ],
  ```

- الجداول الكبيرة تستهلك الذاكرة: يحتاج mPDF إلى نحو 85 كيلوبايت لكل صف، فتقرير من ألف صف يحتاج إلى أكثر من 128 ميجابايت التي تتيحها PHP افتراضياً. راجع [محركات PDF](/ar/guide/engines).

التالي: تنشئ [البداية السريعة](/ar/guide/quick-start) فاتورة حقيقية من controller.
