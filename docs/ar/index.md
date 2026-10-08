---
layout: home

hero:
  name: Laravel Easy PDF & Word
  text: مستندات بأي لغة، والعربية كما يجب
  tagline: أنشئ ملفات PDF و Word من Laravel بقوالب جاهزة للفواتير وعروض الأسعار وقسائم الرواتب والعقود وغيرها. الاتجاه من اليمين لليسار، والحروف المتصلة، والتاريخ الهجري، وتفقيط المبالغ، كلها تعمل مباشرة.
  image:
    src: /previews/invoice-ar.png
    alt: فاتورة ضريبية عربية من إنتاج المكتبة
  actions:
    - theme: brand
      text: ابدأ الآن
      link: /ar/guide/quick-start
    - theme: alt
      text: تصفح القوالب
      link: /ar/templates/
    - theme: alt
      text: حالات الاستخدام
      link: /ar/recipes/

features:
  - icon: 🔤
    title: عربية تظهر بشكل صحيح
    details: حروف متصلة، وصفحات وجداول من اليمين لليسار، ونص مختلط عربي وإنجليزي، وأرقام عربية أو لاتينية، وتاريخ هجري، وتفقيط المبالغ.
  - icon: 📄
    title: PDF و Word من نفس البيانات
    details: كل قالب ينتج ملف PDF وملف Word. ابنِ المستند بالكود مرة واحدة واحصل على الصيغتين، أو املأ ملف docx صممته في Word.
  - icon: 🧾
    title: اثنا عشر قالبًا جاهزًا
    details: فاتورة ضريبية برمز QR لهيئة الزكاة، وفاتورة إلكترونية مصرية، وإشعار دائن، وعرض سعر، وأمر شراء، وإذن تسليم، وسند قبض، وقسيمة راتب، وعقد، وشهادة، وخطاب، وتقرير.
  - icon: ⚙️
    title: محركان لملفات PDF
    details: mPDF يعمل في أي مكان يعمل فيه PHP حتى الاستضافة المشتركة، و Chromium (عبر Browsershot أو Gotenberg) يدعم CSS الحديثة كاملة. بدّل بينهما لكل مستند مع رجوع تلقائي.
  - icon: 📬
    title: تنزيل أو بريد أو ZIP أو queue
    details: أرجع الملف من الـ controller، أو أرفقه في رسالة بريد، أو اجمع عدة ملفات في ZIP، أو اترك الـ queue worker ينشئه ويحفظه على S3.
  - icon: ✅
    title: مصممة لتطبيقات حقيقية
    details: بيانات القوالب يتم التحقق منها، ومدخلات المستخدم تُهرَّب، ومسارات الصور مقيدة، و Doc::fake() يجعل اختباراتك سريعة.
---

## فاتورة عربية في ثلاثة أسطر

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::template('invoice', $data)
    ->locale('ar')
    ->pdf()
    ->download('فاتورة-1024.pdf');
```

نفس الاستدعاء مع `->word()` ينتج ملف Word، ومع `->locale('en')` ينتج نسخة إنجليزية. راجع [البداية السريعة](/ar/guide/quick-start) للمثال الكامل، أو [معرض القوالب](/ar/templates/) لترى شكل كل قالب.
