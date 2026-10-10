# القوالب

تأتي المكتبة باثني عشر قالباً جاهزاً، كل منها بالعربية والإنجليزية، ويخرج ملف PDF أو ملف Word. استخدم هذه الصفحة لتعرف القالب الذي تحتاجه، ثم افتح صفحته لترى حقوله وخياراته ومثالاً يعمل.

<div class="gallery">
  <a href="/ar/templates/invoice"><img src="/previews/invoice-ar.png" alt="معاينة الفاتورة الضريبية"><strong>الفاتورة الضريبية</strong><span>الضريبة والتفقيط ورمز QR لهيئة الزكاة أو لأي رابط</span></a>
  <a href="/ar/templates/eg-invoice"><img src="/previews/eg-invoice-ar.png" alt="معاينة الفاتورة الإلكترونية المصرية"><strong>الفاتورة الإلكترونية المصرية</strong><span>فاتورة وإشعارات دائنة ومدينة بصيغة مصلحة الضرائب</span></a>
  <a href="/ar/templates/credit-note"><img src="/previews/credit-note-ar.png" alt="معاينة الإشعار الدائن"><strong>إشعار دائن ومدين</strong><span>تصحيح فاتورة صدرت من قبل</span></a>
  <a href="/ar/templates/quotation"><img src="/previews/quotation-ar.png" alt="معاينة عرض السعر"><strong>عرض سعر</strong><span>مدة الصلاحية والشروط والتوقيع</span></a>
  <a href="/ar/templates/receipt"><img src="/previews/receipt-ar.png" alt="معاينة سند القبض"><strong>سند قبض وسند صرف</strong><span>مبلغ مقبوض أو مدفوع بالأرقام والحروف</span></a>
  <a href="/ar/templates/purchase-order"><img src="/previews/purchase-order-ar.png" alt="معاينة أمر الشراء"><strong>أمر شراء</strong><span>طلب توريد من مورد مع الاعتمادات</span></a>
  <a href="/ar/templates/delivery-note"><img src="/previews/delivery-note-ar.png" alt="معاينة إذن التسليم"><strong>إذن تسليم</strong><span>الكميات المسلَّمة والمتبقية بلا أسعار</span></a>
  <a href="/ar/templates/payslip"><img src="/previews/payslip-ar.png" alt="معاينة قسيمة الراتب"><strong>قسيمة راتب</strong><span>الاستحقاقات والاستقطاعات وصافي الراتب</span></a>
  <a href="/ar/templates/contract"><img src="/previews/contract-ar.png" alt="معاينة العقد"><strong>عقد</strong><span>الأطراف والبنود المرقمة والتوقيعات</span></a>
  <a href="/ar/templates/certificate"><img src="/previews/certificate-ar.png" alt="معاينة الشهادة"><strong>شهادة</strong><span>بالعرض، مع رمز QR للتحقق</span></a>
  <a href="/ar/templates/letter"><img src="/previews/letter-ar.png" alt="معاينة الخطاب الرسمي"><strong>خطاب رسمي</strong><span>الترويسة ورقم الصادر والتاريخ الهجري</span></a>
  <a href="/ar/templates/report"><img src="/previews/report-ar.png" alt="معاينة التقرير الجدولي"><strong>تقرير جدولي</strong><span>أي صفوف، مع الإجماليات ورأس الجدول في كل صفحة</span></a>
</div>

كل صورة هي الصفحة الأولى من البيانات التجريبية للقالب بالعربية، مع اسم شركة وشعار في الهوية (theme). اضغط على أي قالب لتفتح صفحته، وفيها النسخة الإنجليزية أيضاً.

## القوالب في لمحة {#at-a-glance}

الاسم في العمود الأول هو ما تمرره إلى `Doc::template()`.

