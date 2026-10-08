# الصور

أضف شعارًا أو توقيعًا أو ختمًا إلى مستنداتك، وتحكّم في الأماكن التي يُسمح للحزمة بقراءة الصور منها: المجلدات المحلية وروابط الصور وdata URIs وملفات SVG.

## أين توضع الصور {#where}

تقبل القوالب المرفقة الصور في عدة أماكن:

- شعار الشركة في الهوية، وتستخدمه معظم القوالب: `->theme(['logo' => ...])`؛
- `signature` و`stamp` في الخطاب الرسمي؛
- كتل `->image()` وخلايا الصور في المستندات [المبنية بالكود](/ar/guide/builder)؛
- `$doc->image()` في Blade views الخاصة بك (انظر [أدوات القوالب](/ar/reference/template-helpers))؛
- متغيرات مثل `${logo}` في قالب `word.docx` (انظر [ملفات Word](/ar/guide/word)).

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('letter', [
    'reference' => 'ص/2026/417',
    'date' => '2026-10-08',
    'recipient' => ['name' => 'المهندس أحمد عبد الرحمن', 'organization' => 'مؤسسة النور للتجارة'],
    'subject' => 'عرض تنفيذ نظام إدارة المستندات',
    'body' => 'يسعدنا أن نقدم لكم عرضنا لتنفيذ نظام إدارة المستندات الإلكترونية.',
    'sender' => ['name' => 'م. خالد حسن', 'title' => 'المدير التنفيذي'],
    'signature' => storage_path('app/signatures/khaled.png'),
    'stamp' => storage_path('app/signatures/stamp.png'),
])
    ->theme(['logo' => public_path('images/logo.png')])
    ->locale('ar')
    ->pdf();
