# البداية السريعة

في عشر دقائق سيكون لديك controller ينزّل فاتورة ضريبية عربية، ثم تضيف إليها الأرقام العربية وألوانك وشعارك ونسخة إنجليزية وملف Word والحفظ على disk والإرسال بالبريد. وكل خطوة تحيلك إلى الصفحة التي تشرحها بالتفصيل.

## 1. التثبيت {#install}

```bash
composer require biztecheg/laravel-easy-pdf-word mpdf/mpdf phpoffice/phpword
```

يثبّت هذا الأمر الحزمة ومحرك mPDF ومكتبة PhpWord لملفات Word. وتشرح صفحة [التثبيت](/ar/guide/installation) المحركات الأخرى وإضافات PHP.

## 2. تنزيل فاتورة عربية {#first-invoice}

أضف route وcontroller. يمرر الـ controller بيانات الفاتورة إلى القالب المرفق `invoice` ويعيد ملف PDF للتنزيل:

```php
// routes/web.php
use App\Http\Controllers\InvoicePdfController;

Route::get('/invoices/{number}/pdf', [InvoicePdfController::class, 'pdf']);
```

```php
// app/Http/Controllers/InvoicePdfController.php
namespace App\Http\Controllers;

use BiztechEG\EasyPdfWord\Facades\Doc;

class InvoicePdfController extends Controller
{
    public function pdf(string $number)
    {
        return Doc::template('invoice', $this->data($number))
            ->locale('ar')
            ->pdf()
            ->download("فاتورة-{$number}.pdf");
    }

    private function data(string $number): array
    {
        return [
            'invoice' => [
                'number' => $number,
                'date' => '2026-10-08',
                'currency' => 'EGP',
                'tax_rate' => 14,
            ],
            'seller' => [
                'name' => 'شركة بيزتك للحلول البرمجية',
                'address' => '15 شارع التحرير، الدقي، الجيزة',
                'tax_number' => '123-456-789',
            ],
            'buyer' => [
                'name' => 'مؤسسة النور للتجارة',
                'address' => 'مدينة نصر، القاهرة',
            ],
            'items' => [
                ['description' => 'تطوير نظام إدارة المخزون', 'quantity' => 1, 'unit_price' => 25000],
                ['description' => 'استضافة سحابية - اشتراك سنوي', 'quantity' => 12, 'unit_price' => 450],
            ],
        ];
    }
}
```

افتح `/invoices/INV-2026-1024/pdf` فينزّل المتصفح الملف `فاتورة-INV-2026-1024.pdf`. وقد تولى القالب الباقي:

- تسير الصفحة من اليمين إلى اليسار، بحروف عربية متصلة وتسميات عربية؛
- يُحسب إجمالي كل بند، والمجموع قبل الضريبة (30,400.00)، وضريبة القيمة المضافة 14% ‏(4,256.00)، والإجمالي (34,656.00)؛
- يُكتب الإجمالي بالحروف: فقط أربعة وثلاثون ألفاً وستمائة وستة وخمسون جنيهاً لا غير؛
- يُضاف التاريخ الهجري تحت تاريخ الإصدار، ويعرض تذييل الصفحة رقم الفاتورة وأرقام الصفحات.

