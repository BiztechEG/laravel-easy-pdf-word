# صفحة المعاينة

تعرض صفحة المعاينة كل قالب ببياناته التجريبية، فتستطيع تجربة اللغات والأرقام والمحركات في المتصفح وتنزيل الملفات، دون أن تكتب أي كود.

## ما تعرضه الصفحة {#what}

افتح `/doc-preview` في تطبيقك وهو يعمل في بيئة `local`:

![صفحة المعاينة تعرض الفاتورة الضريبية العربية](/images/guide-b/preview-page.png)

- **القائمة الجانبية** فيها كل قالب يستطيع تطبيقك استخدامه: القوالب المرفقة والقوالب الموجودة في `resources/doc-templates`. ويظهر لكل قالب عنوانه واسمه ووصفه، وهل ينشئ ملفات PDF وWord، ومصدره: `package` للقالب المرفق، و`project` لقالب من قوالبك.
- **Language** تبدّل بين لغات القالب (`ar` و`en` للقوالب المرفقة).
- **Digits** تبدّل بين الأرقام اللاتينية (123) والعربية (١٢٣).
- **Engine** تنشئ الملف بالمحرك الافتراضي، أو بـ `mpdf` أو `chromium` أو `gotenberg`. والمحرك غير المُعد يرجع إلى المحرك الاحتياطي كالمعتاد (انظر [محركات PDF](/ar/guide/engines#fallback)).
- **View** تعرض ملف PDF، أو الـ HTML الذي يتسلمه المحرك، وهذا مفيد حين تعمل على تخطيط.
- **Open PDF** تفتح ملف PDF في تبويب جديد، و**Download Word** تنزّل ملف Word، للقوالب التي تنشئهما.

تنشئ الصفحة كل قالب ببياناته التجريبية `sample` من ملف `template.php` الخاص به، فما تراه هو بالضبط ما ينتجه القالب. وحين تعدّل قالبًا من قوالبك، أعد تحميل الصفحة لترى التغيير.

## روابط مباشرة {#links}

كل معاينة رابط عادي، يمكنك فتحه أو مشاركته مع فريقك:

```text
/doc-preview?template=invoice
/doc-preview/invoice?locale=ar&numerals=arabic
/doc-preview/invoice?locale=en&engine=chromium
/doc-preview/receipt?format=html
/doc-preview/payslip?locale=ar&format=docx
```

| المعامل | القيم | القيمة الافتراضية |
| --- | --- | --- |
| `locale` | إحدى لغات القالب | أول لغة في القالب |
| `numerals` | `latin` و`arabic` | `latin` |
| `engine` | `mpdf` و`chromium` و`gotenberg` | المحرك الافتراضي للتطبيق |
| `format` | `pdf` و`html` و`docx` (أو `word`) | `pdf` |

القيم الخارجة عن هذه القوائم ترجع إلى القيمة الافتراضية. وفي Blade، اربط بالصفحة عبر أسماء الـ routes الخاصة بها: `route('easy-pdf-word.preview.index')` و`route('easy-pdf-word.preview.show', 'invoice')`.

## تفعيلها خارج `local` {#outside-local}

الصفحة مفعلة في بيئة `local` ومعطلة في كل بيئة أخرى. لتفعيلها على خادم تجريبي (staging)، أو للمديرين في بيئة الإنتاج:

```dotenv
DOC_PREVIEW=true
```

خارج `local` تحتاج الصفحة أيضًا إلى الـ gate المسمى `viewDocPreview`، فلا يفتحها إلا من تختارهم. عرّفه في service provider:

```php
// app/Providers/AppServiceProvider.php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDocPreview', fn (User $user) => in_array($user->email, [
        'khaled@biztech.example',
        'sara@biztech.example',
    ]));
}
```

بدون الـ gate، أو لمن يرفضه (والزوار غير المسجلين منهم)، تجيب الصفحة بالرمز 403. ويعطّل `DOC_PREVIEW=false` الصفحة في `local` أيضًا. أما `DOC_PREVIEW=` الفارغة فمثل عدم وجود السطر: مفعلة في `local` فقط.

لا تنشئ الصفحة إلا البيانات التجريبية للقوالب، ولا تعرض بيانات تطبيقك أبدًا. لكن الإنشاء يستهلك المعالج، فأبقها خلف الـ gate.

## المسار والـ middleware {#config}

يُحدَّد عنوان الصفحة والـ middleware الخاص بها في `config/easy-pdf-word.php`:

```php
'preview' => [
    'enabled' => env('DOC_PREVIEW'),
    'path' => 'admin/doc-preview',
    'middleware' => ['web', 'auth'],
],
```

| المفتاح | القيمة الافتراضية | المعنى |
| --- | --- | --- |
| `enabled` | `env('DOC_PREVIEW')` | `null` أو فارغة: مفعلة في `local` فقط. `true` أو `false`: مفعلة أو معطلة في كل مكان. |
| `path` | `'doc-preview'` | رابط الصفحة. |
| `middleware` | `['web']` | الـ middleware لـ routes الصفحة. أضف `auth` لتحويل الزوار غير المسجلين إلى صفحة تسجيل الدخول بدلًا من 403. |

يُضاف فحص الـ gate دائمًا بعد الـ middleware الخاص بك. وتُسجَّل الـ routes عند إقلاع التطبيق، فغيّر هذه الإعدادات في ملف الإعدادات أو `.env`، لا أثناء التشغيل.

ترسل الصفحة سياسة أمان محتوى (Content Security Policy) صارمة، ويعمل عرض HTML في sandbox بلا سكربتات.

## تطوير الحزمة {#package-development}

حين تعمل على الحزمة نفسها، أو على قوالب في نسخة منها، شغّل الصفحة بلا تطبيق:

```bash
git clone https://github.com/BiztechEG/laravel-easy-pdf-word.git
cd laravel-easy-pdf-word
composer install
composer preview
```

يشغّل `composer preview` الصفحة على `http://127.0.0.1:8000/doc-preview`، في بيئة `local` ومع تفعيل الصفحة.
