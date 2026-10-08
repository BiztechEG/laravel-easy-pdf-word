# أوامر Artisan

تضيف الحزمة أربعة أوامر Artisan: عرض قائمة القوالب، ونسخ قالب لتخصيصه، وبدء قالب جديد، وإنشاء ملف من البيانات التجريبية لقالب.

| الأمر | ما يفعله |
| --- | --- |
| [`doc:templates`](#doc-templates) | يعرض القوالب التي يستطيع تطبيقك استخدامها |
| [`doc:template`](#doc-template) | ينسخ قالبًا إلى تطبيقك لتخصيصه |
| [`doc:make-template`](#doc-make-template) | ينشئ قالبًا جديدًا من هيكل فارغ |
| [`doc:sample`](#doc-sample) | ينشئ قالبًا ببياناته التجريبية في ملف PDF أو Word أو HTML |

## doc:templates {#doc-templates}

```bash
php artisan doc:templates
```

لا يأخذ وسائط ولا خيارات، ويعرض كل القوالب: المرفقة والموجودة في مجلد قوالب مشروعك (`resources/doc-templates` افتراضيًا).

```text
+----------------+--------------------+---------+-----------+---------+
| Name           | Title              | Locales | Formats   | Source  |
+----------------+--------------------+---------+-----------+---------+
| my-invoice     | Tax invoice        | ar, en  | PDF, Word | project |
| packing-list   | packing-list       | ar, en  | PDF, Word | project |
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

| العمود | المعنى |
| --- | --- |
| Name | ما تمرره إلى `Doc::template()` |
| Title | `title` من ملف `template.php` الخاص بالقالب |
| Locales | `locales` من `template.php` (`ar, en` إن لم يحددها) |
| Formats | `PDF` حين يحتوي القالب `pdf.blade.php` أو `layout.php` أو `word.php`؛ و`Word` حين يحتوي `layout.php` أو `word.php` أو `word.docx` |
| Source | `project` لقالب في تطبيقك، و`package` لقالب مرفق |

تظهر قوالبك أولًا. والقالب في مشروعك الذي يحمل اسم قالب مرفق يحل محله، فلا يظهر القالب المرفق في القائمة.

## doc:template {#doc-template}

ينسخ قالبًا إلى مجلد قوالب مشروعك، حيث تستطيع تعديله.

```bash
php artisan doc:template {name} {--as=} {--force}
```

| الوسيط أو الخيار | المعنى |
| --- | --- |
| `name` | القالب المراد نسخه، مثل `invoice`. |
| `--as=` | اسم جديد للنسخة. وبدونه تحتفظ النسخة بالاسم نفسه وتحل محل الأصل في تطبيقك. |
| `--force` | الكتابة فوق نسخة موجودة. ومع اسم قالب مرفق، يعيد ملفات القالب المرفق فوق نسختك. |

```bash
# قالب جديد بجانب الأصل
php artisan doc:template invoice --as=my-invoice

# الاسم نفسه: نسختك تحل محل الخطاب المرفق في كل التطبيق
php artisan doc:template letter

# ابدأ نسختك من الخطاب من جديد من القالب المرفق
php artisan doc:template letter --force
```

```text
   INFO  Template copied to resources/doc-templates/my-invoice.
```

أخطاء قد تراها:

```text
   ERROR  resources/doc-templates/my-invoice already exists. Use --force to overwrite it.
   ERROR  Use letters, digits, dots, dashes or underscores for the template name.
   ERROR  resources/doc-templates/packing-list is the template itself. Use --as to copy it under another name.
```

يظهر الخطأ الأخير مع `--force` لقالب موجود في مشروعك فقط. واسم القالب غير المعروف يتوقف بالرسالة `Template [invoce] was not found in: ...` تليها المجلدات التي بُحث فيها. وتذهب النسخة إلى أول مجلد في `templates.paths` في ملف الإعدادات. وتشرح صفحة [قوالبك الخاصة](/ar/guide/custom-templates) ما يمكن تغييره في القالب المنسوخ.

## doc:make-template {#doc-make-template}

ينشئ مجلد قالب جديد من هيكل فارغ: `template.php` و`pdf.blade.php` و`word.php` و`footer.blade.php` و`lang/ar.php` و`lang/en.php`.

```bash
php artisan doc:make-template {name}
```

| الوسيط | المعنى |
| --- | --- |
| `name` | اسم المجلد، وهو اسم القالب أيضًا، مثل `packing-list`. حروف وأرقام ونقاط وشرطات وشرطات سفلية. |

```bash
php artisan doc:make-template packing-list
```

```text
   INFO  Template created in resources/doc-templates/packing-list.
```

يعمل القالب الجديد فورًا، بصيغتي PDF وWord:

```php
Doc::template('packing-list', ['title' => 'قائمة التعبئة'])->locale('ar')->pdf();
```

ومع اسم قالب مرفق، ينبهك الأمر إلى أن القالب الجديد يحل محله:

```text
   WARN  This replaces the bundled [receipt] template in your app. To start from it instead, run php artisan doc:template receipt.

   INFO  Template created in resources/doc-templates/receipt.
```

ولا يُكتب أبدًا فوق مجلد موجود: `ERROR  resources/doc-templates/packing-list already exists.`

## doc:sample {#doc-sample}

ينشئ قالبًا بالبيانات التجريبية من ملف `template.php` الخاص به ويكتب الملف، فترى القالب دون كتابة كود.

```bash
php artisan doc:sample {name} {--locale=ar} {--format=} {--numerals=latin} {--driver=} {--output=}
```

| الوسيط أو الخيار | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `name` | | القالب، مثل `invoice`. |
| `--locale=` | `ar` | لغة المستند. |
| `--format=` | من `--output`، وإلا `pdf` | `pdf` أو `docx` أو `html`. وبدونه يحدد امتداد `--output` الصيغة إن كان أحد هذه. |
| `--numerals=` | `latin` | `latin` (123) أو `arabic` (١٢٣). |
| `--driver=` | المحرك الافتراضي | محرك PDF: `mpdf` أو `chromium` أو `gotenberg`. |
| `--output=` | `storage/app/doc-samples/{name}-{locale}.{format}` | الملف المراد كتابته. والمسار النسبي نسبي إلى المجلد الذي تشغّل منه الأمر. وتُنشأ المجلدات الناقصة. |

```bash
# storage/app/doc-samples/invoice-ar.pdf
php artisan doc:sample invoice

# بالإنجليزية وبالأرقام العربية وبمحرك Chromium
php artisan doc:sample invoice --locale=en --numerals=arabic --driver=chromium

# ملف Word: الامتداد يحدد الصيغة
php artisan doc:sample eg-invoice --output=eg-invoice.docx

# الـ HTML الذي يتسلمه محرك PDF، لتتبع مشكلة في التخطيط
php artisan doc:sample report --format=html --output=report.html
```

```text
   INFO  Saved storage/app/doc-samples/invoice-ar.pdf.
```

أخطاء قد تراها:

```text
   ERROR  Template [invoce] was not found. Run php artisan doc:templates to list them.
   ERROR  Use --format=pdf, docx or html.
   ERROR  Invalid locale [../x], expected a name such as "ar", "en" or "ar_EG".
   ERROR  Unknown numerals style [roman]. Use "latin" or "arabic".
```

والـ `--output` ذو الامتداد الآخر، مثل `report.xls`، يُكتب ملف PDF ما لم يحدد `--format` غير ذلك. والمحرك الاحتياطي يعمل هنا أيضًا، فراجع السجل حين تطلب `--driver=chromium` وتريد التأكد أن Chromium هو من أنشأ الملف.

## نشر ملف الإعدادات {#publish-config}

ليس من أوامر الحزمة نفسها، لكنه الأمر الذي تحتاجه لتغيير إعداداتها:

```bash
php artisan vendor:publish --tag=easy-pdf-word-config
```

ينسخ `config/easy-pdf-word.php` إلى تطبيقك. وتشرح صفحة [الإعدادات](/ar/guide/configuration) كل مفتاح فيه.