| القالب | المستند | مقاس الورق | تخطيط Word |
| --- | --- | --- | --- |
| `invoice` | فاتورة ضريبية: البائع والعميل والأصناف والخصم والضريبة والتفقيط والتاريخ الهجري ورمز QR لهيئة الزكاة أو لأي رابط | A4 طولي | نعم، `word.php` (و PDF من `pdf.html.php`) |
| `eg-invoice` | فاتورة إلكترونية أو إشعار دائن أو مدين لمصلحة الضرائب المصرية، بأكواد الأصناف وأنواع الضرائب | A4 طولي | نعم، `layout.php` (تخطيط واحد للصيغتين) |
| `credit-note` | إشعار دائن أو مدين على فاتورة صادرة، بالسبب والضريبة ورمز QR اختياري | A4 طولي | نعم، `layout.php` |
| `quotation` | عرض سعر بضريبة اختيارية ومدة صلاحية وشروط وتوقيع المرسل | A4 طولي | نعم، `layout.php` |
| `purchase-order` | أمر شراء إلى مورد ببيانات التسليم وشروط الدفع وتوقيعات الاعتماد | A4 طولي | نعم، `layout.php` |
| `delivery-note` | إذن تسليم بلا أسعار: الكميات المطلوبة والمسلَّمة والمتبقية وبيانات النقل والتوقيعات | A4 طولي | نعم، `layout.php` |
| `receipt` | سند قبض أو سند صرف: المبلغ بالأرقام والحروف، نقداً أو بشيك أو تحويل أو بطاقة | A5 عرضي | نعم، `layout.php` |
| `payslip` | قسيمة راتب شهرية: الاستحقاقات والاستقطاعات وصافي الراتب كتابةً والحضور والتوقيعات | A4 طولي | نعم، `layout.php` |
| `contract` | عقد بين طرفين أو أكثر: التمهيد والبنود المرقمة وعدد النسخ وتوقيعات الأطراف والشهود | A4 طولي | نعم، `layout.php` |
| `certificate` | شهادة إتمام أو حضور أو مشاركة أو تقدير، مع رمز QR للتحقق | A4 عرضي | نعم، `word.php` (و PDF من `pdf.html.php`) |
| `letter` | خطاب رسمي: الترويسة ورقم الصادر والتاريخان الميلادي والهجري والتوقيع والختم | A4 طولي | نعم، `word.php` (و PDF من `pdf.html.php`) |
| `report` | تقرير جدولي من أي صفوف: أعمدة تختارها وصف للإجماليات وبطاقات ملخص ورأس الجدول في كل صفحة | A4 طولي | نعم، `word.php` (و PDF من `pdf.html.php`) |

لكل القوالب تخطيط Word، فيعمل `->word()` معها كلها. ملفات Word تحتاج الحزمة `phpoffice/phpword`؛ انظر [ملفات Word](/ar/guide/word). مقاس الورق في الجدول هو المقاس الافتراضي للقالب، ويمكنك تغييره لكل مستند بـ `->paper()` و `->landscape()` (انظر [إعدادات الصفحة](/ar/guide/page-settings)).

## أي قالب أحتاج؟ {#which-template}

- **تُصدر فاتورة لعميل** وتحتاج فاتورة ضريبية: `invoice`. يناسب مصر (ضريبة 14%، وهي القيمة الافتراضية) والسعودية (ضريبة 15% مع رمز QR لهيئة الزكاة والضريبة والجمارك) وعملات الخليج الأخرى.
- **ترسل فواتيرك إلى مصلحة الضرائب المصرية** وتريد النسخة المطبوعة بأرقام التسجيل الضريبي وأكواد الأصناف وأنواع الضرائب (من T1 إلى T20): `eg-invoice`. ويطبع أيضاً الإشعارات الدائنة والمدينة للمنظومة.
- **تصحح فاتورة أصدرتها من قبل** (مرتجع، أو خدمة أُلغيت، أو فرق سعر): `credit-note`، إشعاراً دائناً أو مديناً.
- **تعرض أسعارك قبل البيع**: `quotation`.
- **تشتري من مورد**: `purchase-order`.
- **تسلّم بضاعة** وتحتاج مستنداً موقّعاً بلا أسعار: `delivery-note`.
- **تقبض مبلغاً أو تصرفه**: `receipt`، سند قبض أو سند صرف.
- **تصرف الرواتب**: `payslip`.
- **يوقّع طرفان أو أكثر اتفاقاً**: `contract`.
- **تنظم دورة أو فعالية**: `certificate`.
- **تكتب مراسلات رسمية**: `letter`.
- **تطبع قائمة صفوف** من استعلام (مبيعات، مخزون، حضور): `report`.

لم تجد ما يناسبك؟ انسخ أقرب قالب وعدّله، أو ابنِ قالبك: انظر [قوالبك الخاصة](/ar/guide/custom-templates).

## استخدام القالب {#use}

مرّر اسم القالب وبياناته، واختر اللغة، ثم اطلب ملف PDF أو ملف Word:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$quote = Doc::template('quotation', $data)->locale('ar');

