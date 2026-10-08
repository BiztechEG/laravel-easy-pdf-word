# ملفات Blade و HTML

إذا كان لديك تصميم جاهز بلغتي HTML وCSS، فحوّل ملف Blade الخاص بك أو نص HTML إلى PDF. تعرض هذه الصفحة مكوّن التخطيط وأدوات `$doc` التي تجعل ملف الـ view نفسه يعمل بالعربية والإنجليزية، وCSS الذي يعمل على كل المحركات، وما يجب إبقاؤه خارج HTML الخاص بك.

## تحويل ملف Blade {#view}

يأخذ `Doc::view()` اسم أي view في تطبيقك مع بياناته، مثل الدالة المساعدة `view()` في Laravel:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::view('pdf.contract', ['contract' => $contract])
    ->locale('ar')
    ->pdf()
    ->download("عقد-{$contract->number}.pdf");
```

```blade
{{-- resources/views/pdf/contract.blade.php --}}
<x-doc::layout :doc="$doc" :title="'عقد رقم '.$contract->number">
    <x-slot:styles>
        <style>
            h1 { color: {{ $doc->theme('primary') }}; font-size: 18pt; margin-bottom: 4mm; }
            .parties td { width: 50%; padding: 3mm; border: 1px solid {{ $doc->theme('border') }}; }
            .amount { margin-top: 4mm; padding: 3mm; background-color: #F3F4F6; }
        </style>
    </x-slot:styles>

    <h1>عقد تقديم خدمات رقم {{ $doc->ltr($contract->number) }}</h1>

    <p>تحرر هذا العقد بتاريخ {{ $contract->signed_at->format('Y/m/d') }} بين كل من:</p>

    <table class="parties">
        <tr>
            <td><strong>الطرف الأول:</strong> {{ $contract->provider }}</td>
            <td><strong>الطرف الثاني:</strong> {{ $contract->client }}</td>
        </tr>
    </table>

    <div class="amount">
        <strong>قيمة العقد:</strong> {{ $doc->money($contract->amount, $doc->currency('EGP')) }}
        <div class="muted">{{ $doc->tafqeet($contract->amount, 'EGP') }}</div>
    </div>

    <p style="margin-top: 4mm;">للتواصل: {{ $doc->ltr($contract->phone) }}</p>
</x-doc::layout>
```

يصبح كل مفتاح في البيانات متغيراً، كما في أي view، وتضيف الحزمة `$doc`. ولا يستبدله مفتاح في البيانات اسمه `doc`. وتعمل كل إعدادات المستند مع ملفات الـ view: ‏`->locale()` و`->numerals()` و`->theme()` و`->font()` و`->paper()` و`->header()` و`->footer()` و`->watermark()` وغيرها.

تنتج ملفات الـ view ملفات PDF فقط. فاستدعاء `->word()` على view أو على HTML يرمي `WordNotSupported`: "Word files are made from a template with word.php or word.docx, or from Doc::make(). Blade views and HTML only make PDFs." ولملف Word، استخدم [قالباً](/ar/guide/custom-templates) أو [بناء المستند بالكود](/ar/guide/builder).

## مكوّن التخطيط {#layout-component}

غلّف الـ view بالمكوّن `<x-doc::layout :doc="$doc">`. يكتب المكوّن صفحة HTML كاملة حول المحتوى:

- `<html lang="ar" dir="rtl">` (أو `ltr`) بحسب اللغة، وترميز UTF-8، ووسم `<title>` من الخاصية `title`؛
- خط المستند على الصفحة كلها، مضمَّناً في الصفحة لمحركي Chromium وGotenberg؛
- تنسيقات أساسية: لون النص من الهوية، ونص بحجم 10.5pt وتباعد أسطر 1.5، وجداول بعرض الصفحة بحدود مدمجة، وخلايا محاذاة إلى الأعلى، وعناوين بلا هوامش حولها؛
- أصناف CSS مساعدة: `text-start` و`text-end` (اليمين واليسار في العربية، والعكس في الإنجليزية)، و`text-center`، و`muted` (اللون الثانوي من الهوية)، و`ltr` (يُبقي القيمة من اليسار إلى اليمين)، و`nowrap`.

| الخاصية أو الـ slot | ما هي |
| --- | --- |
| `:doc="$doc"` | مطلوبة: سياق المستند |
| `:title="..."` | عنوان HTML، اختياري |
| `<x-slot:styles>` | كتلة `<style>` الخاصة بك، توضع في رأس الصفحة بعد التنسيقات الأساسية |

يمكنك كتابة صفحة `<html>` خاصة بك دون المكوّن، لكنك حينها تضبط `dir` و`lang` والخط بنفسك. والمكوّن هو الطريق السهل لضبطها صحيحة في كل لغة.

## أدوات $doc {#doc-helpers}

يحمل `$doc` إعدادات المستند وأدوات لطباعة القيم بشكل صحيح في أي لغة:

| الأداة | تعطي |
| --- | --- |
| `$doc->theme('primary')` و`$doc->theme('company.name')` | قيمة من الهوية، مع النقاط للمفاتيح المتداخلة |
| `$doc->isRtl()` و`$doc->start()` و`$doc->end()` | الاتجاه، و`right` / `left` (معكوسين في الإنجليزية) لاستخدامهما في `text-align` |
| `$doc->ltr($value)` | يُبقي رقم هاتف أو كوداً أو بريداً إلكترونياً من اليسار إلى اليمين داخل النص العربي |
| `$doc->number(1250.5)` و`$doc->money(1250.5, 'SAR')` | `1,250.50` و`1,250.50 SAR`، وتبقى إشارة السالب قبل الأرقام |
| `$doc->currency('EGP')` | الاسم المختصر للعملة بلغة المستند: `ج.م` بالعربية و`EGP` بالإنجليزية |
| `$doc->tafqeet(1250.5, 'EGP')` | فقط ألف ومائتان وخمسون جنيهاً وخمسون قرشاً لا غير |
| `$doc->inWords(1250.5, 'EGP')` | المبلغ كتابةً بلغة المستند |
| `$doc->hijri($date)` و`$doc->hasHijri()` | التاريخ الهجري، وهل إضافة `intl` متاحة لإنشائه |
| `$doc->image($path)` | صورة في صورة data URI، تُقرأ من المجلدات المسموح بها فقط |
| `$doc->t('key')` | تسمية من ملفات `lang` في القالب (في القوالب؛ أما في view عادي فتطبع المفتاح) |
| `$doc->locale` و`$doc->direction` و`$doc->font` و`$doc->numerals` | الإعدادات نفسها |

ولرمز QR، استخدم المكوّن `<x-doc::qr :value="$url" size="30mm" />`. وفي Blade أيضاً `@tafqeet(1250.5, 'EGP')` و`@hijri($date)`. ويسرد [مرجع أدوات القوالب](/ar/reference/template-helpers) كل أداة مع معاملاتها.

لا تحوّل الأرقام بنفسك أبداً. فمع `->numerals('arabic')` تتحول الأرقام في نص الصفحة النهائية إلى أرقام عربية، بينما تبقى الوسوم وCSS والروابط كما هي.

## نص HTML {#html}

يأخذ `Doc::html()` نص HTML بنيته بنفسك:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::html('<h1>إشعار استلام</h1><p>تم استلام طلبكم رقم 1024 وسيتم التواصل معكم خلال يومي عمل.</p>')
    ->locale('ar')
    ->pdf()
    ->stream('إشعار.pdf');
```

يوضع جزء HTML كهذا داخل مكوّن التخطيط، فيحصل على الاتجاه والخط والتنسيقات الأساسية. أما المستند الكامل، أي الذي فيه وسم `<html>`، فيُستخدم كما هو تماماً.

## CSS يعمل على كل المحركات {#css}

يدعم المحرك الافتراضي mPDF ‏CSS 2.1، ويدعم Chromium كل شيء، فـ CSS الذي يعمل في mPDF يعمل على الاثنين:

- نسّق الصفحة بالجداول: الترويسة ذات العمودين جدول بخليتين وعرضين بالنسبة المئوية. والعناصر العائمة (float) تعمل أيضاً. أما flexbox وgrid فلا يعملان في mPDF.
- استخدم وحدتي `mm` و`pt` اللتين تطابقان الصفحة.
- استخدم `$doc->start()` و`$doc->end()` في `text-align` بدلاً من `left` و`right`، ليعمل الـ view نفسه في الاتجاهين.
- ضع رؤوس الجداول في `<thead>`: يكررها المحركان أعلى كل صفحة عندما يمتد الجدول إلى ما بعدها.
- يطبع المحركان ألوان الخلفية.
- اعرض الصور بـ `<img src="{{ $doc->image($path) }}">`: يعمل الـ data URI على كل المحركات، ويُفحص المسار مقابل المجلدات المسموح بها. راجع [الصور](/ar/guide/images).

إذا كنت تستخدم Chromium في بيئة الإنتاج، فاعرض الـ view بالمحركين مرة واحدة: ‏`->driver('mpdf')` و`->driver('chromium')`، أو [صفحة المعاينة](/ar/guide/preview) للقوالب. وفي صفحة [محركات PDF](/ar/guide/engines) التفاصيل.

## فواصل الصفحات {#page-breaks}

ابدأ صفحة جديدة بعنصر فارغ:

```blade
<div style="page-break-before: always;"></div>
```

وتنقسم الجداول الطويلة على الصفحات تلقائياً، مع تكرار `<thead>`.

## رأس الصفحة وتذييلها {#header-footer}

ليس للـ view ملف لرأس الصفحة أو تذييلها، فمررهما بلغة HTML. ويتحول `{page}` و`{pages}` إلى رقم الصفحة وعدد الصفحات:

```php
Doc::view('pdf.contract', ['contract' => $contract])
    ->locale('ar')
    ->header('<div style="text-align: left; font-size: 8pt; color: #6B7280;">شركة بيزتك للحلول البرمجية</div>')
    ->footer(view('pdf.partials.footer', ['contract' => $contract])->render())
    ->pdf();
```

```blade
{{-- resources/views/pdf/partials/footer.blade.php --}}
<div style="text-align: center; font-size: 8pt; color: #6B7280;">
    عقد رقم {{ $contract->number }} - صفحة {page} من {pages}
</div>
```

استخدم التنسيقات المضمّنة (inline styles): يرسم Chromium رأس الصفحة وتذييلها بمعزل عن الصفحة، دون CSS الخاص بها. وتتبع الأرقام `->numerals()`، إلا أن Chromium يطبع أرقام الصفحات بالأرقام اللاتينية. والجزء (partial) الذي تنشئه بـ `view()` لا يتلقى `$doc`، فمرر إليه ما يحتاج إليه. راجع [إعدادات الصفحة](/ar/guide/page-settings) للهوامش ومساحة رأس الصفحة وتذييلها.

## الخطوط {#fonts}

يضبط مكوّن التخطيط خط المستند، وهو Cairo افتراضياً، على الصفحة كلها. غيّره لكل مستند بـ `->font('naskh')` أو `->font('tajawal')` أو بخط سجّلته:

```php
Doc::view('pdf.contract', ['contract' => $contract])->locale('ar')->font('naskh')->pdf();
```

ومع mPDF يمكنك أيضاً إعطاء عنصر واحد خطاً آخر من الخطوط المرفقة أو المسجّلة عبر CSS، مثل `font-family: 'naskh';`. أما Chromium وGotenberg فلا يتلقيان إلا خط المستند، فالعنصر الذي تعطيه خطاً آخر يعود معهما إلى خط مثبّت على الخادم، فاضبط الخط بـ `->font()` بدلاً من ذلك. راجع [الخطوط](/ar/guide/fonts) لتسجيل خطوطك.

## لا تمرر مدخلات المستخدم في صورة HTML {#trust}

::: danger خطر
يطبع `Doc::html()` و`{!! !!}` في الـ view نص HTML كما هو تماماً. لا تمرر إليهما أبداً شيئاً كتبه مستخدم أو شيئاً خُزِّن من نموذج إدخال. فـ HTML القادم من الخارج قد يغيّر المستند، أو يجعل المحرك يحمّل ملفات وعناوين.

اطبع كل قيمة بـ `{{ }}` التي تهرّبها (escape)، ويُطبع النص العربي والأرقام بشكل صحيح بعد التهريب. وللحفاظ على فواصل الأسطر في حقل نصي، هرّب النص أولاً ثم أضفها: `{!! nl2br(e($customer->notes)) !!}`.
:::

```blade
<p>{{ $customer->notes }}</p>        {{-- آمن: القيمة مُهرَّبة --}}
<p>{!! $customer->notes !!}</p>      {{-- لا تفعل هذا أبداً مع بيانات المستخدم --}}
```

تهرّب القوالب وبناء المستند بالكود كل قيمة نيابة عنك. راجع [الأمان](/ar/guide/security) لبقية القواعد.