```

تمر كل صورة بالفحوص نفسها الموضحة أدناه. والصورة التي لا تجتازها تُترك خارج المستند بلا خطأ، فلا تفسد صورة ناقصة مستندًا أبدًا. وحين تغيب صورة تتوقعها، انظر [حل المشكلات](/ar/guide/troubleshooting#images).

## المصادر المقبولة {#sources}

| المصدر | مثال | يُستخدم حين |
| --- | --- | --- |
| مسار ملف محلي | `public_path('images/logo.png')` | يكون الملف داخل مجلد مسموح به وصورة حقيقية. |
| رابط URL | `https://cdn.biztech.example/logo.png` | تكون الصور البعيدة مسموحًا بها لنطاقه. وهي ممنوعة افتراضيًا. |
| data URI | `data:image/png;base64,iVBORw0...` | يكون صورة (`data:image/...`). وعلى data URI من نوع SVG أن يجتاز [فحوص SVG](#svg) أيضًا. |

لا تُقرأ أبدًا المسارات ذات المخططات الأخرى (`file://` و`phar://` و`ftp://` و`php://`) ولا مجلدات الشبكة المشتركة (`\\server\share` و`//server/share`).

تعمل صور PNG وJPEG وGIF وWebP وBMP وSVG كلها في ملفات PDF، مع mPDF ومع Chromium. ولملفات Word قواعدها الخاصة؛ انظر [الصور في ملفات Word](#word).

## الملفات المحلية والمجلدات المسموح بها {#allowed-folders}

لا تُقرأ الصور المحلية إلا من هذه المجلدات:

```php
// config/easy-pdf-word.php
'images' => [
    'paths' => [
        public_path(),
        storage_path('app'),
        resource_path(),
    ],
    // ...
],
```

فالمسار الذي يصل ضمن بيانات المستخدم، مثل `../../.env` أو `/etc/passwd`، لا يستطيع إدخال ملفات أخرى من الخادم إلى ملف PDF. ويجب أيضًا أن يكون الملف صورة حقيقية: تفحص الحزمة محتواه لا اسمه.

أضف المجلدات التي توجد فيها صورك، كمجلد رفع مشترك مثلًا:

```php
'paths' => [
    public_path(),
    storage_path('app'),
    resource_path(),
    '/mnt/shared/uploads',
],
```

واجعل `'paths' => null` للسماح بأي مجلد. ولا تفعل ذلك إلا حين لا تأتي مسارات الصور من المستخدمين أبدًا.

الملفات على disk سحابي مثل S3 ليست مسارات محلية. مرّر رابطها (واسمح بنطاقه كما في القسم التالي)، أو اقرأها في data URI:

```php
use Illuminate\Support\Facades\Storage;

$logo = 'data:image/png;base64,'.base64_encode(Storage::disk('s3')->get('tenants/14/logo.png'));

Doc::template('invoice', $data)->theme(['logo' => $logo])->pdf();
```

## الصور البعيدة {#remote}

ينزّل خادمك رابط الصورة: ينزّله محرك PDF، أو تنزّله الحزمة لملفات Word. والرابط المأخوذ من مدخلات المستخدم قد يجعل خادمك يطلب عناوين داخلية، لذلك تُتجاهل روابط الصور ما لم تسمح بها.

اسمح بالنطاقات التي تستخدمها في `.env`:

```dotenv
DOC_REMOTE_IMAGES=cdn.biztech.example,*.amazonaws.com
```

أو في ملف الإعدادات، في صورة مصفوفة:

```php
'images' => [
    'remote' => ['cdn.biztech.example', '*.amazonaws.com'],
],
```

- لا فرق بين الأحرف الكبيرة والصغيرة في النطاقات. ويطابق `*.amazonaws.com` النطاق `bucket.s3.amazonaws.com`، لكنه لا يطابق `amazonaws.com` نفسه.
- يسمح `DOC_REMOTE_IMAGES=true` بأي رابط. ولا تستخدمه إلا حين لا تأتي روابط الصور من المستخدمين أبدًا.
- تتجاهل القيمة `false` (الافتراضية) كل الروابط.

إعادة التوجيه والخوادم البطيئة:

- لا يتبع mPDF ولا ملفات Word إعادة التوجيه (redirect)، فلا يقود رابط على نطاق مسموح به إلى نطاق آخر. والرابط الذي يعيد التوجيه يُظهر في ملف PDF أيقونة mPDF الصغيرة "image not found"، ولا يظهر شيئًا في ملف Word. استخدم الرابط النهائي.
- ينتظر mPDF وملفات Word الصورة 10 ثوانٍ على الأكثر.
- يحمّل Chromium وGotenberg الصور كما يحمّلها المتصفح، ويتبعان إعادة التوجيه. فلا تسمح إلا بالنطاقات التي تثق في إعادة توجيهها.

## صور SVG {#svg}

تعمل شعارات SVG وأختامها في ملفات PDF من كل المحركات، ملفاتٍ أو data URIs، حين يكون ملف SVG مكتفيًا بنفسه. أما SVG الذي قد يشير إلى خارجه فيُترك، لأن المحرك سيحمّل ما يشير إليه. وترفض الحزمة ملف SVG يحتوي:

- `href` أو `src` لا يشير إلى جزء من ملف SVG نفسه (`href="#shape"` مقبول، و`href="logo.png"` غير مقبول)؛
- `url(...)` ليس إشارة من هذا النوع (`url(#gradient)` مقبول)؛
- العناصر `<image>` أو `<script>` أو `<foreignObject>` أو `<feImage>`، حتى لو كانت بياناتها مضمنة؛
- `@import` أو `<!DOCTYPE>` أو entities أو سطر `<?xml-stylesheet ...?>`.

تضيف بعض أدوات التصميم سطر `<!DOCTYPE>` أو تضمّن صورًا نقطية بالعنصر `<image>`. فإن تُرك ملف SVG الخاص بك، افتحه في محرر نصوص وابحث عن هذه الأشياء، أو صدّره من جديد بصيغة SVG عادية، أو استخدم PNG.

## الصور في ملفات Word {#word}

يعرض Word صور JPEG وPNG وGIF. وتحوّل الحزمة صور WebP وBMP إلى PNG بإضافة GD في PHP.

تُترك صور SVG خارج ملفات Word، لأن PHP لا يستطيع رسمها. فإن كنت تنشئ ملفات Word، استخدم شعارًا وتوقيعًا وختمًا بصيغة PNG أو JPEG:

```php
$document = Doc::template('invoice', $data)
    ->theme(['logo' => public_path('images/logo.png')])   // PNG يعمل في PDF وWord
    ->locale('ar');

$document->pdf()->save('invoices/INV-2026-1024.pdf');
$document->word()->save('invoices/INV-2026-1024.docx');
```

وتنطبق المجلدات والنطاقات المسموح بها نفسها على ملفات Word.