return $quote->pdf()->download('QT-2026-0088.pdf');
// أو: return $quote->word()->download('QT-2026-0088.docx');
```

قبل رسم أي شيء تحدث ثلاث خطوات:

1. تملأ القيم الافتراضية للقالب ما تركته من حقول (العملة مثلاً).
2. يجري التحقق من البيانات حسب قواعد `fields` في القالب. البيانات الناقصة أو الخاطئة ترمي `ValidationException` المعتاد في Laravel، فيرى المستخدم رسائل التحقق المعتادة داخل الـ request.
3. تضيف خطوة `prepare()` في القالب القيم المحسوبة: إجمالي كل سطر والضريبة والإجمالي ورمز QR وغيرها.

اسم شركتك وشعارها وألوانها تأتي من الهوية: اضبطها مرة واحدة في `config/easy-pdf-word.php` تحت `theme`، أو لكل مستند بـ `->theme([...])`. تشرح صفحة [القوالب الجاهزة](/ar/guide/templates) البيانات والهوية واللغات بتفصيل أكثر.

## عرض قائمة القوالب {#list}

```bash
php artisan doc:templates
```

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| certificate    | Certificate        | ar, en  | PDF, Word | package |
| contract       | Contract           | ar, en  | PDF, Word | package |
| credit-note    | Credit note        | ar, en  | PDF, Word | package |
| delivery-note  | Delivery note      | ar, en  | PDF, Word | package |
| eg-invoice     | Egyptian e-invoice | ar, en  | PDF, Word | package |
| invoice        | Tax invoice        | ar, en  | PDF, Word | package |
| letter         | Official letter    | ar, en  | PDF, Word | package |
| payslip        | Payslip            | ar, en  | PDF, Word | package |
| purchase-order | Purchase order     | ar, en  | PDF, Word | package |
| quotation      | Price quotation    | ar, en  | PDF, Word | package |
| receipt        | Receipt voucher    | ar, en  | PDF, Word | package |
| report         | Table report       | ar, en  | PDF, Word | package |
+----------------+--------------------+---------+-----------+---------+
```

القوالب التي نسختها أو أنشأتها بنفسك تظهر في القائمة نفسها، ومصدرها `project`.

## معاينة القوالب {#preview}

في بيئة `local` افتح `/doc-preview` في تطبيقك. تعرض الصفحة كل القوالب ببياناتها التجريبية، وتتيح لك تبديل اللغة والأرقام ومحرك PDF، وفتح ملف PDF أو تنزيل ملف Word. خارج `local` تكون صفحة المعاينة مغلقة حتى تفعّلها وتسمح بها عبر gate: انظر [صفحة المعاينة](/ar/guide/preview).

وبلا متصفح، ولّد ملفاً من البيانات التجريبية لأي قالب:

```bash
# storage/app/doc-samples/eg-invoice-ar.pdf
php artisan doc:sample eg-invoice

# امتداد --output يحدد الصيغة: .pdf أو .docx أو .html
php artisan doc:sample invoice --locale=en --output=invoice-en.docx

# أرقام عربية ومحرك آخر
php artisan doc:sample quotation --numerals=arabic --driver=chromium
```

القيمة الافتراضية لـ `--locale` هي `ar`. البيانات التجريبية موجودة في ملف `template.php` لكل قالب، ويمكنك استخدامها في الكود أيضاً، في اختبار مثلاً:

```php
$sample = Doc::templates()->get('invoice')->sample();

$pdf = Doc::template('invoice', $sample)->locale('ar')->pdf();
```

## النسخ والتعديل {#copy}

لتغيير تخطيط قالب أو عناوينه أو قيمه الافتراضية، انسخه إلى تطبيقك:

```bash
php artisan doc:template invoice --as=my-invoice
```

ينسخ هذا الأمر مجلد القالب كاملاً إلى `resources/doc-templates/my-invoice`. عدّله هناك واستخدمه باسمه الجديد:

```php
Doc::template('my-invoice', $data)->locale('ar')->pdf();
```

النسخ بدون `--as` يُبقي الاسم نفسه، فتحل نسختك محل القالب الأصلي في التطبيق كله. أضف `--force` لتكتب فوق نسخة سابقة. تنتهي صفحة كل قالب بما يمكنك تعديله في نسخته، وتشرح صفحة [قوالبك الخاصة](/ar/guide/custom-templates) محتويات المجلد وكيف تبني قالباً من الصفر.
