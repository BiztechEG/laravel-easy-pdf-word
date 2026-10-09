# حل المشكلات

مشكلات شائعة وأسبابها وطرق حلها. والرسائل المذكورة هي ما تطبعه الحزمة ومحركاتها وLaravel.

## حروف عربية تظهر مربعات أو منفصلة أو بخط آخر {#arabic-letters}

**مربعات (□□□) بدل الحروف.** الخط المستخدم لا يحتوي حروفًا عربية. ويحدث هذا حين:

- تسجّل خطًا بلا حروف عربية وتجعله خط المستند (`->font()` أو `fonts.default`)؛
- يحدد الـ CSS الخاص بك، مع Chromium أو Gotenberg، خطًا غير مسجل في الحزمة، ولا يوجد على الخادم خط فيه حروف عربية؛
- يحتوي المستند حروفًا من لغة أخرى، كالصينية أو الهندية، لا يحتويها خط المستند.

استخدم أحد الخطوط المرفقة أو خطًا يدعم العربية. وللغات الأخرى مع mPDF، فعّل `auto_lang_to_font` (انظر [الخطوط](/ar/guide/fonts#auto-lang-to-font)). ولخط ثانٍ مع Chromium، سجّله (انظر [الخطوط](/ar/guide/fonts#custom-fonts))؛ فالخط المسجل الذي يذكره الـ CSS الخاص بك يُضمَّن تلقائيًا.

**حروف مرسومة منفصلة غير متصلة.** يحتوي الخط الحروف العربية لكن بلا قواعد التشكيل التي تصلها (جدول GSUB). وتفتقدها بعض الخطوط القديمة والخطوط المحوّلة ببعض الأدوات. استخدم خط TrueType حديثًا، كالخطوط المرفقة.

**العربية بخط غير الذي اخترته.** مع mPDF، يحل DejaVu Sans محل أي `font-family` في الـ CSS الخاص بك ليس اسم خط مسجّل (`Arial` و`Tahoma` و`Roboto`)، ومحل أي اسم تمرره إلى `->font()` ولم تسجّله. استخدم الأسماء المسجّلة: `cairo` أو `tajawal` أو `naskh` أو خطك الخاص. وأسماء الخطوط الأساسية القديمة في mPDF (`chelvetica` و`ctimes` و`ccourier`) تفشل بالرسالة `You cannot use core fonts in a document which contains RTL text.`

**المستند كله من اليسار إلى اليمين.** لغة المستند ليست لغة تُكتب من اليمين إلى اليسار. استدعِ `->locale('ar')`، أو حدد لغة التطبيق أو `locale` في ملف الإعدادات. انظر [دعم اللغة العربية](/ar/guide/arabic#locale).

## أرقام أو رموز بترتيب خاطئ {#mixed-order}

داخل النص العربي، يظهر رقم هاتف مثل `+20 100 000 0000` هكذا `0000 000 100 20+`، ورقم ضريبي مثل `123-456-789` هكذا `789-456-123`، وتاريخ مثل `2026-10-08` هكذا `08-10-2026`.

هكذا تعامل قواعد Unicode ثنائية الاتجاه الأرقامَ التي تأتي بعد حروف عربية: المجموعات المفصولة بمسافات أو شرطات تُرتَّب من اليمين إلى اليسار. أما الأرقام مثل `1,250.00`، والتواريخ بالشرطة المائلة (`2026/10/08`)، والرموز التي تبدأ بحروف لاتينية (`INV-2026-1024`)، فتحتفظ بترتيبها.

غلّف هذه القيم بـ `$doc->ltr()` في Blade view، أو أعطها النمط `ltr` في `Doc::make()`:

```blade
<p>الهاتف: {{ $doc->ltr('+20 100 000 0000') }}</p>
```

```php
Doc::make()->paragraph(['الرقم الضريبي: ', ['text' => '123-456-789', 'ltr' => true]])->locale('ar');
```

انظر [النص العربي والإنجليزي معًا](/ar/guide/arabic#mixed-text).

## mPDF يتوقف بخطأ في الخط {#mpdf-font-errors}

```text
Font "almarai" contains MarkGlyphSets which is not supported
This font [almarai] contains MarkGlyphSets - Not tested yet
GPOS Lookup Type 5, Format 3 not supported (ttfontsuni.php).
```

يستخدم الخط ميزات لا يستطيع mPDF قراءتها، وهذا شائع في الخطوط الحديثة وخطوط Google Fonts. أصلح ملفات الخط مرة واحدة بالسكربت المرفق مع الحزمة:

```bash
python3 -m pip install fonttools
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/Almarai-*.ttf
```

أخطاء أخرى في الخطوط:

| الرسالة | الحل |
| --- | --- |
| `Fonts with postscript outlines are not supported` | استخدم نسخة `.ttf` (TrueType) من الخط، لا `.otf`. |
| `The font files [...] and [...] have the same name. mPDF finds fonts by file name, so rename one of them.` | أعطِ كل ملف خط اسمًا فريدًا. |
| `Font [name] needs at least a "regular" file.` | أضف `regular` إلى الخط في `fonts.custom`. |

انظر [الخطوط](/ar/guide/fonts#mpdf-font-fix).

## نفاد الذاكرة مع الجداول الكبيرة {#memory}

```text
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in vendor/mpdf/mpdf/src/Mpdf.php
```

يحتفظ mPDF بالجدول كاملًا في الذاكرة، نحو 90 KB لكل صف، فيتجاوز تقرير من 1,000 إلى 1,500 صف الحد الافتراضي لـ PHP وهو 128 MB. والخطأ الفادح (fatal error) لا يمكن أن يرجع إلى محرك آخر.

- ارفع الحد حيث يُنشأ التقرير: `ini_set('memory_limit', '512M')`، ويُفضَّل في job في الـ queue.
- أو أنشئ التقارير الطويلة بـ Chromium أو Gotenberg، فهما يستهلكان القليل من ذاكرة PHP: `->driver('chromium')`.
- أو قسّم التقرير، مثلًا ملف PDF لكل شهر أو فرع.

انظر [محركات PDF](/ar/guide/engines#large-documents).

## تحذيرات الرجوع إلى المحرك الاحتياطي في السجل {#fallback-warning}

```text
production.WARNING: easy-pdf-word: [browsershot] failed, falling back to [mpdf]: ...
production.WARNING: easy-pdf-word: [gotenberg] failed, falling back to [mpdf]: cURL error 7: Failed to connect to localhost port 3000 ...
```

فشل المحرك الذي اخترته أو لم يُثبَّت، فأنشأ mPDF الملف بدلًا منه. والنص بعد النقطتين هو خطأ المحرك، وتشرح الأقسام التالية الأخطاء المعتادة. وإلى أن تصلحه، يتسلم مستخدموك ملفات PDF من mPDF، وقد يختلف شكلها في الـ views الخاصة بك.

لترى الخطأ مباشرة بدل الرجوع إلى المحرك الاحتياطي، حدد `DOC_PDF_FALLBACK=null` أثناء الإصلاح، أو افحص `->pdf()->engine()`. انظر [المحرك الاحتياطي](/ar/guide/engines#fallback).

## Chromium أو Puppeteer غير موجود {#chromium}

| الرسالة | السبب والحل |
| --- | --- |
| `The [browsershot] engine needs the spatie/browsershot package. Run: composer require spatie/browsershot` | Browsershot غير مثبت. |
| `sh: 1: node: not found` (رمز الخروج 127) | لا يجد خادم الويب Node. حدد `DOC_NODE_BINARY` (و`DOC_NPM_BINARY`) بمساراتهما الكاملة، وتجدها بـ `which node`. |
| `Error: Cannot find module 'puppeteer'` | Puppeteer غير مثبت حيث يبحث Node. شغّل `npm install -g puppeteer` وحدد `DOC_NODE_MODULES_PATH` بناتج `npm root -g`، أو شغّل `npm install puppeteer` في المجلد الرئيسي للتطبيق. |
| `Error: Could not find Chrome (ver. ...)` أو `Could not find chrome-headless-shell (ver. ...)` | Chrome الخاص بـ Puppeteer غير موجود، أو ثُبّت لمستخدم آخر (فهو في `~/.cache/puppeteer` لذلك المستخدم). ثبّت Chrome أو Chromium للنظام كله وحدد `DOC_CHROME_PATH`، أو شغّل `npx puppeteer browsers install chrome-headless-shell` بالمستخدم الذي يعمل به PHP. |
| `Browser was not found at the configured executablePath (/usr/bin/google-chrome)` | يشير `DOC_CHROME_PATH` إلى ملف غير موجود. تحقق من المسار بـ `which chromium` أو `which google-chrome`. |
| `Running as root without --no-sandbox is not supported` | يعمل PHP بالمستخدم root، كما في كثير من صور Docker. حدد `DOC_CHROME_NO_SANDBOX=true`، أو شغّل PHP بمستخدم آخر. |
| خطأ انتهاء المهلة بعد 60 ثانية | استغرقت الصفحة أكثر من `pdf.drivers.browsershot.timeout`، غالبًا بسبب صور بعيدة بطيئة. ارفع المهلة، أو استخدم صورًا محلية. |

تحتوي الرسالة الكاملة أيضًا أمر Browsershot كله، والجزء المفيد تحت `Error Output`. ومع تفعيل المحرك الاحتياطي، تجدها في تحذير السجل.

## Gotenberg لا يمكن الوصول إليه {#gotenberg}

| الرسالة | السبب والحل |
| --- | --- |
| `cURL error 7: Failed to connect to localhost port 3000` | Gotenberg لا يعمل، أو ليس على هذا العنوان. تحقق من `DOC_GOTENBERG_URL`؛ وداخل Docker Compose استخدم اسم الخدمة، مثل `http://gotenberg:3000`. |
| `cURL error 28: Operation timed out` | لم يُجب Gotenberg خلال `pdf.drivers.gotenberg.timeout` (‏60 ثانية). |
| `Gotenberg returned HTTP 404: ...` | يشير الرابط إلى شيء آخر، أو فيه مسار زائد. استخدم العنوان الأساسي للخادم فقط. |
| `Gotenberg did not return a PDF; check DOC_GOTENBERG_URL. It returned: ...` | أجاب شيء آخر بصفحة ويب، مثل صفحة تسجيل الدخول في proxy. وتنتهي الرسالة ببداية نص تلك الصفحة. |
| `The [gotenberg] engine needs the URL of a Gotenberg server in DOC_GOTENBERG_URL.` | الرابط فارغ. |

## الصور لا تظهر {#images}

الصور التي لا تجتاز [قواعد الصور](/ar/guide/images) تُترك دون خطأ. افحص ما يلي بالترتيب:

1. **ملف محلي خارج المجلدات المسموح بها.** المسموح افتراضيًا `public` و`storage/app` و`resources` فقط. أضف مجلدك إلى `images.paths`.
2. **ملف ليس صورة،** كصفحة خطأ HTML محفوظة باسم `logo.png`. تفحص الحزمة المحتوى، لا الاسم.
3. **مسار على قرص سحابي** مثل `tenants/14/logo.png` على S3. هذا ليس مسارًا محليًا: مرّر رابط الملف أو data URI.
4. **رابط نطاقه غير مسموح به.** أضف النطاق إلى `DOC_REMOTE_IMAGES`، ثم شغّل `php artisan config:cache` مجددًا في بيئة الإنتاج.
5. **رابط يعيد التوجيه.** لا تُتبع إعادة التوجيه: يعرض mPDF بدلًا من الصورة أيقونة صغيرة تعني "الصورة غير موجودة"، ويتركها Chromium وGotenberg وملفات Word. استخدم الرابط النهائي.
6. **صورة SVG تشير إلى ملفات أو روابط**، أو فيها سطر `<!DOCTYPE>`. صدّرها مجددًا بصيغة SVG عادية، أو استخدم PNG.
7. **صورة SVG في ملف Word.** تُترك صور SVG في ملفات Word. استخدم PNG أو JPEG.

لفحص صورة واحدة، ضعها في مستند صغير وابحث عنها في الـ HTML الذي يتسلمه المحرك:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

str_contains(Doc::make()->image($path)->toHtml(), '<img');   // false: الصورة مرفوضة
```

## ملفات Word تظهر بشكل خاطئ {#word}

**النص من اليسار إلى اليمين.** لغة المستند ليست لغة تُكتب من اليمين إلى اليسار: استدعِ `->locale('ar')`. ولقالب `word.docx` صممته في Word، اضبط الفقرات والجداول على الاتجاه من اليمين إلى اليسار في Word نفسه، فالحزمة تملأ القيم فقط.

**الخط ليس الذي اخترته.** لا تضمّن ملفات Word الخطوط. يعرض Word الخط المحدد في `DOC_WORD_FONT` (‏Arial افتراضيًا) حين يكون عند القارئ، وخطًا آخر حين لا يكون. اختر خطًا يملكه قراؤك ويدعم العربية، مثل Arial أو Tahoma أو Sakkal Majalla، وشغّل `php artisan config:cache` مجددًا. انظر [الخطوط](/ar/guide/fonts#word).

**فواصل الآلاف والكسور العشرية.** مع الأرقام العربية، تحتفظ ملفات Word بـ `,` و`.` (١٢,٥٠٠.٧٥)، لأن خط القارئ قد لا يرسم الفواصل العربية.

**لا علامة مائية.** تُنشأ ملفات Word بلا العلامة المائية، فهي لملفات PDF فقط.

**أخطاء عند إنشاء الملف:**

| الرسالة | الحل |
| --- | --- |
| `The [word] engine needs the phpoffice/phpword package. Run: composer require phpoffice/phpword` | ثبّت PhpWord. |
| `Template [name] has no Word layout. Add layout.php, word.php or word.docx to its folder.` | القالب ينشئ ملفات PDF فقط. انظر [ملفات Word](/ar/guide/word). |
| `Word files are made from a template with word.php or word.docx, or from Doc::make(). Blade views and HTML only make PDFs.` | لا يمكن أن تصبح الـ views والـ HTML ملفات Word. ابنِ المستند بـ [`Doc::make()`](/ar/guide/builder). |
| `Word files cannot take a password; ->password() works for PDF files only.` | احذف `->password()` لملف Word، أو أرسل ملف PDF. |

## التاريخ الهجري يفشل {#hijri}

```text
Hijri dates need the PHP intl extension.
```

ثبّت الإضافة `intl` وفعّلها لنسخة PHP التي تشغّل تطبيقك (مثلًا `sudo apt install php8.3-intl` ثم أعد تشغيل PHP-FPM)، وتحقق بـ `php -m | grep intl`. وتترك القوالب المرفقة التاريخ الهجري ما دامت `intl` غير موجودة، فتُنشأ بشكل طبيعي؛ ولا يرمي الخطأ إلا استدعاؤك أنت لـ `Arabic::hijri()` أو `hijri_date()` أو `@hijri`.

## ملفات ZIP أو Word تفشل: ZipArchive غير موجود {#zip}

```text
ZIP files need the PHP zip extension (ext-zip).
Class "ZipArchive" not found
```

الرسالة الأولى من `Doc::zip()`، والثانية من ملفات Word، لأن ملف `.docx` أرشيف ZIP أيضًا. ثبّت الإضافة `zip` وفعّلها (`sudo apt install php8.3-zip`). ويتحقق Composer منها حين تثبّت PhpWord، فإن عملت في سطر الأوامر ولم تعمل في الموقع، فإن PHP الخاص بخادم الويب (PHP-FPM) يفتقد الإضافة: افحص `phpinfo()` الخاص به.

## بيانات القالب مرفوضة {#validation}

```text
The buyer.name field is required. (and 1 more error)
```

يجري التحقق من البيانات المرسلة إلى القالب وفق القواعد في ملف `template.php` الخاص به (`fields`) قبل إنشاء أي شيء، فيرمي الخطأ `Illuminate\Validation\ValidationException`. وفي الـ controller، يحوّله Laravel إلى redirect للصفحة السابقة مع الأخطاء، أو إلى رد JSON بالرمز 422. التقطه لترى كل الرسائل:

```php
use Illuminate\Validation\ValidationException;

try {
    $pdf = Doc::template('invoice', $data)->locale('ar')->pdf();
} catch (ValidationException $e) {
    logger()->error('Invoice data', $e->errors());   // ['buyer.name' => ['The buyer.name field is required.'], ...]
    throw $e;
}
```

الحقول التي يأخذها كل قالب مذكورة في صفحته ضمن [القوالب الجاهزة](/ar/templates/). وبعض القوالب تفحص أكثر من القواعد: ترفض الفاتورة الخصم بالرسالة `The discount cannot be more than the line amount (quantity × unit price).`

أخطاء أخرى في القوالب:

- `Template [invoce] was not found in: ...` تذكر المجلدات التي بُحث فيها. تحقق من الاسم بـ `php artisan doc:templates`.
- `View [pdf.contract] not found.` تأتي من `Doc::view()` مع view غير موجود.

## المستندات في الـ queue {#queue}

**الـ job كبير جدًا.** يحمل الـ job بيانات المستند، فقد يتجاوز تقرير من آلاف الصفوف حد الحجم في الـ queue: ‏1 MB في Amazon SQS، و64 KB افتراضيًا في Beanstalkd (`JOB_TOO_BIG`). ضع في الـ queue job خاصًا بك يحمّل البيانات على الـ worker؛ انظر [الإخراج والتسليم](/ar/guide/output#queue-size).

**انتهاء مهلة الـ job.**

```text
BiztechEG\EasyPdfWord\Jobs\SaveDocument has timed out.
BiztechEG\EasyPdfWord\Jobs\SaveDocument has been attempted too many times.
```

استغرق الإنشاء أكثر من `--timeout` الخاص بالـ worker (‏60 ثانية افتراضيًا). أعطِ queue المستندات وقتًا أطول، مثل `--timeout=180`، وأبقِ `retry_after` أكبر منه، وأصلح المحرك الذي يفشل حتى لا يضيف الرجوع إلى المحرك الاحتياطي إنشاءً ثانيًا. انظر [مهلة الـ workers](/ar/guide/output#queue-timeouts).

**`The MAC is invalid.`** الـ job مشفّر بالمفتاح `APP_KEY` الخاص بالتطبيق الذي وضعه في الـ queue. أعطِ الـ workers المفتاح `APP_KEY` نفسه.

**انتهى الـ job لكن لا يوجد ملف.** حفظ worker على خادم آخر الملف على القرص المحلي الخاص به. استخدم قرصًا مشتركًا مثل `s3` للمستندات في الـ queue.

**بريد في الـ queue يفشل بالرسالة `Failed to serialize job of type [Illuminate\Mail\SendQueuedMailable]: Serialization of 'Closure' is not allowed`.** مُرِّر ملف PDF إلى الـ constructor الخاص بالـ Mailable. أنشئ الملف داخل `attachments()` بدلًا من ذلك؛ انظر [البريد في الـ queue](/ar/guide/output#queued-mail).

## أخطاء أخرى {#other}

| الرسالة | الحل |
| --- | --- |
| `The [mpdf] engine needs the mpdf/mpdf package. Run: composer require mpdf/mpdf` | ثبّت mPDF، أو اختر محركًا آخر بـ `DOC_PDF_DRIVER`. |
| `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().` | اسم محرك مكتوب خطأً في `->driver()` أو `DOC_PDF_DRIVER` أو `doc:sample --driver`. صحّح الاسم؛ فلا يوجد محرك احتياطي لاسم ليس محركًا. |
| `PDF passwords need the mpdf/mpdf package, also with Chromium. Run: composer require mpdf/mpdf` | تحتاج `->password()` مع Chromium أو Gotenberg إلى mPDF لتشفير الملف. |
| `Cannot create the mPDF temp folder [...]` أو `The mPDF temp folder [...] is not writable.` | حدد في `pdf.drivers.mpdf.temp_dir` مجلدًا يستطيع خادم الويب والـ workers الكتابة فيه. |
| `Could not write [invoices/INV-2026-1024.pdf] to the [s3] disk.` | تحقق من إعدادات القرص وصلاحياته. انظر [الإخراج والتسليم](/ar/guide/output#save-failures). |
| `Unknown paper size [Foolscap].` | استخدم مقاسًا من القائمة، أو `[width, height]` بالمليمتر. انظر [إعدادات الصفحة](/ar/guide/page-settings#paper). |
| `Unknown currency [GBP]. Register it with Tafqeet::registerCurrency().` | أضف العملة تحت `currencies` في ملف الإعدادات. انظر [دعم اللغة العربية](/ar/guide/arabic#currencies). |
