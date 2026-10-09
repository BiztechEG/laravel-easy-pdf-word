# الخطوط

اختر خط المستند، وأضف خطوطك الخاصة، وأصلح ملفات الخطوط التي لا يستطيع mPDF قراءتها، وحدد الخط الذي تستخدمه ملفات Word.

## الخطوط المرفقة {#bundled}

تأتي مع الحزمة ثلاثة خطوط عربية، لكل منها وزن عادي ووزن عريض، بترخيص SIL Open Font License:

| الاسم في الكود | الخط | طابعه |
| --- | --- | --- |
| `cairo` | Cairo | خط حديث بلا زوائد، وهو الافتراضي لكل المستندات |
| `tajawal` | Tajawal | خط خفيف مستدير بلا زوائد |
| `naskh` | Noto Naskh Arabic | خط النسخ التقليدي، للخطابات والعقود |

تحتوي الخطوط الثلاثة الحروف اللاتينية أيضًا، فلا يحتاج النص الإنجليزي أو المختلط إلى خط آخر.

## اختيار الخط {#choose}

لمستند واحد:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('letter', $data)->locale('ar')->font('naskh')->pdf();
```

وللتطبيق كله، في `config/easy-pdf-word.php`:

```php
'fonts' => [
    'default' => 'naskh',       // المستندات التي تُكتب من اليمين إلى اليسار
    'default_ltr' => 'cairo',   // بقية المستندات
    'custom' => [],
],
```

تأخذ `->font()` اسم خط مرفق أو مسجل، بأحرف كبيرة أو صغيرة (`naskh` أو `Naskh`). والاسم الذي يحتوي غير الحروف والأرقام والمسافات و`-` و`_` يرمي `Invalid font name [...]`. أما الاسم الصحيح غير المسجل فلا يرمي خطأ: يستخدم mPDF بصمت خطه DejaVu Sans بدلًا منه، فراجع كتابة الاسم حين يخرج المستند بخط غير الذي اخترته.

### في CSS الخاص بك {#css}

في الـ views والـ HTML الخاصة بك، استخدم الأسماء نفسها في `font-family`:

```blade
<h1 style="font-family: 'tajawal'">عرض سعر</h1>
<p style="font-family: 'naskh'">نص العرض بخط النسخ.</p>
```

يمكن استخدام كل خط مرفق أو مسجل بهذه الطريقة، مع كل المحركات. فـ mPDF يقرأ ملفات الخطوط بنفسه، أما مع Chromium وGotenberg فتضمّن الحزمة كل خط مسجل تذكره أنماط CSS في صفحتك في قاعدة `@font-face`. ويضيف التضمين ملفات الخط كاملة إلى الـ HTML (بضع مئات من الكيلوبايتات لكل ملف)، فلا تذكر إلا الخطوط التي تستخدمها. وتشرح صفحة [ملفات Blade و HTML](/ar/guide/views-and-html) الـ views ومكوّن التخطيط.

## خطوطك الخاصة {#custom-fonts}

ضع ملفات `.ttf` في تطبيقك، في `resources/fonts` مثلًا، وسجّلها تحت `fonts.custom`:

```php
// config/easy-pdf-word.php
'fonts' => [
    'default' => 'almarai',
    'default_ltr' => 'cairo',
    'custom' => [
        'almarai' => [
            'regular' => resource_path('fonts/Almarai-Regular.ttf'),
            'bold' => resource_path('fonts/Almarai-Bold.ttf'),
        ],
        'ibm-plex' => [
            'regular' => resource_path('fonts/IBMPlexSansArabic-Regular.ttf'),
            'bold' => resource_path('fonts/IBMPlexSansArabic-Bold.ttf'),
            'italic' => resource_path('fonts/IBMPlexSansArabic-Italic.ttf'),
            'bold_italic' => resource_path('fonts/IBMPlexSansArabic-BoldItalic.ttf'),
            'arabic_separators' => true,
        ],
    ],
],
```

| المفتاح | مطلوب | المعنى |
| --- | --- | --- |
| `regular` | نعم | ملف الخط العادي. وبدونه: `Font [name] needs at least a "regular" file.` |
| `bold` و`italic` و`bold_italic` | لا | الأنماط الأخرى. وبدون ملف عريض يطبع mPDF النص العريض بالوزن العادي. |
| `arabic_separators` | لا | `true` حين يرسم الخط `٫` و`٬` بوضوح، فتستخدمهما المستندات ذات الأرقام العربية (١٢٬٥٠٠٫٧٥). انظر [دعم اللغة العربية](/ar/guide/arabic#separators). |
| `arabic` | لا | `false` لخط بلا حروف عربية. ومع تفعيل `auto_lang_to_font` يُكتب النص العربي حينئذ بخط Cairo. والقيمة الافتراضية `true`. |

لا فرق بين الأحرف الكبيرة والصغيرة في الأسماء. والخط الخاص الذي يحمل اسم خط مرفق (`cairo`) يحل محله.

يحفظ mPDF البيانات التي يقرؤها من ملفات الخطوط في مجلد `fonts-<hash>` داخل مجلده المؤقت [`temp_dir`](/ar/guide/configuration#mpdf)، مجلدًا لكل نسخة من ملفات الخطوط المسجلة. فحين تضيف خطًا، أو تستبدل ملف خط أو تعدّله، يستخدم المستند التالي مجلدًا جديدًا ويقرأ الخطوط من جديد، فلا توجد ذاكرة مؤقتة تحتاج إلى مسحها. أما مجلدات `fonts-...` الأقدم فلم تعد مستخدمة ويمكن حذفها.

ويمكنك أيضًا تسجيل خط في الكود، داخل الدالة `boot()` في service provider:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::fonts()->register('almarai', [
    'regular' => resource_path('fonts/Almarai-Regular.ttf'),
    'bold' => resource_path('fonts/Almarai-Bold.ttf'),
]);
```

