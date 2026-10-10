# واجهة Doc

كل الفئات والدوال والخصائص العامة في المكتبة بتوقيعاتها الدقيقة. يشرح [الدليل](/ar/guide/introduction) طريقة إنجاز كل مهمة، أما هذه الصفحة فتجمع كل شيء في مكان واحد لتبحث فيها عن اسم دالة أو معامل أو قيمة افتراضية.

كل الفئات تحت مساحة الأسماء `BiztechEG\EasyPdfWord`. الدوال الموسومة بأنها *داخلية* عامة لأسباب تقنية فقط، فلا تستدعها من تطبيقك.

## كيف تترابط الأجزاء {#overview}

```text
Doc::template() / Doc::view() / Doc::html() / Doc::make()
        │
        ▼
PendingDocument          settings: ->locale() ->theme() ->paper() ... and blocks for Doc::make()
        │
        ├── ->pdf()      → PdfDocument   ┐
        ├── ->word()     → WordDocument  ├─ RenderedFile: download, stream, save, content, mail attachment
        │   Doc::zip()   → ZipFile       ┘
        ├── ->queue()    → SaveDocument job (Laravel PendingDispatch)
        └── ->toHtml()   → the HTML handed to the PDF engine

Doc::fake()              → DocFake, which records a GeneratedDocument for every file
```

| الفئة | الاسم الكامل | من أين تحصل عليها |
| --- | --- | --- |
| الـ facade `Doc` | `BiztechEG\EasyPdfWord\Facades\Doc` | استوردها، أو استخدم الاسم المختصر `Doc` |
| `DocFactory` | `BiztechEG\EasyPdfWord\DocFactory` | الكائن الذي يقف خلف الـ facade (نسخة واحدة singleton) |
| `PendingDocument` | `BiztechEG\EasyPdfWord\PendingDocument` | `Doc::template()` و`view()` و`html()` و`make()` |
| `DocumentBuilder` | `BiztechEG\EasyPdfWord\Builder\DocumentBuilder` | `Doc::make()` والمعامل الأول في `layout.php` و`word.php` |
| `PdfDocument` و`WordDocument` | `BiztechEG\EasyPdfWord\PdfDocument` و`...\WordDocument` | `->pdf()` و`->word()` |
| `ZipFile` | `BiztechEG\EasyPdfWord\ZipFile` | `Doc::zip()` |
| `SaveDocument` | `BiztechEG\EasyPdfWord\Jobs\SaveDocument` | `->queue()` |
| `DocFake` و`GeneratedDocument` | `BiztechEG\EasyPdfWord\Testing\...` | `Doc::fake()` |
| `TemplateRegistry` و`Template` | `BiztechEG\EasyPdfWord\Templates\...` | `Doc::templates()` |
| `FontRegistry` | `BiztechEG\EasyPdfWord\Fonts\FontRegistry` | `Doc::fonts()` |
| `PdfManager` و`PdfOptions` | `BiztechEG\EasyPdfWord\Pdf\...` | `Doc::pdfManager()`، ودالة `render()` في محرك PDF |
| `PdfDriver` | `BiztechEG\EasyPdfWord\Contracts\PdfDriver` | تطبّقها لتكتب محركك الخاص |

الأدوات التي تستخدمها أثناء كتابة القالب (`$doc` في Blade و`Arabic` و`ZatcaQr` ...) موجودة في صفحة [أدوات القوالب](/ar/reference/template-helpers).

## الـ facade Doc {#doc}

يستدعي `Doc` الكائن `DocFactory` من الحاوية. تسجّل المكتبة الاسم المختصر `Doc` عبر اكتشاف الحزم التلقائي (package discovery)، فيعمل `Doc::` داخل Blade وفي `tinker` أيضًا. أما داخل الفئات فاستورد الـ facade:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
```

### template() {#doc-template}

```php
Doc::template(string $name, array $data = []): PendingDocument
```

يبدأ مستندًا من قالب: أحد القوالب المرفقة بالمكتبة (`invoice` و`receipt` ...) أو مجلد داخل الإعداد `templates.paths`. يُبحث في مجلدات مشروعك أولًا، فالقالب الذي تنسخه يحل محل القالب الأصلي. المعامل `$data` يكافئ استدعاء `->data($data)`. يرمي [`TemplateNotFound`](#exceptions) فورًا إن لم يوجد مجلد بهذا الاسم.

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$data = [
    'invoice' => ['number' => 'INV-2026-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'buyer'   => ['name' => 'مؤسسة النور'],
    'items'   => [
        ['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000],
    ],
];

return Doc::template('invoice', $data)->locale('ar')->pdf()->download('فاتورة-1024.pdf');
```

الأمثلة التالية تعيد استخدام المتغير `$data` هذا.

### view() {#doc-view}

```php
Doc::view(string $view, array $data = []): PendingDocument
```

