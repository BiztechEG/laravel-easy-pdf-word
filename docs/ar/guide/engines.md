# محركات PDF

تستطيع الحزمة إنشاء ملفات PDF بثلاثة محركات. تقارن هذه الصفحة بينها، وتشرح إعداد كل منها، واختيار محرك للتطبيق كله أو لمستند واحد، وما يحدث حين يفشل محرك، وكيف تضيف محركك الخاص.

## مقارنة المحركات {#compare}

| | mPDF | Chromium | Gotenberg |
| --- | --- | --- | --- |
| الاسم في الكود | `mpdf` | `chromium` (أو `browsershot`) | `gotenberg` |
| التثبيت | `mpdf/mpdf` | `spatie/browsershot` وNode.js وPuppeteer وChrome على الخادم | حاوية [Gotenberg](https://gotenberg.dev) يصل إليها التطبيق عبر HTTP |
| أين يعمل | داخل PHP | متصفح Chrome بلا واجهة يُشغَّل لكل مستند | Chrome في حاوية أخرى |
| الاستضافة المشتركة | نعم | نادرًا | لا |
| CSS | قرابة CSS 2.1: الجداول والعناصر العائمة والأنماط المضمنة، بلا flexbox ولا grid | كل ما يدعمه Chrome | كل ما يدعمه Chrome |
| العربية | يوصل mPDF الحروف | يوصلها Chrome (HarfBuzz) | يوصلها Chrome |
| أرقام `{page}` و`{pages}` | تتبع `->numerals()` | لاتينية دائمًا | لاتينية دائمًا |
| العلامة المائية | نعم | نعم | نعم |
| كلمة المرور | نعم | نعم، يضيفها mPDF بعد ذلك | نعم، يضيفها mPDF بعد ذلك |
| الجداول الكبيرة | قرابة 90 KB من ذاكرة PHP لكل صف | ذاكرة PHP قليلة | ذاكرة PHP قليلة |
| الترخيص | GPL-2.0 | MIT (Browsershot) وApache-2.0 (Puppeteer) | MIT |

تستخدم القوالب المرفقة CSS تفهمه كل المحركات، فتبدو متطابقة على الثلاثة. وmPDF هو المحرك الافتراضي لأنه لا يحتاج إلا إلى PHP. اختر Chromium أو Gotenberg حين تستخدم views الخاصة بك CSS حديثًا، أو للتقارير الطويلة.

## mPDF {#mpdf}

```bash
composer require mpdf/mpdf
```

هذا كل شيء. mPDF مكتوب بلغة PHP بالكامل ويعمل على الاستضافة المشتركة.

- ترخيص mPDF هو GPL-2.0. تأكد أنه يناسب مشروعك، أو استخدم Chromium أو Gotenberg بدلًا منه.
- دعمه لـ CSS قرابة CSS 2.1. صمّم الصفحات بالجداول والعناصر العائمة، لا بـ flexbox ولا grid. وتشرح صفحة [ملفات Blade و HTML](/ar/guide/views-and-html) الـ views والـ HTML الخاصة بك.
- يحفظ mPDF ذاكرة الخطوط المؤقتة وملفات العمل في مجلد خاص بكل مستخدم في النظام (`/tmp/easy-pdf-word-{uid}`)، فلا يحجب خادم الويب والـ queue worker أحدهما الآخر إن عملا بمستخدمين مختلفين. وحدد مجلدًا آخر في `pdf.drivers.mpdf.temp_dir`.
- يحدد `use_kashida` (القيمة الافتراضية `75`) مقدار ما يتم بالكشيدة (ـ) من تمديد النص العربي المضبوط بدلًا من توسيع المسافات، من 0 إلى 100.
- يسمح `auto_lang_to_font` (القيمة الافتراضية `false`) لـ mPDF باختيار خط لكل نظام كتابة، للمستندات التي تخلط العربية بالصينية أو الهندية أو أنظمة كتابة أخرى لا يحتويها خط المستند. وهو يتجاهل `font-family` في CSS الخاص بك، فاتركه مطفأً في غير ذلك.

### المستندات الكبيرة {#large-documents}

يحتفظ mPDF بالجدول كله في الذاكرة أثناء ترتيبه: قرابة 90 KB لكل صف. فتقرير من 1,000 صف يقترب من حد `memory_limit` الافتراضي في PHP وهو 128 MB، و1,500 صف تتجاوزه. ونفاد الذاكرة ينهي الطلب بخطأ fatal لا يستطيع أي محرك احتياطي التقاطه:

```text
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in vendor/mpdf/mpdf/src/Mpdf.php
```

للتقارير التي تتجاوز بضع مئات من الصفوف، إما أن ترفع الحد للكود الذي ينشئها، أو تستخدم Chromium، الذي أنشأ 2,000 صف بقرابة 50 MB من ذاكرة PHP في اختباراتنا:

```php
ini_set('memory_limit', '512M');

Doc::template('report', $data)->locale('ar')->pdf()->save('reports/2026-09.pdf', 's3');

// أو
Doc::template('report', $data)->locale('ar')->driver('chromium')->pdf()->save('reports/2026-09.pdf', 's3');
```

والـ job في الـ queue مكان مناسب للحلين؛ انظر [الإخراج والتسليم](/ar/guide/output#queue-size).

## Chromium {#chromium}

ينشئ Chromium ملفات PDF بمتصفح حقيقي عبر [Browsershot](https://github.com/spatie/browsershot) وPuppeteer.

```bash
composer require spatie/browsershot
npm install -g puppeteer
```

تثبيت Puppeteer ينزّل معه نسخة من Chrome. ويمكنك تثبيت Puppeteer داخل المشروع بدلًا من ذلك (`npm install puppeteer` في المجلد الرئيسي للتطبيق)، وتوجيه الحزمة إلى Chrome أو Chromium ثبّتّه بنفسك عبر `DOC_CHROME_PATH`.

ثم اجعله المحرك الافتراضي، أو استخدمه لكل مستند على حدة (انظر أدناه):

```dotenv
DOC_PDF_DRIVER=chromium
```

الإعدادات تحت `pdf.drivers.browsershot` في ملف الإعدادات:

| المفتاح | `.env` | القيمة الافتراضية | المعنى |
| --- | --- | --- | --- |
| `chrome_path` | `DOC_CHROME_PATH` | فارغ | مسار Chrome أو Chromium، مثل `/usr/bin/chromium`. والقيمة الفارغة تستخدم Chrome الذي نزّله Puppeteer. |
| `node_binary` | `DOC_NODE_BINARY` | فارغ | مسار `node` حين لا يكون في `PATH` الخاص بخادم الويب (شائع مع nvm). |
| `npm_binary` | `DOC_NPM_BINARY` | فارغ | مسار `npm`، بالطريقة نفسها. |
| `node_modules_path` | `DOC_NODE_MODULES_PATH` | فارغ | مجلد `node_modules` العام، أي ناتج `npm root -g`. وإن كان فارغًا يشغّل Browsershot الأمر `npm root -g` لكل مستند، وهذا يستهلك وقتًا. |
| `no_sandbox` | `DOC_CHROME_NO_SANDBOX` | `false` | تشغيل Chrome بلا sandbox. وهو لازم حين يعمل PHP بالمستخدم root، كما في كثير من صور Docker. |
| `javascript` | `DOC_CHROME_JAVASCRIPT` | `false` | تشغيل JavaScript في الصفحة. القوالب لا تحتاجها؛ فعّلها فقط للمستندات التي ترسم بها، كالرسوم البيانية. |
| `timeout` | | `60` | عدد الثواني قبل التخلي عن الإنشاء. |

إعداد نموذجي على الخادم:

```dotenv
DOC_PDF_DRIVER=chromium
DOC_CHROME_PATH=/usr/bin/chromium
DOC_NODE_MODULES_PATH=/usr/lib/node_modules
```

ينزّل Puppeteer نسخة Chrome الخاصة به في المجلد الرئيسي للمستخدم الذي شغّل `npm install`. وخادم الويب والـ queue workers يعملون عادة بمستخدم آخر (مثل `www-data`) لا يجدها. وتحديد `DOC_CHROME_PATH` لنسخة Chrome مثبتة للنظام كله يتجنب ذلك. والأخطاء التي قد تراها مذكورة في [حل المشكلات](/ar/guide/troubleshooting#chromium).

تبقى JavaScript مطفأة ما لم تفعّلها، فلا يستطيع HTML تسلل إلى بيانات المستند أن يجعل المتصفح يطلب صفحات أخرى. انظر [الأمان](/ar/guide/security).

## Gotenberg {#gotenberg}

يشغّل [Gotenberg](https://gotenberg.dev) متصفح Chrome في حاوية خاصة به وينشئ ملفات PDF عبر HTTP. فتحصل على مخرجات Chromium بلا Node ولا Chrome على خادم التطبيق.

```bash
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

```dotenv
DOC_PDF_DRIVER=gotenberg
DOC_GOTENBERG_URL=http://localhost:3000
```

ومع Docker Compose استخدم اسم الخدمة: `DOC_GOTENBERG_URL=http://gotenberg:3000`.

الإعدادات تحت `pdf.drivers.gotenberg`:

| المفتاح | `.env` | القيمة الافتراضية | المعنى |
| --- | --- | --- | --- |
| `url` | `DOC_GOTENBERG_URL` | `http://localhost:3000` | عنوان Gotenberg. |
| `javascript` | `DOC_CHROME_JAVASCRIPT` | `false` | تشغيل JavaScript في الصفحة، كما في Chromium. |
| `timeout` | | `60` | عدد ثواني انتظار رد Gotenberg. |

ترسل الحزمة الصفحة إلى المسار `/forms/chromium/convert/html` في Gotenberg. والرد الذي ليس ملف PDF (صفحة تسجيل دخول من proxy مثلًا) يُعامل على أنه فشل: `Gotenberg did not return a PDF; check DOC_GOTENBERG_URL. It returned: ...`، يليها بداية نص تلك الصفحة.

## اختيار المحرك الافتراضي {#default}

يأتي محرك التطبيق كله من `.env`:

```dotenv
DOC_PDF_DRIVER=mpdf        # mpdf, chromium or gotenberg
DOC_PDF_FALLBACK=mpdf      # used when the chosen engine fails; null turns it off
```

لا فرق بين الأحرف الكبيرة والصغيرة في أسماء المحركات، و`chrome` و`browsershot` اسمان آخران لـ `chromium`. ويُستخدم `DOC_PDF_FALLBACK` حين يفشل المحرك المختار، والقيمة `null` تلغيه.

## اختيار محرك لمستند واحد {#per-document}

تختار `->driver()` المحرك لمستند واحد:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('report', $data)->locale('ar')->driver('chromium')->pdf();
```

## المحرك الاحتياطي {#fallback}

حين لا يكون المحرك المختار مثبتًا، أو يرمي خطأ أثناء الإنشاء، يُنشأ المستند من جديد بالمحرك الاحتياطي (`DOC_PDF_FALLBACK`، وقيمته الافتراضية `mpdf`)، ويُكتب تحذير في السجل:

```text
[2026-10-08 22:33:02] production.WARNING: easy-pdf-word: [browsershot] failed, falling back to [mpdf]: The command "PATH=$PATH:/usr/local/bin:/opt/homebrew/bin NODE_PATH=`npm root -g` "node" ...
[2026-10-08 22:33:02] production.WARNING: easy-pdf-word: [gotenberg] failed, falling back to [mpdf]: cURL error 7: Failed to connect to 127.0.0.1 port 3000 ...
```

وتخبرك `->engine()` على الملف بالمحرك الذي أنشأه. ويظهر Chromium باسم `browsershot`:

```php
$pdf = Doc::template('invoice', $data)->driver('chromium')->pdf();

if ($pdf->engine() !== 'browsershot') {
    // فشل Chromium فأنشأ mPDF الملف، والسجل يذكر السبب.
}
```

ما ينبغي معرفته:

- الأخطاء في المستند نفسه، مثل خطأ في Blade أو بيانات لا تجتاز التحقق من البيانات، تُرمى كما هي. ولا يُعاد إلا ما يفشل فيه المحرك.
- اسم المحرك غير الموجود، كخطأ إملائي مثل `chromuim`، لا يُعاد بمحرك آخر: بل يرمي `InvalidArgumentException` بالرسالة `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().`
- حين يكون المحرك الاحتياطي هو المحرك المختار نفسه، أو غير مثبت هو الآخر، يصلك الخطأ الأصلي، لا رسالة عن المحرك الاحتياطي.
- ألغِ المحرك الاحتياطي بـ `DOC_PDF_FALLBACK=null`، حتى ترمي مشكلة المحرك خطأ بدلًا من إنتاج PDF بمحرك آخر.
- الإنشاء الاحتياطي يتم في الطلب أو الـ job نفسه، فيضيف وقته إلى وقت المحاولة الأولى. ضع ذلك في حسابك مع [مهلة الـ worker](/ar/guide/output#queue-timeouts).

## محركك الخاص {#custom-engine}

المحرك صنف (class) يطبّق الواجهة `BiztechEG\EasyPdfWord\Contracts\PdfDriver`:

```php
interface PdfDriver
{
    /** Turn a full HTML document into PDF bytes. */
    public function render(string $html, PdfOptions $options): string;

    /** Whether the engine's package or service is installed, so the manager can fall back before trying. */
    public function isAvailable(): bool;

    /** Whether the engine loads fonts from CSS @font-face (Chromium) rather than its own font setup (mPDF). */
    public function usesCssFonts(): bool;
}
```

هذا المحرك يرسل الصفحة إلى خدمة PDF داخلية:

```php
namespace App\Pdf;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdfServiceDriver implements PdfDriver
{
    public function __construct(private ?string $url) {}

    public function render(string $html, PdfOptions $options): string
    {
        [$width, $height] = $options->paperSize();
        [$top, $right, $bottom, $left] = $options->margins;

        $response = Http::timeout(60)->post($this->url.'/render', [
            'html'    => Watermark::inject($html, $options),
            'width'   => $width,
            'height'  => $height,
            'margins' => compact('top', 'right', 'bottom', 'left'),
            // هذه الخدمة مبنية على Chrome، فأرقام الصفحات تستخدم أصناف Chrome.
            'footer'  => str_replace(
                ['{page}', '{pages}'],
                ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
                (string) $options->footer,
            ),
        ]);

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF-')) {
            throw new RuntimeException("The PDF service returned HTTP {$response->status()}.");
        }

        return $response->body();
    }

    public function isAvailable(): bool
    {
        return ! empty($this->url);
    }

    public function usesCssFonts(): bool
    {
        return true;
    }
}
```

سجّله في service provider، ثم استخدمه باسمه:

```php
// app/Providers/AppServiceProvider.php
use App\Pdf\PdfServiceDriver;
use BiztechEG\EasyPdfWord\Facades\Doc;

public function boot(): void
{
    Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver(config('services.pdf.url')));
}
```

```php
Doc::template('invoice', $data)->driver('pdf-service')->pdf();
```

أو `DOC_PDF_DRIVER=pdf-service` للتطبيق كله. ما يتسلمه محركك وما عليه فعله:

- `$html` هو المستند كاملًا. وحين تعيد `usesCssFonts()` القيمة `true`، تضمّن الحزمة في قواعد `@font-face` خط المستند وكل خط مسجل تذكره أنماط CSS في الصفحة، فلا يحتاج المحرك المبني على متصفح إلى خطوط مثبتة. وتنزّل أيضًا الصور البعيدة المسموح بها وتضعها في الصفحة بصيغة data URI، فلا يجلب محركك أي رابط.
- يحمل `$options` الدالة `paperSize()` بالمليمتر بعد تطبيق الاتجاه، و`margins` (أعلى، يمين، أسفل، يسار بالمليمتر)، و`direction` و`locale` و`font` و`numerals` و`title` و`author` و`header` و`footer`. ويبقى في رأس الصفحة وتذييلها `{page}` و`{pages}`؛ استبدل بهما صيغة أرقام الصفحات في محركك.
- تكون `$options->watermark` محددة حين يكون للمستند علامة مائية. وتضيفها `Watermark::inject($html, $options)` إلى HTML عنصرًا ثابتًا، كما يفعل محركا Chromium.
- كلمات المرور تُعالج عنك: بعد أن يعيد محركك الملف، تشفّره الحزمة بـ mPDF.
- ارمِ استثناءً حين يفشل الإنشاء، فيتولى المحرك الاحتياطي الأمر، كما في المحركات المدمجة.
- لا فرق بين الأحرف الكبيرة والصغيرة في الأسماء: `Doc::extend('PdfService', ...)` يُعثر عليه باسم `pdfservice`.