يعثر mPDF على ملفات الخطوط بأسماء ملفاتها، فلا يمكن لخطين استخدام ملفين بالاسم نفسه من مجلدين مختلفين (`fonts/a/Regular.ttf` و`fonts/b/Regular.ttf`). ويفشل ذلك بالرسالة `The font files [...] and [...] have the same name. mPDF finds fonts by file name, so rename one of them.`

لا يقرأ mPDF إلا الخطوط ذات المحيطات من نوع TrueType. والخط ذو محيطات PostScript (معظم ملفات `.otf`) يفشل بالرسالة `Fonts with postscript outlines are not supported`؛ استخدم نسخة `.ttf` من الخط.

## إصلاح الخطوط التي لا يقرؤها mPDF {#mpdf-font-fix}

لا يستطيع mPDF قراءة بعض خصائص الخطوط الحديثة، ومنها كثير من خطوط Google Fonts. فيتوقف الإنشاء بأحد هذه الأخطاء:

```text
Font "almarai" contains MarkGlyphSets which is not supported
This font [almarai] contains MarkGlyphSets - Not tested yet
GPOS Lookup Type 5, Format 3 not supported (ttfontsuni.php).
```

تتضمن الحزمة سكربتًا يصلح ملفات الخطوط مرة واحدة، ويحتاج إلى Python 3 ومكتبة fontTools:

```bash
python3 -m pip install fonttools
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/Almarai-*.ttf
```

```text
resources/fonts/Almarai-Bold.ttf: fixed
resources/fonts/Almarai-Regular.ttf: fixed
```

يغيّر السكربت الملفات في مكانها، فاحتفظ بنسخة من الأصول. وهذا ما يفعله:

- **MarkGlyphSets**: يحذف مجموعات علامات التشكيل الاختيارية ويرجع إلى الإصدار الأقدم من جدول الخط. ويظل وصل الحروف ووضع الحركات يعملان.
- **Lookup Type 5, Format 3**: يعيد كتابة هذه الاستبدالات السياقية بالصيغة المتسلسلة المكافئة التي يدعمها mPDF. وتخرج الحروف كما هي.

والملف الذي لا يحتاج إلى تغيير يطبع `nothing to change`. والخطوط المرفقة مُصلحة بهذه الطريقة مسبقًا. أما Chromium وGotenberg فيقرآن الخطوط بلا هذه القيود، فلا تحتاج إلى إصلاح.

## الخطوط في ملفات Word {#word}

لا تضمّن ملفات Word الخطوط: يعرض Word النص بخط مثبت على جهاز القارئ. لذلك لا تنطبق الخطوط السابقة إلا على ملفات PDF، وتستخدم ملفات Word الخط المحدد في `DOC_WORD_FONT`، وهو Arial افتراضيًا:

```dotenv
DOC_WORD_FONT="Sakkal Majalla"
```

اختر خطًا موجودًا لدى قرائك ويغطي العربية: Arial أو Tahoma أو Times New Roman أو Simplified Arabic أو Traditional Arabic أو Sakkal Majalla على Windows. ويحدد `word.font_size` في ملف الإعدادات الحجم (11 افتراضيًا). وتجد المزيد عن مخرجات Word في [ملفات Word](/ar/guide/word).

## الخطوط مع Chromium وGotenberg {#chromium}

يتسلم Chromium وGotenberg خط المستند مضمّنًا في HTML في قاعدة `@font-face` تحتوي ملف الخط نفسه. فلا يحتاجان إلى خطوط مثبتة على الخادم، ويستخدم رأس الصفحة وتذييلها الخط نفسه. ويُضمَّن بالطريقة نفسها أي خط مسجل آخر تذكره أنماط CSS في الصفحة؛ انظر [في CSS الخاص بك](#css).

## مستندات بأكثر من نظام كتابة {#auto-lang-to-font}

المستند الذي يخلط العربية بنظام كتابة لا يحتويه خطه، كالصينية أو الديفناغرية، يُظهر مربعات فارغة مكان تلك الحروف. مع mPDF فعّل `auto_lang_to_font` في ملف الإعدادات:

```php
// config/easy-pdf-word.php
'pdf' => [
    // ...
    'drivers' => [
        'mpdf' => [
            // ...
            'auto_lang_to_font' => true,
        ],
        // ...
    ],
],
```

يختار mPDF حينئذ خطًا لكل نظام كتابة من خطوطه هو، ويبقى النص العربي بخط المستند. وهو يتجاهل `font-family` في CSS الخاص بك، فاتركه مطفأً لبقية المستندات.