يبدأ ملف PDF من أي view في تطبيقك. يستقبل الـ view بياناتك ومعها `$doc` (كائن [`DocContext`](/ar/reference/template-helpers#doc-context))، فضع محتواه داخل `x-doc::layout`. ملفات Blade تنتج PDF فقط، واستدعاء `->word()` يرمي [`WordNotSupported`](#exceptions).

```php
Doc::view('pdf.contract', ['contract' => $contract])->locale('ar')->pdf()->save('contracts/1024.pdf', 's3');
```

### html() {#doc-html}

```php
Doc::html(string $html): PendingDocument
```

يبدأ ملف PDF من نص HTML. النص الذي يحتوي على وسم `<html>` يُستخدم كما هو، وأي نص آخر يوضع داخل التخطيط الجاهز في المكتبة فيأخذ الاتجاه والخط والتنسيقات الأساسية. ينتج PDF فقط. يُعامل هذا النص على أنه موثوق، فلا تمرر إليه أبدًا مدخلات المستخدمين.

```php
return Doc::html('<h1>مرحبا</h1><p>إيصال استلام رقم 77</p>')->locale('ar')->pdf()->stream();
```

### make() {#doc-make}

```php
Doc::make(): PendingDocument
```

يبدأ مستندًا فارغًا تبنيه كتلة بعد كتلة بدوال [`DocumentBuilder`](#document-builder). الكتل نفسها تنتج PDF و Word.

```php
$report = Doc::make()
    ->heading('تقرير المبيعات')
    ->table([['الفرع', 'المبيعات'], ['القاهرة', '486,500.75']], ['header' => true])
    ->locale('ar');

$report->pdf()->save('reports/sales.pdf');
$report->word()->save('reports/sales.docx');
```

### zip() {#doc-zip}

```php
Doc::zip(array $files, string $filename = 'documents.zip'): ZipFile
```

يجمع عدة ملفات من `->pdf()` أو `->word()` أو `Doc::zip()` في أرشيف ZIP واحد. استخدم مفتاحًا نصيًا لتغيير اسم ملف داخل الأرشيف. راجع [ZipFile](#zip-file). يحتاج امتداد PHP المسمى `zip`.

```php
$invoice = Doc::template('invoice', $data)->locale('ar');

return Doc::zip([
    $invoice->pdf('فاتورة-1024.pdf'),
    $invoice->word('فاتورة-1024.docx'),
], 'order-1024.zip')->download();
```

### extend() {#doc-extend}

```php
Doc::extend(string $driver, Closure $callback): DocFactory
```

يسجّل محرك PDF خاصًا بك تحت اسم تختاره. تستقبل الدالة حاوية التطبيق وتعيد كائن [`PdfDriver`](#pdf-driver). لا يُفرّق بين الأحرف الكبيرة والصغيرة في الأسماء. تعيد الـ factory نفسه فيمكن تسلسل الاستدعاءات. استدعها في دالة `boot()` داخل service provider.

```php
Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver((string) config('services.pdf.url')));
```

### fake() {#doc-fake}

```php
Doc::fake(): DocFake
```

يستبدل الـ facade بكائن [`DocFake`](#doc-fake-class): تُبنى المستندات ويُتحقق من بياناتها، لكن لا يعمل أي محرك ولا يُكتب أو يُرسل شيء. استخدمه في الاختبارات ثم استدع [دوال التحقق](#doc-fake-class) على `Doc`.

```php
Doc::fake();

$this->get('/invoices/1024/download')->assertOk();

Doc::assertDownloaded('فاتورة-1024.pdf');
```

### templates() {#doc-templates}

```php
Doc::templates(): TemplateRegistry
```

السجل الذي يبحث عن القوالب. راجع [TemplateRegistry](#template-registry).

```php
$sample = Doc::templates()->get('invoice')->sample();   // البيانات التجريبية للقالب
```

### fonts() {#doc-fonts}

```php
Doc::fonts(): FontRegistry
```

الخطوط التي تعرفها المكتبة: Cairo و Tajawal و Naskh والخطوط المسجلة في `fonts.custom`. راجع [FontRegistry](#font-registry).

### pdfManager() {#doc-pdf-manager}

```php
Doc::pdfManager(): PdfManager
```

المدير الذي يجلب محركات PDF بأسمائها ويتولى الانتقال إلى المحرك الاحتياطي. راجع [PdfManager](#pdf-manager).

## PendingDocument {#pending-document}

الكائن `BiztechEG\EasyPdfWord\PendingDocument` مستند قيد الإعداد. كل دالة إعداد تعيد الكائن نفسه (`static`)، فتتسلسل الاستدعاءات بأي ترتيب. لا يُنشأ الملف فعليًا حتى تطلب محتواه. أدلة ذات صلة: [إعدادات الصفحة](/ar/guide/page-settings) و[الإخراج والتسليم](/ar/guide/output).

| المجموعة | الدوال |
| --- | --- |
| البيانات | [`data`](#pending-data) و[`with`](#pending-with) و[`withoutValidation`](#pending-without-validation) |
| اللغة والأرقام | [`locale`](#pending-locale) و[`direction`](#pending-direction) و[`rtl` / `ltr`](#pending-rtl-ltr) و[`numerals`](#pending-numerals) |
| المظهر | [`theme`](#pending-theme) و[`font`](#pending-font) و[`title`](#pending-title) |
| الصفحة | [`paper`](#pending-paper) و[`landscape` / `portrait`](#pending-landscape-portrait) و[`margins`](#pending-margins) و[`header`](#pending-header) و[`footer`](#pending-footer) |
| لملفات PDF فقط | [`driver`](#pending-driver) و[`watermark`](#pending-watermark) و[`password`](#pending-password) |
| الإخراج | [`pdf`](#pending-pdf) و[`word`](#pending-word) و[`queue`](#pending-queue) و[`toHtml`](#pending-to-html) و[`options`](#pending-options) |
| كتل `Doc::make()` | [`heading` و`paragraph` و`table` ...](#pending-blocks) |

إن لم تستدع دالة إعداد، تؤخذ القيمة من ملف `template.php` الخاص بالقالب أو من `config/easy-pdf-word.php`:

| الإعداد | القيمة الافتراضية |
| --- | --- |
| اللغة | `easy-pdf-word.locale`، وإلا `app.locale` |
| الاتجاه | من اللغة: `rtl` للغات `ar` و`fa` و`ur` و`he` ...، و`ltr` لغيرها |
| الأرقام | `easy-pdf-word.numerals` (`latin`) |
| الخط | `fonts.default` (`cairo`) للمستندات من اليمين إلى اليسار، و`fonts.default_ltr` (`cairo`) لغيرها |
| مقاس الورق والاتجاه والهوامش | `template.php`، ثم `pdf.paper` (`A4`) و`pdf.orientation` (`portrait`) و`pdf.margins` (`[15, 15, 15, 15]`) |
| رأس الصفحة وتذييلها | الملفان `header.blade.php` و`footer.blade.php` في مجلد القالب |
| العنوان | المفتاح `title` في `template.php` |
| الهوية | `easy-pdf-word.theme`، ثم `theme` في `template.php`، ثم `->theme()` |
| المحرك | `pdf.driver` (`DOC_PDF_DRIVER`، والافتراضي `mpdf`) |

### data() {#pending-data}

```php
data(array $data): static
```

يحدد بيانات القالب أو الـ view. كل استدعاء يستبدل المفاتيح العليا: `->data(['buyer' => [...]])` يستبدل المصفوفة `buyer` كاملة ويُبقي باقي المفاتيح. في القوالب تتحول نماذج Eloquent والـ collections إلى مصفوفات، ثم تُدمج فوق `defaults` الخاصة بالقالب، ويُتحقق منها بقواعد `fields`، وتمر على دالة `prepare`.

```php
Doc::template('invoice')->data($data)->data(['buyer' => ['name' => 'شركة الأفق للتجارة']]);
```

### with() {#pending-with}

```php
with(string|array $key, mixed $value = null): static
```

مثل `data()` لمفتاح واحد: `->with('qr', 'zatca')` يساوي `->data(['qr' => 'zatca'])`. وإن مررت مصفوفة عملت مثل `data()`.

### withoutValidation() {#pending-without-validation}

```php
withoutValidation(): static
```

يتخطى قواعد التحقق `fields` الخاصة بالقالب، مع بقاء `defaults` و`prepare`. استخدمه فقط إن كانت بياناتك متحققًا منها مسبقًا وكانت إحدى القواعد تعترض طريقك.

### locale() {#pending-locale}

```php
locale(string $locale): static
```

يحدد لغة المستند: نصوص القالب (`lang/{language}.php`)، والمبلغ كتابةً، والاتجاه ما لم تستدع `->direction()`. يقبل أسماء مثل `ar` و`en` و`ar_EG` و`ar-SA`، ويرمي `InvalidArgumentException` لأي قيمة أخرى (مسارات أو وسوم).

```php
Doc::template('invoice', $data)->locale('ar_EG');   // عربي، من اليمين إلى اليسار، بنصوص عربية
```

### direction() {#pending-direction}

```php
direction(string $direction): static
```

يفرض الاتجاه: القيمة `'rtl'` تعني من اليمين إلى اليسار، وأي قيمة أخرى تعني من اليسار إلى اليمين. بدونها يتبع الاتجاه اللغة.

### rtl() / ltr() {#pending-rtl-ltr}

```php
rtl(): static
ltr(): static
```

اختصار لـ `->direction('rtl')` و`->direction('ltr')`.

### numerals() {#pending-numerals}

```php
numerals(string $style): static
```

القيمة `'arabic'` تطبع الأرقام العربية (١٢٣) في نص المستند، و`'latin'` تطبع الأرقام اللاتينية (123). يقبل أيضًا `arab` و`ar` و`eastern` و`hindi` (للعربية) و`latn` و`en` و`western` (للاتينية)، ويرمي `InvalidArgumentException` لغيرها. يُحوَّل النص فقط: الوسوم والخصائص و CSS وعناوين البريد والروابط تحتفظ بأرقامها. مع خط Naskh تصبح الفواصل ٫ و٬، أما Cairo و Tajawal وملفات Word فتُبقي `.` و`,`.

```php
Doc::template('invoice', $data)->locale('ar')->numerals('arabic');   // INV-٢٠٢٦-١٠٢٤
```

### theme() {#pending-theme}

```php
theme(array $theme): static
```

الألوان والشعار وبيانات الشركة لهذا المستند، وتُدمج فوق `theme` في الإعدادات (والاستدعاءات اللاحقة فوق السابقة). المفاتيح التي تقرؤها القوالب المرفقة: `primary` و`text` و`muted` و`border` (ألوان)، و`logo` (مسار صورة أو رابط أو data URI)، و`company` وفيها `name` و`address` و`phone` و`email` و`tax_number`. اللون غير الصالح في CSS يُستبدل بالقيمة الافتراضية. ويصبح `company.name` أيضًا اسم مؤلف ملف PDF.

```php
Doc::template('invoice', $data)->theme([
    'primary' => '#1D4ED8',
    'logo'    => public_path('images/logo.png'),
    'company' => ['name' => 'شركة بيزتك', 'phone' => '+20 100 000 0000', 'tax_number' => '123-456-789'],
]);
```

### font() {#pending-font}

```php
font(string $font): static
```

اسم خط من الإعدادات: `cairo` أو `tajawal` أو `naskh` أو خط سجلته في `fonts.custom`. يُحوَّل الاسم إلى أحرف صغيرة. يقبل الحروف والأرقام والمسافات و`-` و`_` فقط، ويرمي `InvalidArgumentException` لغير ذلك. ملفات Word تتجاهله وتستخدم الإعداد `word.font`.

### title() {#pending-title}

```php
title(string $title): static
```

العنوان المحفوظ في خصائص الملف: ملف PDF، وملفات Word المبنية من الكتل أو من ملف تخطيط (أما `word.docx` فيحتفظ بعنوانه). وفي ملف PDF يحل محل `<title>` الخاص بالصفحة. وبدونه يحتفظ ملف PDF بعنوان صفحته، والقالب الذي ليس لصفحته عنوان يأخذ `title` القالب.

### paper() {#pending-paper}

```php
paper(string|array $paper, ?string $orientation = null): static
```

مقاس الورق: `A2` و`A3` و`A4` و`A5` و`A6` و`B4` و`B5` و`Letter` و`Legal` و`Tabloid` و`Executive` (بأي حالة أحرف)، أو `[width, height]` بالمليمتر. القيمتان `A4-L` و`A4-P` تحددان الاتجاه أيضًا. المعامل `$orientation` إما `'landscape'` (عرضي) أو `'portrait'` (طولي). الاسم غير المعروف، أو المصفوفة التي ليست عددين موجبين، يرمي `InvalidArgumentException`.

```php
Doc::template('receipt', $receipt)->paper('A5', 'landscape');
Doc::template('report', $report)->paper('A4-L');
Doc::view('pdf.till-receipt', ['order' => $order])->paper([80, 200]);
```

### landscape() / portrait() {#pending-landscape-portrait}

```php
landscape(): static
portrait(): static
```

يحدد اتجاه الصفحة (عرضي أو طولي) مع بقاء مقاس الورق.

### margins() {#pending-margins}

```php
margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
```

هوامش الصفحة بالمليمتر بأسلوب CSS: قيمة واحدة لكل الجهات، أو قيمتان (أعلى وأسفل ثم يمين ويسار)، أو أربع قيم لكل جهة.

```php
->margins(15)          // 15 on every side
->margins(15, 12)      // top and bottom 15, right and left 12
->margins(20, 15, 25, 15)
```

### header() {#pending-header}

```php
header(string $html): static
```

HTML يُطبع أعلى كل صفحة (رأس الصفحة). يتحول `{page}` و`{pages}` إلى رقم الصفحة وعدد الصفحات. يحل محل `header.blade.php` في القالب. في ملفات Word يتحول HTML إلى نص عادي مع حقول أرقام الصفحات.

### footer() {#pending-footer}

```php
footer(string $html): static
```

مثل السابق أسفل كل صفحة (تذييل الصفحة)، ويحل محل `footer.blade.php` في القالب.

```php
->footer('<div style="text-align: center; font-size: 8pt;">صفحة {page} من {pages}</div>')
```

::: info معلومة
يطبع Chromium و Gotenberg قيمتي `{page}` و`{pages}` بالأرقام اللاتينية حتى مع `->numerals('arabic')`، بينما يتبع mPDF إعداد الأرقام. أما في ملفات Word فهما حقلان لأرقام الصفحات يملؤهما Word بنفسه، فلا ينطبق عليهما إعداد الأرقام.
:::

### driver() {#pending-driver}

```php
driver(string $driver): static
```

محرك PDF لهذا المستند: `mpdf` أو `chromium` (أو `browsershot` أو `chrome`) أو `gotenberg` أو اسم سجلته بـ `Doc::extend()`. إن لم يكن المحرك مثبتًا أو فشل، ينشئ المحرك الاحتياطي `pdf.fallback` الملف ويُسجَّل تحذير في السجلات. وأي اسم آخر يرمي `InvalidArgumentException` عند إنشاء ملف PDF: `Unknown PDF engine [chromuim]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().`

### watermark() {#pending-watermark}

```php
watermark(string $text, float $opacity = 0.12, string $color = '#000000'): static
```

يطبع نصًا كبيرًا مائلًا على كل صفحات ملف PDF، مثل `مسودة` أو `نسخة`. يُقص النص إلى 100 حرف، وتُحصر الشفافية بين 0.01 و1، واللون غير الصالح يصبح أسود. النص الفارغ يرمي `InvalidArgumentException`. ملفات Word تُنشأ بدون العلامة المائية.

```php
Doc::template('quotation', $quotation)->watermark('مسودة', opacity: 0.08, color: '#B91C1C')->pdf();
```

### password() {#pending-password}

```php
password(string $user, ?string $owner = null, array $allow = ['print', 'print-highres', 'copy']): static
```

يشفّر ملف PDF. تُطلب كلمة المرور `$user` عند فتح الملف (والقيمة `''` تفتحه بدون كلمة مرور). كلمة مرور المالك `$owner` تفتح كل الصلاحيات، وإن كانت `null` تُستخدم كلمة عشوائية فتبقى القيود سارية. يحدد `$allow` ما يُسمح للقارئ به، من بين `PendingDocument::PERMISSIONS`:

```php
public const PERMISSIONS = ['print', 'print-highres', 'copy', 'modify', 'annot-forms', 'fill-forms', 'extract', 'assemble'];
```

الصلاحية غير المعروفة ترمي `InvalidArgumentException`. ملفات Word لا تقبل التشفير، لذا يرمي `->word()` و`->queue('….docx')` الاستثناء `LogicException` لمستند له كلمة مرور. مع Chromium أو Gotenberg يشفّر mPDF الملف بعد إنشائه، فيجب تثبيت `mpdf/mpdf`.

```php
// Opens freely, but readers may only print it.
Doc::template('receipt', $receipt)->password('', owner: 'office-2026', allow: ['print'])->pdf();
```

### pdf() {#pending-pdf}

```php
pdf(?string $filename = null): PdfDocument
```

يعيد ملف PDF ككائن [`PdfDocument`](#rendered-files). المعامل `$filename` هو الاسم المستخدم في `download()` و`stream()` ومرفقات البريد، والافتراضي اسم القالب (`invoice.pdf`) أو `document.pdf`. تُنسخ الإعدادات لحظة الاستدعاء ويُتحقق من بيانات القالب فورًا، أما الملف نفسه فيُنشأ عند أول حاجة إلى محتواه.

### word() {#pending-word}

```php
word(?string $filename = null): WordDocument
```

يعيد ملف Word ككائن [`WordDocument`](#rendered-files)، مبنيًا من `word.docx` أو `word.php` أو `layout.php` في القالب، أو من كتل `Doc::make()`. الاسم الافتراضي `invoice.docx` أو `document.docx`. وكما في `pdf()`، يجري التحقق من بيانات القالب في هذه اللحظة، ويُنشأ الملف حين تُطلب بياناته أول مرة. يرمي [`WordNotSupported`](#exceptions) لملفات Blade و HTML والقوالب التي لا تخطيط Word لها، و`LogicException` إن وُجدت كلمة مرور. يحتاج `phpoffice/phpword`.

### queue() {#pending-queue}

```php
queue(string $path, ?string $disk = null): PendingDispatch
```

يُنشئ الملف ويحفظه في الـ queue بدلًا من أثناء الطلب. الامتداد يحدد الصيغة: `.pdf` أو `.docx` (وأي امتداد آخر يرمي `InvalidArgumentException`). يُتحقق من بيانات القالب قبل إضافة الـ job. يعيد `Illuminate\Foundation\Bus\PendingDispatch` من Laravel، فتعمل `->onQueue()` و`->onConnection()` و`->delay()` و`->chain()`. الـ job هو [`SaveDocument`](#save-document).

```php
Doc::template('invoice', $data)->locale('ar')
    ->queue('invoices/INV-2026-1024.pdf', 's3')
    ->onQueue('documents');
```

### toHtml() {#pending-to-html}

```php
toHtml(?PdfDriver $engine = null, ?PdfOptions $options = null): string
```

نص HTML النهائي الذي يستلمه محرك PDF بعد تحويل الأرقام. مفيد لتتبع أخطاء القالب أو لمعاينة سريعة. المعاملان اختياريان، والافتراضي محرك المستند وإعداداته.

### options() {#pending-options}

```php
options(): PdfOptions
```

إعدادات الصفحة والمستند النهائية ككائن [`PdfOptions`](#pdf-options)، أي ما سيستلمه المحرك. يتحقق من بيانات القالب.

```php
$options = Doc::template('receipt', $receipt)->options();
$options->paper;        // 'A5'
$options->orientation;  // 'landscape'
```

### كتل البناء على المستند {#pending-blocks}

المستند الناتج من `Doc::make()` يقبل أيضًا كل دوال الكتل في [`DocumentBuilder`](#document-builder) (`heading` و`paragraph` و`table` و`image` و`qr` و`spacer` و`pageBreak` و`line`) ويعيد نفسه، فتمتزج الكتل والإعدادات في سلسلة واحدة. أما على مستند من قالب أو view أو HTML فترمي هذه الدوال `BadMethodCallException`.

### الدوال الداخلية {#pending-internal}

الدوال `forTemplate()` و`forView()` و`forHtml()` و`forBuilder()` (منشئات ثابتة يستخدمها `Doc`) و`recordTo()` (يستخدمها `Doc::fake()`) و`toQueue()` و`fromQueue()` (يستخدمها الـ job) كلها *داخلية*.

ترث `PendingDocument` الفئة `BiztechEG\EasyPdfWord\Document`، وهي المستند نفسه دون Laravel؛ وتضيف `PendingDocument` الدالة `->queue()` والتسجيل في `Doc::fake()` وقبول مجموعات Laravel ونماذجه بيانات.

## DocumentBuilder {#document-builder}

الكائن `BiztechEG\EasyPdfWord\Builder\DocumentBuilder` يصف المستند على شكل كتل. تستخدمه عبر `Doc::make()`، وتستلمه معاملًا أول في ملف `layout.php` أو `word.php` للقالب. كل دالة كتلة تعيد الكائن نفسه. الدليل: [بناء المستند بالكود](/ar/guide/builder).

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::make()
    ->heading('عرض أسعار الخدمات')
    ->paragraph([['text' => 'العميل: ', 'bold' => true], 'مؤسسة النور'])
    ->table([
        ['الخدمة', 'السعر'],
        ['تصميم الهوية', '8,000.00'],
        ['تطوير الموقع', '25,000.00'],
    ], ['header' => true, 'columns' => [70, ['width' => 30, 'align' => 'end']]])
    ->qr('https://biztech.example/q/2026-77', 25, 'end')
    ->locale('ar')
    ->pdf();
```

### heading() {#builder-heading}

```php
heading(string $text, int $level = 1, array $style = []): static
```

عنوان بالمستوى 1 أو 2 أو 3 (والقيم الأخرى تُحصر في هذا المدى): بحجم 18 و14 و12 نقطة وبخط عريض. المستوى 1 بلون `primary` من الهوية. يبقى العنوان دائمًا في الصفحة نفسها مع الكتلة التي تليه. يقبل `$style` [تنسيقات النص](#builder-styles).

### paragraph() {#builder-paragraph}

```php
paragraph(string|array $text, array $style = []): static
```

فقرة. المعامل `$text` نص، أو قائمة مقاطع: كل مقطع نص أو مصفوفة فيها `text` وتنسيقات المقطع (`bold` و`italic` و`size` و`color` و`ltr`). ينطبق `$style` على الفقرة كاملة، ويقبل أيضًا `space_after` (بالمليمتر) و`line_height` (القيمة `1.5` تعني سطرًا ونصفًا). فواصل الأسطر في النص تبقى كما هي.

```php
->paragraph([['text' => 'المبلغ: ', 'bold' => true], '1,250.00 ج.م'], ['align' => 'end', 'space_after' => 4])
```

### table() {#builder-table}

```php
table(array $rows, array $options = []): static
```

جدول. `$rows` قائمة صفوف، وكل صف قائمة [خلايا](#builder-cells). تجد `$options` في [خيارات الجدول](#builder-table-options).

### image() {#builder-image}

```php
image(string $source, float $widthMm = 40, string $align = 'start'): static
```

صورة من مسار ملف أو رابط أو data URI، بعرض `$widthMm` مليمتر، ومحاذاة `start` أو `center` أو `end`. تنطبق [قواعد الصور](/ar/guide/images): الملفات المحلية من المجلدات المسموحة فقط، والروابط من النطاقات المسموحة فقط، والصورة غير المسموحة تُترك. ملفات Word تقبل JPEG و PNG و GIF (وتُحوَّل WebP و BMP، وتُترك SVG).

### qr() {#builder-qr}

```php
qr(string $value, float $sizeMm = 30, string $align = 'start'): static
```

رمز QR للقيمة `$value` (رابط، أو بيانات هيئة الزكاة ...) بعرض `$sizeMm` مليمتر.

### spacer() {#builder-spacer}

```php
spacer(float $heightMm = 5): static
```

مسافة رأسية فارغة.

### pageBreak() {#builder-page-break}

```php
pageBreak(): static
```

يبدأ صفحة جديدة.

### line() {#builder-line}

```php
line(?string $color = null): static
```

خط أفقي رفيع بلون `border` من الهوية ما لم تحدد لونًا.

### blocks() / isEmpty() {#builder-blocks}

```php
blocks(): array
isEmpty(): bool
```

الكتل المضافة حتى الآن كمصفوفات فيها المفتاح `type`، وهل القائمة فارغة. متاحتان على كائن البناء في `layout.php`، ولا يمررهما مستند `Doc::make()`.

### تنسيقات النص {#builder-styles}

تقبل العناوين والفقرات والمقاطع وخلايا الجداول هذه المفاتيح:

| المفتاح | القيمة | ملاحظات |
| --- | --- | --- |
| `bold` | `true` | خط عريض |
| `italic` | `true` | خط مائل |
| `size` | بالنقطة، مثل `9.5` | |
| `color` | `#hex` أو `rgb()` أو `hsl()` (أو اسم لون في PDF) | يترك Word أسماء الألوان ويُسقط الشفافية من `#RRGGBBAA` |
| `align` | `start` أو `end` أو `center` أو `justify` | `start` يمين في المستندات العربية ويسار في الإنجليزية. لا ينطبق على المقطع الواحد |
| `ltr` | `true` | يحافظ على ترتيب رقم الهاتف أو الكود أو البريد من اليسار إلى اليمين داخل النص العربي |
| `background` | `#hex` | لخلايا الجداول فقط |
| `space_after` | بالمليمتر | للفقرات فقط |
| `line_height` | مثل `1.5` | للفقرات فقط |
| `font` | اسم خط، مثل `Tahoma` | لملفات Word فقط، وتستخدم ملفات PDF خط المستند |

### خيارات الجدول {#builder-table-options}

| الخيار | الافتراضي | الأثر |
| --- | --- | --- |
| `columns` | `[]` | عنصر لكل عمود: نسبة العرض المئوية (`30`)، أو `['width' => 30, 'align' => 'end']` |
| `header` | `false` | الصف الأول صف عناوين: عريض وملون ويتكرر أعلى كل صفحة |
| `header_background` | `primary` من الهوية | خلفية صف العناوين |
| `header_color` | `#FFFFFF` | لون نص صف العناوين |
| `borders` | `true` | خط أسفل كل خلية |
| `border_color` | `border` من الهوية | لون هذه الخطوط |
| `striped` | `null` | لون خلفية `#hex` لصف وترك صف |
| `footer` | `false` | الصف الأخير عريض (صف الإجماليات) |
| `font_size` | `null` | حجم نص الجدول كله بالنقطة |

### خلايا الجدول {#builder-cells}

الخلية نص، أو مصفوفة فيها هذه المفاتيح مع أي [تنسيق نص](#builder-styles):

| المفتاح | المحتوى |
| --- | --- |
| `text` | نص |
| `lines` | عدة فقرات: كل واحدة نص، أو مقطع منسق (`['text' => ..., 'bold' => true, 'align' => 'center']`)، أو قائمة مقاطع، أو `['image' => $path, 'width' => 30]` |
| `image` | مسار صورة أو رابط أو data URI، مع `width` بالمليمتر (الافتراضي 30) |
| `qr` | قيمة تُرسم كرمز QR، مع `width` بالمليمتر (الافتراضي 30) |
| `colspan` | عدد الأعمدة التي تمتد عليها الخلية |
| `border` | لون `#hex`: إطار حول هذه الخلية |

```php
->table([
    [['text' => 'الإجمالي', 'colspan' => 2, 'bold' => true], ['text' => '28,500.00', 'ltr' => true]],
    [['lines' => ['شركة بيزتك', ['text' => '+20 100 000 0000', 'ltr' => true, 'color' => '#6B7280']]], ['qr' => 'https://biztech.example/i/1024', 'width' => 22], ''],
], ['borders' => false, 'columns' => [50, 25, 25]])
```

## الملفات الناتجة {#rendered-files}

ترث `PdfDocument` و`WordDocument` و`ZipFile` الفئة المجردة `BiztechEG\EasyPdfWord\RenderedFile`، التي تطبّق الواجهتين `Attachable` (للبريد) و`Responsable` (لردود الـ controller) من Laravel. يُنشأ الملف مرة واحدة عند أول استدعاء يحتاج إلى محتواه. وترث `RenderedFile` الفئة `BiztechEG\EasyPdfWord\Output\File`، وهي الملف دون Laravel.

| الدالة | تعيد | الوصف |
| --- | --- | --- |
| `content(): string` | البايتات | ينشئ الملف (مرة واحدة) ويعيد محتواه |
| `toString(): string` | البايتات | مثل `content()` |
| `base64(): string` | نص | المحتوى بترميز base64 (لواجهات API) |
| `engine(): string` | اسم | ما أنشأ الملف: `mpdf` أو `browsershot` أو `gotenberg` أو اسم محركك (وبعد الانتقال إلى المحرك الاحتياطي اسمه هو)، و`phpword` أو `docx-template` لملفات Word، و`zip`، و`fake` مع `Doc::fake()` |
| `filename(): string` | اسم | اسم الملف مع امتداده |
| `mimeType(): string` | نوع | `application/pdf` أو نوع Word أو `application/zip` |
| `extension(): string` | `pdf` أو `docx` أو `zip` | |
| `download(?string $filename = null): Response` | رد | يرسل الملف للتنزيل |
| `stream(?string $filename = null): Response` | رد | يعرض الملف في المتصفح |
| `inline(?string $filename = null): Response` | رد | مثل `stream()` |
| `save(string $path, ?string $disk = null): string` | المسار | يحفظ على disk، أو في مسار محلي مطلق إن لم تحدد disk |
| `toResponse($request): Response` | رد | يتيح للـ controller إعادة الملف مباشرة فيُعرض في المتصفح |
| `toMailAttachment(): Attachment` | مرفق | يتيح لـ Mailable أو `MailMessage::attach()` استقبال الملف |

`Response` هو `Symfony\Component\HttpFoundation\Response`، و`Attachment` هو `Illuminate\Mail\Attachment`.

### الأسماء والحفظ {#rendered-names}

الاسم الذي ينقصه الامتداد الصحيح يُضاف إليه (`'فاتورة-1024'` يصبح `فاتورة-1024.pdf`)، و`/` و`\` في الاسم يصبحان `-`. الأسماء العربية تعمل: يحمل الرد الاسم الأصلي بترميز UTF-8، ومعه للبرامج القديمة اسم بحروف ASCII لاتينية (`fator-1024.pdf`)، أو `document.pdf` إن لم يبقَ منه شيء.

`save()` مع disk يكتب عبر `Storage::disk($disk)`. وبدون disk يُكتب المسار المطلق (`/var/...` أو `C:\...`) مباشرة مع إنشاء المجلد، ويذهب المسار النسبي إلى الـ disk الافتراضي. الكتابة الفاشلة ترمي `RuntimeException` بدلًا من أن تبدو ناجحة.

```php
$pdf = Doc::template('invoice', $data)->locale('ar')->pdf('فاتورة-1024');

$pdf->save('invoices/INV-2026-1024.pdf', 's3');   // on the s3 disk
$pdf->save(storage_path('app/archive/1024.pdf')); // an absolute local path
return $pdf->download();                          // فاتورة-1024.pdf
```

### مرفقات البريد {#rendered-mail}

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Mail\Mailable;

class InvoiceMail extends Mailable
{
    public function __construct(public array $invoice) {}

    // envelope() and content() as in any Mailable

    public function attachments(): array
    {
        return [Doc::template('invoice', $this->invoice)->locale('ar')->pdf('فاتورة-1024.pdf')];
    }
}
```

الاسم الممرر إلى `pdf()` أو `word()` هو اسم المرفق. أنشئ الملف داخل `attachments()` (أو `toMail()`) لا في الـ constructor، حتى لا يحمل البريد المؤجل في الـ queue ملفًا لم يُنشأ بعد.

### PdfDocument و WordDocument {#pdf-word-document}

لا تضيف `BiztechEG\EasyPdfWord\PdfDocument` (`application/pdf` و`.pdf`) و`BiztechEG\EasyPdfWord\WordDocument` (`application/vnd.openxmlformats-officedocument.wordprocessingml.document` و`.docx`) شيئًا إلى `RenderedFile` سوى النوع والامتداد. إعادة أي منهما من controller تعرضه في المتصفح.

## ZipFile {#zip-file}

الكائن `BiztechEG\EasyPdfWord\ZipFile` من نوع `RenderedFile`، فله كل الدوال السابقة (`download` و`save` و`toMailAttachment` ...).

```php
ZipFile::make(array $files, string $filename = 'documents.zip'): ZipFile
```

تستدعيه `Doc::zip()`. المعامل `$files` قائمة كائنات `PdfDocument` أو `WordDocument` أو `ZipFile`. المفتاح النصي هو الاسم داخل الأرشيف، وإلا يحتفظ كل ملف باسمه `filename()`. لا تحتوي الأسماء على مجلدات أبدًا، والاسم المكرر يصبح `name (2).pdf` ثم `name (3).pdf`. يُنشأ كل ملف عند بناء الأرشيف. القائمة الفارغة أو أي عنصر ليس ملفًا ناتجًا يرمي `InvalidArgumentException`، وغياب امتداد `zip` يرمي `RuntimeException` عند بناء الأرشيف.

```php
Doc::zip([
    'كشف-رواتب-سبتمبر.pdf' => Doc::template('report', $payroll)->pdf(),
    Doc::template('payslip', $ahmed)->pdf('قسيمة-أحمد.pdf'),
    Doc::template('payslip', $sara)->pdf('قسيمة-سارة.pdf'),
], 'payroll-2026-09.zip')->save('payroll/2026-09.zip', 's3');
```

## الـ job SaveDocument {#save-document}

`BiztechEG\EasyPdfWord\Jobs\SaveDocument` هو الـ job الذي يرسله `->queue()`. يطبّق `ShouldQueue` و`ShouldBeEncrypted` (يشفّر Laravel محتواه بمفتاح التطبيق لأنه يحمل بيانات المستند وكلمة مرور PDF إن وجدت)، ويستخدم `Dispatchable` و`InteractsWithQueue` و`Queueable`.

```php
new SaveDocument(array $document, string $format, string $path, ?string $disk = null)
```

| الخاصية العامة | النوع | القيمة |
| --- | --- | --- |
| `$document` | `array` | مصدر المستند وإعداداته (بنية *داخلية*) |
| `$format` | `string` | `'pdf'` أو `'word'` |
| `$path` | `string` | مكان حفظ الملف |
| `$disk` | `?string` | الـ disk، أو `null` للـ disk الافتراضي (أو لمسار مطلق) |

```php
handle(DocFactory $factory): void
```

يعيد بناء المستند وينشئه ويحفظه في `$path` على `$disk`. اسم الملف هو الجزء الأخير من المسار. تحقق من الـ job في الاختبارات باستخدام `Queue::fake()`:

```php
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use Illuminate\Support\Facades\Queue;

Queue::fake();

Doc::template('invoice', $data)->queue('invoices/INV-2026-1024.pdf', 's3');

Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->format === 'pdf'
    && $job->path === 'invoices/INV-2026-1024.pdf'
    && $job->disk === 's3');
```

## الاختبارات {#testing}

### DocFake {#doc-fake-class}

`BiztechEG\EasyPdfWord\Testing\DocFake` يرث `DocFactory` ويطبّق الواجهة `Fake` من Laravel. بعد `Doc::fake()` تعمل `Doc::template()` و`view()` و`html()` و`make()` كالمعتاد (فتُدمج البيانات مع القيم الافتراضية وتُجهَّز ويُتحقق منها)، لكن `->pdf()` و`->word()` تعيدان ملفات بديلة وتسجلان كائن [`GeneratedDocument`](#generated-document). يُسجَّل الحفظ والتنزيل والعرض بدلًا من تنفيذها. ويظل `Doc::zip()` يبني أرشيفًا من الملفات البديلة. الدليل: [اختبار تطبيقك](/ar/guide/testing).

| الدالة | تنجح عندما |
| --- | --- |
| `generated(?Closure $callback = null): array` | ليست دالة تحقق: تعيد كل كائنات `GeneratedDocument` المسجلة، أو التي تقبلها الدالة |
| `assertGenerated(?Closure $callback = null): void` | أُنشئ ملف واحد على الأقل (تقبله الدالة) |
| `assertNotGenerated(Closure $callback): void` | لا يطابق أي ملف الدالة |
| `assertGeneratedCount(int $count): void` | أُنشئ `$count` ملف بالضبط |
| `assertNothingGenerated(): void` | لم يُنشأ أي ملف |
| `assertSaved(string\|Closure $path, ?string $disk = null): void` | حُفظ ملف في `$path` (على `$disk` إن حُدد)، أو طابق ملف محفوظ الدالة |
| `assertDownloaded(string\|Closure\|null $filename = null): void` | أُرسل ملف للتنزيل (بهذا الاسم، أو مطابقًا للدالة) |
| `assertStreamed(string\|Closure\|null $filename = null): void` | عُرض ملف في المتصفح عبر `stream()` أو `inline()` أو بإعادته من controller |

تستقبل الدوال كائن `GeneratedDocument` وتعيد `bool`. استدعها كلها على الـ facade: `Doc::assertSaved(...)`.

```php
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;

Doc::fake();

Doc::template('invoice', $data)->locale('ar')->pdf('فاتورة-1024.pdf')->save('invoices/INV-2026-1024.pdf', 's3');

Doc::assertGeneratedCount(1);
Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->template === 'invoice'
    && $doc->data('totals.total') == 28500
    && $doc->contains('مؤسسة النور'));
Doc::assertSaved('invoices/INV-2026-1024.pdf', 's3');
```

### GeneratedDocument {#generated-document}

`BiztechEG\EasyPdfWord\Testing\GeneratedDocument` يصف ملفًا واحدًا طُلب أثناء تشغيل `Doc::fake()`.

| الخاصية العامة | النوع | القيمة |
| --- | --- | --- |
| `format` | `string` | `'pdf'` أو `'word'` |
| `template` | `?string` | اسم القالب، أو `null` |
| `view` | `?string` | اسم الـ view، أو `null` |
| `locale` | `string` | اللغة النهائية، مثل `'ar'` |
| `direction` | `string` | `'rtl'` أو `'ltr'` |
| `numerals` | `string` | `'arabic'` أو `'latin'` |
| `driver` | `?string` | المحرك المحدد بـ `->driver()`، أو `null` |
| `watermark` | `?string` | نص العلامة المائية (بأرقام المستند)، أو `null` |
| `protected` | `bool` | هل لملف PDF كلمة مرور |

| الدالة | تعيد |
| --- | --- |
| `isPdf(): bool` و`isWord(): bool` | الصيغة |
| `filename(): string` | الاسم مع الامتداد كما سيصل في التنزيل |
| `data(?string $key = null, mixed $default = null): mixed` | البيانات التي أُنشئ منها الملف، وفي القوالب بعد القيم الافتراضية و`prepare` (فتجد الإجماليات). المفتاح بالنقاط يختار قيمة واحدة |
| `html(): string` | نص HTML الذي سيستلمه محرك PDF. يرمي `LogicException` لملفات Word |
| `contains(string $text): bool` | هل يحتوي HTML ملف PDF على النص، بعد ترميزه كما يطبعه Blade. مع الأرقام العربية اكتب النص بالأرقام العربية |
| `saves(): array` | كل عمليات الحفظ: `['path' => ..., 'disk' => ...]` |
| `wasSaved(?string $path = null, ?string $disk = null): bool` | هل حُفظ (في هذا المسار وهذا الـ disk) |
| `wasDownloaded(?string $filename = null): bool` | هل أُرسل للتنزيل (بهذا الاسم) |
| `wasStreamed(?string $filename = null): bool` | هل عُرض في المتصفح (بهذا الاسم) |

الدوال `for()` و`content()` و`recordSave()` و`recordResponse()` *داخلية*.

## سجل القوالب {#template-registry}

يعيد `Doc::templates()` النسخة الوحيدة من `BiztechEG\EasyPdfWord\Templates\TemplateRegistry`.

| الدالة | الوصف |
| --- | --- |
| `get(string $name): Template` | القالب، أو `TemplateNotFound` |
| `exists(string $name): bool` | هل يوجد قالب بهذا الاسم |
| `all(): array` | `name => Template` لكل مجلد فيه `template.php`، ومجلدات المشروع أولًا |
| `paths(): array` | المجلدات التي يُبحث فيها بالترتيب، ومجلد المكتبة الأخير |
| `addPath(string $path, bool $first = true): static` | يضيف مجلدًا للبحث، في البداية (الافتراضي) أو النهاية |
| `isBundled(string $name): bool` | هل يشير الاسم إلى قالب المكتبة نفسه |
| `static packagePath(): string` | مجلد `resources/templates` في المكتبة |
| `static isValidName(string $name): bool` | حروف وأرقام و`.` و`-` و`_`، وليس `.` أو `..` |

```php
// Templates kept by a module of your app, searched before the others.
Doc::templates()->addPath(base_path('modules/Sales/doc-templates'));

foreach (Doc::templates()->all() as $name => $template) {
    echo $name.': '.$template->title().PHP_EOL;
}
```

### Template {#template-class}

`BiztechEG\EasyPdfWord\Templates\Template` يمثل مجلد قالب واحدًا، ويقرأ `template.php` (راجع [مفاتيح template.php](/ar/reference/template-helpers#template-php)).

| العضو | يعيد |
| --- | --- |
| `name` و`path` | خاصيتان عامتان للقراءة فقط: اسم المجلد ومساره الكامل |
| `title(): string` | `title`، أو الاسم |
| `description(): string` | `description`، أو `''` |
| `locales(): array` | `locales`، أو `['ar', 'en']` |
| `rules(): array` | `fields`: قواعد التحقق من البيانات في Laravel |
| `defaults(): array` | `defaults` |
| `prepare(array $data, array $theme = []): array` | البيانات بعد دالة `prepare` |
| `sample(): array` | `sample` (وإن كانت دالة تُستدعى) |
| `theme(): array` | `theme` |
| `paper()` و`orientation(): ?string` و`margins(): ?array` | إعدادات الصفحة، أو `null`. وتعيد `paper()` اسمًا مثل `'A4-L'` أو `[width, height]` بالمليمتر |
| `hasPdfView(): bool` و`pdfView(): string` | `pdf.blade.php`، و`pdfView()` يرمي `RuntimeException` إن لم يوجد |
| `wordFile(): ?string` | مسار `word.docx`، أو `null` |
| `wordLayout(): ?callable` | `word.php`، وإلا `layout.php` |
| `pdfLayout(): ?callable` | `layout.php`، وإلا `word.php` (يُستخدم حين لا يوجد `pdf.blade.php`) |
| `supportsPdf(): bool` و`supportsWord(): bool` | الصيغ التي يستطيع المجلد إنتاجها |
| `headerView(): ?string` و`footerView(): ?string` | مسارا `header.blade.php` و`footer.blade.php` |
| `translations(string $locale): array` | النصوص في `lang/{language}.php` |

## FontRegistry {#font-registry}

يعيد `Doc::fonts()` النسخة الوحيدة من `BiztechEG\EasyPdfWord\Fonts\FontRegistry`، وفيها الخطوط المرفقة وخطوط `fonts.custom`.

| الدالة | الوصف |
| --- | --- |
| `register(string $name, array $files): static` | يضيف خطًا. `$files`: `regular` (إلزامي) و`bold` و`italic` و`bold_italic` (مسارات `.ttf`)، و`arabic` (الافتراضي `true`)، و`arabic_separators` (رسم ٫ و٬ مع الأرقام العربية). بدون `regular` يرمي `InvalidArgumentException` |
| `has(string $name): bool` | هل الخط معروف (بأي حالة أحرف) |
| `get(string $name): array` | ملفاته، أو `InvalidArgumentException` إن لم يكن معروفًا |
| `all(): array` | كل الخطوط: `name => files` |
| `supportsArabic(string $name): bool` | هل الخط معلَّم بأنه يدعم العربية |
| `hasArabicSeparators(string $name): bool` | هل تستخدم الأرقام العربية الفاصلتين ٫ و٬ مع هذا الخط (من الخطوط المرفقة: `naskh` فقط) |
| `cssFontFaces(array $names): string` | قواعد `@font-face` تتضمن ملفات الخط، للمحركات التي تحمّل الخطوط من CSS |
| `forMpdf(int $kashida = 75): array` | *داخلية*: جدول الخطوط لـ mPDF |

```php
Doc::fonts()->register('amiri', [
    'regular' => resource_path('fonts/Amiri-Regular.ttf'),
    'bold'    => resource_path('fonts/Amiri-Bold.ttf'),
]);

Doc::template('letter', $letter)->font('amiri')->pdf();
```

التسجيل في الإعداد `fonts.custom` يفعل الشيء نفسه لكل الطلبات.

## PdfManager {#pdf-manager}

يعيد `Doc::pdfManager()` النسخة الوحيدة من `BiztechEG\EasyPdfWord\Pdf\PdfManager`، وهو `Manager` من Laravel.

| الدالة | الوصف |
| --- | --- |
| `driver($driver = null)` | كائن `PdfDriver` بالاسم (بأي حالة أحرف، و`chromium` و`chrome` تعنيان `browsershot`)، أو المحرك الافتراضي |
| `extend($driver, Closure $callback)` | ما تستدعيه `Doc::extend()` |
| `getDefaultDriver(): string` | `pdf.driver` من الإعدادات |
| `normalize(?string $driver): string` | الاسم كما يُخزَّن: بأحرف صغيرة وبعد حل الأسماء البديلة |
| `engineConfig(string $name): array` | `pdf.drivers.{name}` من الإعدادات |
| `render(string $html, PdfOptions $options, ?string $driver = null, ?callable $htmlFor = null): array` | *داخلية*: تنشئ الملف مع الانتقال إلى المحرك الاحتياطي وتعيد `[bytes, engine name]` |

```php
if (! Doc::pdfManager()->driver('chromium')->isAvailable()) {
    // spatie/browsershot is not installed
}
```

## محرك PDF خاص بك {#pdf-driver}

طبّق الواجهة `BiztechEG\EasyPdfWord\Contracts\PdfDriver` وسجّل المحرك بـ [`Doc::extend()`](#doc-extend). المحركات المدمجة موصوفة في [محركات PDF](/ar/guide/engines).

```php
interface PdfDriver
{
    /** Turn a full HTML document into PDF bytes. */
    public function render(string $html, PdfOptions $options): string;

    /** Whether the engine's package or service is installed, so the manager can fall back. */
    public function isAvailable(): bool;

    /** Whether the engine loads fonts from CSS @font-face (Chromium) rather than its own configuration (mPDF). */
    public function usesCssFonts(): bool;
}
```

- تستلم `render()` نص HTML النهائي (بعد تحويل الأرقام) وكائن [`PdfOptions`](#pdf-options). عليها تطبيق مقاس الورق والهوامش ورأس الصفحة وتذييلها (مع استبدال `{page}` و`{pages}`)، والعلامة المائية إن دعمتها. تضيف `BiztechEG\EasyPdfWord\Pdf\Watermark::inject($html, $options)` العلامة المائية بالطريقة التي تستخدمها محركات Chromium.
- إن أعادت `usesCssFonts()` القيمة `true` تضيف المكتبة إلى HTML قواعد `@font-face` لخط المستند ولكل خط مسجل تذكره أنماط CSS في الصفحة (`font-family: 'naskh'`)، وتنزّل بنفسها الصور البعيدة المسموح بها وتضمّنها في الصفحة، فلا يحمّل محركك أي رابط.
- تضيف mPDF كلمة المرور بعد `render()`، فلا يحتاج محركك إلى التشفير.
- إن أعادت `isAvailable()` القيمة `false` أو رمت `render()` استثناءً، ينشئ المحرك الاحتياطي المستند.

```php
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use Illuminate\Support\Facades\Http;

class PdfServiceDriver implements PdfDriver
{
    public function __construct(private string $url) {}

    public function render(string $html, PdfOptions $options): string
    {
        [$width, $height] = $options->paperSize();

        return Http::timeout(60)->post($this->url, [
            'html' => $html,
            'width_mm' => $width,
            'height_mm' => $height,
            'margins_mm' => $options->margins,
        ])->throw()->body();
    }

    public function isAvailable(): bool
    {
        return $this->url !== '';
    }

    public function usesCssFonts(): bool
    {
        return true;
    }
}

// AppServiceProvider::boot()
Doc::extend('pdf-service', fn ($app) => new PdfServiceDriver((string) config('services.pdf.url')));

// Anywhere
Doc::template('invoice', $data)->driver('pdf-service')->pdf()->engine();   // 'pdf-service'
```

### PdfOptions {#pdf-options}

`BiztechEG\EasyPdfWord\Pdf\PdfOptions` ينقل إعدادات الصفحة والمستند إلى المحرك. كل خصائصه عامة.

| الخاصية | النوع | الافتراضي |
| --- | --- | --- |
| `paper` | `string` أو `[width, height]` بالمليمتر | `'A4'` |
| `orientation` | `string` | `'portrait'` |
| `margins` | `[top, right, bottom, left]` بالمليمتر | `[15, 15, 15, 15]` |
| `direction` | `string` | `'ltr'` |
| `locale` | `string` | `'en'` |
| `font` | `string` | `'cairo'` |
| `header` و`footer` | `?string` نص HTML فيه `{page}` و`{pages}` | `null` |
| `title` و`author` | `?string` | `null` |
| `numerals` | `string` | `'latin'` |
| `watermark` | `['text' => ..., 'opacity' => ..., 'color' => ...]` أو `null` | `null` |
| `protection` | `['user' => ..., 'owner' => ..., 'allow' => [...]]` أو `null` | `null` |

| الدالة | تعيد |
| --- | --- |
| `isLandscape(): bool` | هل الاتجاه `landscape` (أو `L`) |
| `paperSize(): array` | `[width, height]` بالمليمتر بعد تطبيق الاتجاه |
| `static unknownPaper(string $paper): InvalidArgumentException` | الخطأ الخاص بمقاس غير معروف |
| `PdfOptions::PAPER_SIZES` | ثابت: `name => [width, height]` بالمليمتر من `A2` إلى `EXECUTIVE` |

## الاستثناءات {#exceptions}

استثناءات المكتبة نفسها في `BiztechEG\EasyPdfWord\Exceptions`:

| الاستثناء | يرث | متى يُرمى |
| --- | --- | --- |
| `TemplateNotFound` | `InvalidArgumentException` | لا يجد `Doc::template($name)` أو `Doc::templates()->get($name)` مجلدًا بهذا الاسم (أو في الاسم أحرف غير الحروف والأرقام و`.` و`-` و`_`). تذكر الرسالة المجلدات التي بُحث فيها |
| `WordNotSupported` | `LogicException` | استدعاء `->word()` أو `->queue('….docx')` على view أو HTML، أو على قالب ليس فيه `layout.php` أو `word.php` أو `word.docx` |
| `DriverNotAvailable` | `RuntimeException` | محرك PDF المختار غير مثبت أو غير مضبوط (`mpdf/mpdf` أو `spatie/browsershot` أو `DOC_GOTENBERG_URL`، أو أعادت `isAvailable()` في محركك `false`) ولم يستطع المحرك الاحتياطي الإنشاء أيضًا؛ أو إنشاء ملف Word بدون `phpoffice/phpword`؛ أو تحديد كلمة مرور مع Chromium أو Gotenberg مع غياب `mpdf/mpdf` |

استثناءات أخرى قد تقابلها:

| الاستثناء | متى |
| --- | --- |
| `Illuminate\Validation\ValidationException` | فشل بيانات القالب في قواعد `fields`: فورًا مع `->pdf()` و`->word()` و`->queue()` و`->toHtml()` و`->options()` |
| `InvalidArgumentException` | لغة أو اسم خط أو مقاس ورق أو نمط أرقام غير صالح، أو اسم محرك PDF غير معروف، أو علامة مائية فارغة، أو صلاحية كلمة مرور غير معروفة، أو مسار `->queue()` لا ينتهي بـ `.pdf` أو `.docx`، أو قائمة `Doc::zip()` فارغة أو خاطئة |
| `LogicException` | `->word()` أو `->queue('….docx')` لمستند له كلمة مرور، أو `GeneratedDocument::html()` لملف Word |
| `BadMethodCallException` | دالة كتلة (`heading` و`table` ...) على قالب أو view أو HTML، أو دالة غير موجودة |
| `RuntimeException` | حفظ لم يمكن كتابته، أو ZIP بدون `ext-zip`، أو تاريخ هجري بدون `ext-intl`، أو مجلد mPDF المؤقت غير قابل للكتابة، أو خطأ محرك بلا محرك احتياطي |

::: tip ملاحظة
اسم المحرك غير المدمج وغير المسجل بـ `Doc::extend()`، كخطأ إملائي، يرمي استثناءً بدلًا من اللجوء إلى المحرك الاحتياطي. فالمحرك الاحتياطي للمحركات الموجودة غير المثبتة أو التي تفشل فقط؛ استخدم `->pdf()->engine()` إن لم تكن متأكدًا من المحرك الذي عمل.
:::