تُفحص البيانات قبل رسم أي شيء. احذف `buyer.name` وستحصل على `ValidationException` برسالة "The buyer.name field is required.". راجع [القوالب الجاهزة](/ar/guide/templates#validation). وفي تطبيق حقيقي تبني هذه المصفوفة من الـ models، وتسرد [صفحة قالب الفاتورة](/ar/templates/invoice) كل الحقول.

## 3. الأرقام العربية {#arabic-digits}

أضف `->numerals('arabic')` لتظهر ١٢٣ بدلاً من 123 في المستند كله، بما في ذلك التواريخ والمبالغ وأرقام الصفحات:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->numerals('arabic')
    ->pdf()
    ->download("فاتورة-{$number}.pdf");
```

المزيد في [دعم اللغة العربية](/ar/guide/arabic).

## 4. ألوانك وشعارك {#theme}

يحدد `->theme()` الألوان والشعار لمستند واحد:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->numerals('arabic')
    ->theme([
        'primary' => '#1D4ED8',
        'logo' => public_path('images/logo.png'),
    ])
    ->pdf()
    ->download("فاتورة-{$number}.pdf");
```

<div class="preview">
  <figure><img src="/images/guide-a/quick-start-invoice-ar.png" alt="فاتورة هذه الصفحة بالأرقام العربية وهوية زرقاء وشعار"><figcaption>الفاتورة بعد الخطوات من 2 إلى 4</figcaption></figure>
</div>

ولاستخدام المظهر نفسه في كل المستندات، ضعه في `config/easy-pdf-word.php` (انشره بالأمر `php artisan vendor:publish --tag=easy-pdf-word-config`). تطبع القوالب بيانات الشركة الموجودة هناك، وتستخدمها الفاتورة بياناتٍ للبائع، فيمكنك حذف `seller` من البيانات:

```php
'theme' => [
    'primary' => '#1D4ED8',
    'text' => '#1F2937',
    'muted' => '#6B7280',
    'border' => '#E5E7EB',
    'logo' => public_path('images/logo.png'),
    'company' => [
        'name' => 'شركة بيزتك للحلول البرمجية',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
        'phone' => '+20 100 000 0000',
        'email' => 'info@biztech.example',
        'tax_number' => '123-456-789',
    ],
],
```

يمكن أن يكون الشعار ملفاً داخل `public/` أو `storage/app` أو `resources/`، أو data URI. راجع [الصور](/ar/guide/images) للروابط والمجلدات الأخرى.

## 5. النسخة الإنجليزية {#english}

غيّر اللغة. تتحول التسميات إلى الإنجليزية وتسير الصفحة من اليسار إلى اليمين:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('en')
    ->pdf()
    ->download("invoice-{$number}.pdf");
```

تُطبع بياناتك كما مررتها، فتبقى الأسماء العربية عربية. ولا تتضمن الفاتورة الإنجليزية التاريخ الهجري ولا التفقيط العربي.

## 6. ملف Word {#word}

ضع `->word()` مكان `->pdf()`. ينتج القالب نفسه بالبيانات نفسها ملف `.docx` بفقرات وجداول من اليمين إلى اليسار:

```php
return Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->word()
    ->download("فاتورة-{$number}.docx");
```

راجع [ملفات Word](/ar/guide/word).

## 7. الحفظ على disk {#save}

بدلاً من إرسال الملف، احتفظ به. تكتب `save()` الملف على أي disk من أنظمة ملفات Laravel وتعيد مساره:

```php
Doc::template('invoice', $this->data($number))
    ->locale('ar')
    ->pdf()
    ->save("invoices/{$number}.pdf", disk: 's3');
```

ولإنشاء الملف وحفظه على queue worker بدلاً من أثناء الطلب، استخدم `->queue("invoices/{$number}.pdf", disk: 's3')`. راجع [الإخراج والتسليم](/ar/guide/output).

## 8. الإرسال بالبريد {#mail}

يمكن إرجاع ملف PDF من الدالة `attachments()` في الـ Mailable كما هو، ويصبح الاسم الذي تعطيه لـ `->pdf()` اسمَ المرفق:

```php
// app/Mail/InvoiceMail.php
namespace App\Mail;

use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(public array $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'فاتورة رقم '.$this->invoice['invoice']['number']);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>مرفق فاتورتكم. شكراً لتعاملكم معنا.</p>');
    }

    public function attachments(): array
    {
        $number = $this->invoice['invoice']['number'];

        return [
            Doc::template('invoice', $this->invoice)->locale('ar')->pdf("فاتورة-{$number}.pdf"),
        ];
    }
}
```

```php
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;

Mail::to('accounts@alnoor.example')->send(new InvoiceMail($data));
```

يُنشأ ملف PDF عند بناء الرسالة، وكذلك في الرسائل التي تُرسل عبر queue. وتضيف حالة الاستخدام [إرسال فاتورة بالبريد](/ar/recipes/email-invoice) إشعاراً (notification) ونسخة تعمل على queue.

## إلى أين بعد ذلك {#next}

- [القوالب الجاهزة](/ar/guide/templates): كيف تعمل البيانات والقيم الافتراضية والتسميات والهوية في كل قالب، و[معرض القوالب](/ar/templates/).
- [قوالبك الخاصة](/ar/guide/custom-templates): عدّل قالباً مرفقاً أو ابنِ قالباً جديداً.
- [بناء المستند بالكود](/ar/guide/builder): تقارير وقوائم أسعار من بياناتك، بصيغتي PDF وWord.
- [حالات الاستخدام](/ar/recipes/): أمثلة كاملة مثل [فاتورة هيئة الزكاة السعودية](/ar/recipes/zatca-invoice) أو [شهادات دورة تدريبية دفعة واحدة](/ar/recipes/certificates).
