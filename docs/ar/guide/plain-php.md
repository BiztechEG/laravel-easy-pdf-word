# PHP بدون إطار عمل

تعمل المكتبة أيضًا بدون Laravel: في سكريبت PHP عادي، وفي WordPress وSymfony وCodeIgniter وأي إطار عمل آخر. وهي الحزمة [biztecheg/easy-pdf-word](https://github.com/BiztechEG/easy-pdf-word) التي بُنيت عليها حزمة Laravel. فيها القوالب والمحركات ودعم العربية وملفات Word نفسها، وينقصها فقط ما يخص Laravel. تشرح هذه الصفحة طريقة استخدامها، ولكلٍّ من [WordPress](/ar/guide/wordpress) و[Symfony](/ar/guide/symfony) صفحة خاصة.

## التثبيت {#install}

```bash
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

يثبّت هذا الأمر المكتبة ومحرك mPDF وPhpWord لملفات Word. تحتاج إلى PHP 8.2 أو أحدث مع الإضافتين `mbstring` و`gd`، وإلى `intl` للتاريخ الهجري، و`zip` لملفات Word وملفات ZIP، و`curl` لمحرك Gotenberg (أما حزمة Laravel فترسل عبر عميل HTTP الخاص بـ Laravel). ولاستخدام Chromium بدلًا من mPDF راجع [محركات PDF](/ar/guide/engines)، فالمحركات تعمل هنا بالطريقة نفسها.

## إنشاء الـ factory {#create}

يبدأ كل شيء من `EasyPdfWord::create()`. تأخذ هذه الدالة إعدادات [ملف الإعدادات](/ar/guide/configuration) نفسها في حزمة Laravel، في صورة مصفوفة PHP، واترك منها ما لا تغيّره.

```php
require __DIR__.'/vendor/autoload.php';

use BiztechEG\EasyPdfWord\EasyPdfWord;

$docs = EasyPdfWord::create([
    'locale' => 'ar',
    'theme' => [
        'primary' => '#0F766E',
        'logo' => __DIR__.'/assets/logo.png',
        'company' => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    ],
    'images' => ['paths' => [__DIR__.'/assets']],
    'views' => ['paths' => [__DIR__.'/views']],
]);
```

أنشئه مرة واحدة وأعد استخدامه، مثلًا في دالة صغيرة أو في الـ service container الخاص بإطار عملك. وتعيد `EasyPdfWord::defaults()` كل الإعدادات بقيمها الافتراضية.

أكثر الإعدادات التي ستغيّرها:

| الإعداد | الافتراضي | وظيفته |
| --- | --- | --- |
| `locale` | `en` | لغة المستندات: `ar` تعطي عناوين عربية وصفحة من اليمين إلى اليسار |
| `numerals` | `latin` | `arabic` تطبع ٠١٢٣ |
| `theme` | | الألوان والشعار وبيانات الشركة التي تظهر في كل القوالب |
| `pdf.driver` | `mpdf` | `mpdf` أو `browsershot` أو `gotenberg`؛ راجع [محركات PDF](/ar/guide/engines) |
| `templates.paths` | `[]` | مجلدات قوالبك الخاصة، ويُبحث فيها قبل القوالب المرفقة |
| `views.paths` | `[]` | مجلدات يجد فيها `->view('contracts.show')` الملف `contracts/show.html.php` |
| `images.paths` | `[]` | المجلدات المسموح أن تأتي منها الصور المحلية (الشعار والتوقيع والختم)؛ لا شيء افتراضيًا |
| `fonts.custom` | `[]` | خطوطك الخاصة؛ راجع [الخطوط](/ar/guide/fonts) |
| `temp_dir` | `null` | مجلد للملفات المؤقتة لملفات Word وقوالب docx وملفات ZIP وChromium بدل مجلد النظام؛ مفيد في الاستضافة المشتركة |

القوائم مثل `images.paths` و`pdf.margins` تحل محل القائمة الافتراضية، أما باقي الإعدادات فتُدمج مفتاحًا بمفتاح.

## إنشاء المستندات {#make}

للـ factory الطرق الأربع نفسها لبدء مستند كما في الـ facade ‏`Doc` في Laravel، وللمستند الإعدادات نفسها (`->locale()` و`->paper()` و`->font()` و`->watermark()` و`->password()` ...). وباقي هذا الدليل ينطبق كما هو؛ اقرأ `$docs->` حيث تجد `Doc::`.

```php
// قالب جاهز ببياناتك
$invoice = $docs->template('invoice', [
    'invoice' => ['number' => 'INV-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'seller'  => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    'buyer'   => ['name' => 'مؤسسة النور'],
    'items'   => [['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000]],
]);

// مستند مبني بالكود
$report = $docs->make()
    ->heading('تقرير المبيعات')
    ->table([['البند', 'القيمة'], ['المبيعات', '120,000']], ['header' => true]);

// HTML خاص بك
$page = $docs->html('<h1>مرحباً</h1>');

// view خاص بك: views/contracts/show.html.php
$contract = $docs->view('contracts.show', ['contract' => $data]);
```

القوالب والمستندات المبنية بالكود تُنتج ملفات PDF وWord، أما HTML والـ views فتُنتج ملفات PDF.

| تبدأ من | الدليل |
| --- | --- |
| `$docs->template()` | [القوالب الجاهزة](/ar/guide/templates)، [قوالبك الخاصة](/ar/guide/custom-templates) |
| `$docs->make()` | [بناء المستند بالكود](/ar/guide/builder)، [ملفات Word](/ar/guide/word) |
| `$docs->html()` و`$docs->view()` | [الـ views الخاصة بك](#views) أدناه |

## حفظ الملف أو إرساله {#output}

تعيد `->pdf()` و`->word()` ملفًا. ولا يُنشأ شيء حتى تطلب محتواه، ثم يُنشأ مرة واحدة فقط.

```php
$file = $invoice->pdf('INV-1024.pdf');

$file->save(__DIR__.'/storage/INV-1024.pdf');   // احفظه في مجلد (يُنشأ المجلد إن لم يوجد)
$file->send();                                  // أرسله إلى المتصفح للتنزيل، أو:
// $file->send('فاتورة-1024.pdf', inline: true); // اعرضه في المتصفح باسم آخر
$bytes = $file->content();                       // محتوى الملف لبريد إلكتروني أو رفع
$file->base64();                                 // المحتوى بترميز base64 لواجهة API
$file->mimeType();                               // application/pdf
$file->filename();                               // INV-1024.pdf
$file->engine();                                 // المحرك الذي أنشأه: mpdf أو browsershot ...
```

يحمل الـ response ملفًا واحدًا، لذلك استدعِ `send()` مرة واحدة، بإحدى الطريقتين أعلاه. تضبط `send()` الترويسات `Content-Type` و`Content-Length` و`Content-Disposition` ثم تطبع الملف. استدعها قبل طباعة أي شيء آخر، وأوقف السكريبت بعدها. والمخرجات التي ما زالت تنتظر في مخازن المخرجات (output buffers) في PHP (سطر فارغ زائد أو تنبيه) تُحذف أولًا، فلا تدخل في الملف. أما المخرجات التي وصلت إلى المتصفح فلا يمكن استرجاعها: حينها ترمي `send()` الاستثناء `RuntimeException` بالرسالة `Cannot send the file: output already started at ...` مع اسم الملف ورقم السطر. ويبقى اسم الملف العربي كما هو في المتصفحات، مع اسم بحروف لاتينية للبرامج القديمة.

وتوضع عدة ملفات في ملف ZIP واحد، والمفاتيح هي أسماؤها داخل الملف:

```php
$docs->zip([
    'INV-1024.pdf' => $invoice->pdf(),
    'INV-1024.docx' => $invoice->word(),
], 'INV-1024.zip')->send();
```

## البيانات الخاطئة {#validation}

يُتحقق من بيانات القالب وفق قواعده قبل إنشاء أي شيء. وعند وجود خطأ يُرمى `ValidationFailed`، وتعيد دالته `errors()` الرسائل مرتبة حسب الحقل:

```php
use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;

try {
    $docs->template('invoice', $data)->pdf()->save($path);
} catch (ValidationFailed $e) {
    $e->errors();   // ['invoice.number' => ['The invoice.number field is required.'], ...]
}
```

القواعد والرسائل هي نفسها في Laravel، لكن بدون Laravel، والرسائل بالإنجليزية. وتتخطى `->withoutValidation()` هذا التحقق.

## الـ views الخاصة بك {#views}

الـ view ملف PHP عادي يطبع HTML. يستقبل بياناتك في صورة متغيرات، ومعها `$doc` وهو أدوات المستند:

```php
<?php // views/contracts/show.html.php ?>
<h1><?= $doc->e($contract['title']) ?></h1>
<p><?= $doc->e($contract['client']) ?></p>
<p><?= $doc->money($contract['total'], 'EGP') ?></p>
<p><?= $doc->e($doc->tafqeet($contract['total'], 'EGP')) ?></p>
```

هرّب كل قيمة تأتي من بياناتك بـ `$doc->e()`. وتضع المكتبة الـ view داخل صفحة كاملة بلغة المستند واتجاهه وخطوطه وألوانه، وتجد كل الأدوات في [أدوات القوالب](/ar/reference/template-helpers). وتعمل `$docs->html($html)` بالطريقة نفسها مع HTML جاهز لديك.

## قوالبك الخاصة {#templates}

القالب مجلد، وتشرح صفحة [قوالبك الخاصة](/ar/guide/custom-templates) كل ملف فيه. ضع مجلداتك في مسار واحد وأضفه إلى `templates.paths`:

```php
$docs = EasyPdfWord::create(['templates' => ['paths' => [__DIR__.'/doc-templates']]]);

$docs->template('warranty', $data)->pdf()->save(__DIR__.'/warranty.pdf');
```

لا يوجد أمر `doc:template` خارج Laravel. ولتعديل قالب مرفق، انسخ مجلده من `vendor/biztecheg/easy-pdf-word/resources/templates/` إلى مسارك وعدّل النسخة، فتُستخدم بدلًا من القالب المرفق لأن مجلداتك تأتي أولًا. والبيانات التجريبية لكل قالب طريقة سريعة لتجربته:

```php
$letter = $docs->templates()->get('letter');

$docs->template('letter', $letter->sample())->pdf()->save(__DIR__.'/letter.pdf');
```

## تحذيرات المحركات {#warnings}

عندما يفشل محرك وترجع المكتبة إلى mPDF، أو تُترك صورة لأن مجلدها ليس في `images.paths` أو لأن نطاقها غير مسموح به، تستدعي المكتبة دالة `warn` التي تمررها في المعامل الثاني. وبدونها يذهب التحذير إلى `error_log()` في PHP.

```php
$docs = EasyPdfWord::create($config, [
    'warn' => fn (string $message) => $logger->warning($message),
]);
```

## ما يوجد في Laravel فقط {#laravel-only}

هذه الأشياء من حزمة Laravel. وخارج Laravel استخدم طريقة PHP العادية:

| في Laravel | في PHP العادي |
| --- | --- |
| `Doc::template()` والـ facade ‏`Doc` | `$docs->template()` على الـ factory الناتج من `create()` |
| ملفات Blade وقوالب `.blade.php` | views وقوالب `.html.php`. ومجلد القالب الذي فيه ملفات `.html.php` يعمل في Laravel أيضًا، أما `Doc::view()` هناك فلا يجد إلا views الخاصة بـ Laravel (`.blade.php` أو `.php`)، لا `.html.php` |
| `config/easy-pdf-word.php` و`.env` | المصفوفة التي تمررها إلى `create()` |
| `->download()` و`->stream()` وإعادة الملف من controller | `->send()`، أو `->content()` في الـ response الخاص بإطار عملك |
| `->save($path, 's3')` على disk | `->save($path)` في مجلد محلي، أو `->content()` إلى مكان التخزين لديك |
| مرفقات البريد و`->queue()` | `->content()` مع نظام البريد أو الـ queue لديك |
| `Doc::fake()` | بديل اختبار تكتبه بنفسك |
| صفحة المعاينة وأوامر Artisan | `$docs->templates()->get($name)->sample()` |
| رسائل التحقق المترجمة في Laravel | رسائل بالإنجليزية |
