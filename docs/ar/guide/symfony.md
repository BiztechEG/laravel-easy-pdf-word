# Symfony

تضيف هذه الصفحة المكتبة إلى تطبيق Symfony: خدمة (service) واحدة للتطبيق كله، وcontrollers تنزّل ملفات PDF وWord أو تعرضها، وTwig لتصميماتك الخاصة، ورسائل بريد مرفق بها الفاتورة. تستخدم المكتبة بدون Laravel، فاقرأ أولًا صفحة [PHP بدون إطار عمل](/ar/guide/plain-php) إن لم تكن قد قرأتها.

جُرّب الكود في هذه الصفحة على Symfony 7.4.

## التثبيت {#install}

```bash
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

## خدمة واحدة للتطبيق {#service}

تعيد `EasyPdfWord::create()` كائن `DocumentFactory`. سجّله خدمةً، فتستقبله الـ controllers وخدماتك الخاصة بنوعه. وتنشئه كلاس صغيرة بإعداداتك:

```php
// src/Documents/Documents.php
namespace App\Documents;

use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Documents
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%kernel.cache_dir%')] private string $cacheDir,
    ) {}

    public function create(): DocumentFactory
    {
        return EasyPdfWord::create([
            'locale' => 'ar',
            'theme' => [
                'logo' => $this->projectDir.'/assets/logo.png',
                'company' => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
            ],
            'images' => ['paths' => [$this->projectDir.'/assets']],
            'templates' => ['paths' => [$this->projectDir.'/documents']],
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => $this->cacheDir.'/easy-pdf-word']]],
        ], [
            'warn' => fn (string $message) => $this->logger->warning($message),
        ]);
    }
}
```

```yaml
# config/services.yaml
services:
    # ... إدخالات _defaults و App\ الافتراضية

    BiztechEG\EasyPdfWord\DocumentFactory:
        factory: ['@App\Documents\Documents', 'create']
```

- يحتوي `documents/` على قوالبك الخاصة؛ راجع [قوالبك الخاصة](/ar/guide/custom-templates).
- يحفظ mPDF ذاكرة الخطوط المؤقتة داخل `var/cache`، ويفرّغها الأمر `cache:clear`.
- عندما يفشل محرك ويتولى mPDF المهمة، أو تُترك صورة بسبب `images.paths` أو `images.remote`، يذهب التحذير إلى الـ logger الخاص بـ Symfony.

## تنزيل الملف أو عرضه {#responses}

للملف الناتج من المكتبة محتواه ونوعه واسمه. وتحوّله هذه الدالة المساعدة إلى response في Symfony، مع اسم بحروف لاتينية بديلًا عن اسم الملف العربي:

```php
// src/Documents/DocumentResponse.php
namespace App\Documents;

use BiztechEG\EasyPdfWord\Output\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class DocumentResponse
{
    /** تنزيل، أو عرض الملف في المتصفح مع $inline. */
    public static function from(File $file, bool $inline = false): Response
    {
        $name = $file->filename();
        $fallback = (new AsciiSlugger())->slug(pathinfo($name, PATHINFO_FILENAME))->toString() ?: 'document';

        return new Response($file->content(), Response::HTTP_OK, [
            'Content-Type' => $file->mimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                $fallback.'.'.$file->extension(),
            ),
        ]);
    }
}
```

توجد `AsciiSlugger` في `symfony/string`، وهي موجودة في أغلب التطبيقات؛ وإلا فشغّل `composer require symfony/string`.

ويستقبل الـ controller الـ factory ويعيد الملفات:

```php
// src/Controller/InvoiceController.php
namespace App\Controller;

use App\Documents\DocumentResponse;
use BiztechEG\EasyPdfWord\DocumentFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class InvoiceController extends AbstractController
{
    public function __construct(private DocumentFactory $docs) {}

    #[Route('/{_locale}/invoices/{number}.pdf', name: 'invoice_pdf', requirements: ['_locale' => 'ar|en'])]
    public function pdf(string $number, Request $request): Response
    {
        $file = $this->docs->template('invoice', $this->invoice($number))
            ->locale($request->getLocale())
            ->pdf("فاتورة-{$number}.pdf");

        return DocumentResponse::from($file, inline: $request->query->has('view'));
    }

    #[Route('/invoices/{number}.docx', name: 'invoice_word')]
    public function word(string $number): Response
    {
        return DocumentResponse::from($this->docs->template('invoice', $this->invoice($number))->word("invoice-{$number}.docx"));
    }

    #[Route('/invoices/{number}.zip', name: 'invoice_zip')]
    public function zip(string $number): Response
    {
        $invoice = $this->docs->template('invoice', $this->invoice($number));

        return DocumentResponse::from($this->docs->zip([
            "invoice-{$number}.pdf" => $invoice->pdf(),
            "invoice-{$number}.docx" => $invoice->word(),
        ], "invoice-{$number}.zip"));
    }

    private function invoice(string $number): array
    {
        // اقرأ الفاتورة من قاعدة بياناتك هنا، ثم تحقق من أن المستخدم يحق له رؤيتها:
        // $this->denyAccessUnlessGranted('view', $invoice);
        return [
            'invoice' => ['number' => $number, 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
            'buyer' => ['name' => 'مؤسسة النور', 'address' => 'مدينة نصر، القاهرة'],
            'items' => [
                ['description' => 'تطوير نظام إدارة المخزون', 'quantity' => 1, 'unit_price' => 25000],
                ['description' => 'استضافة سحابية - اشتراك سنوي', 'quantity' => 12, 'unit_price' => 450],
            ],
        ];
    }
}
```

- ينزّل `/ar/invoices/INV-7.pdf` الفاتورة العربية و`/en/invoices/INV-7.pdf` الإنجليزية، ويعرضها `?view` في المتصفح. وتتبع `->locale($request->getLocale())` قيمة `{_locale}` في العنوان، وبدونها تستخدم كل المستندات قيمة `locale` في الـ factory. ولغة الطلب في Symfony هي `en` ما لم يحددها route أو `framework.default_locale`، فمررها فقط إن كان تطبيقك يضبطها.
- تعني `#[IsGranted('ROLE_USER')]` على الصنف (من `symfony/security-bundle`) أن كل route يحتاج مستخدمًا مسجّل الدخول. وهذا لا يكفي وحده: فالمستخدم المسجّل يستطيع تغيير الرقم في العنوان وتنزيل فاتورة شخص آخر. لذلك تحقق في `invoice()` من أن المستخدم يحق له رؤية هذه الفاتورة، مثلًا بـ [voter](https://symfony.com/doc/current/security/voters.html) على كيان الفاتورة و`$this->denyAccessUnlessGranted('view', $invoice)`، الذي يرد برمز 403.
- المورد هو `company` في الـ theme الخاص بالخدمة.
- مع اسم ملف عربي تحفظ المتصفحات الملف باسم `فاتورة-INV-7.pdf`، وتحصل البرامج القديمة على `fatwrt-INV-7.pdf`.

## البيانات الخاطئة في API {#validation}

يُتحقق من بيانات القالب قبل إنشاء أي شيء. وفي الـ API حوّل `ValidationFailed` إلى response برمز 422:

```php
use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/invoices', name: 'invoice_from_json', methods: ['POST'])]
public function fromJson(Request $request): Response
{
    try {
        return DocumentResponse::from($this->docs->template('invoice', $request->toArray())->pdf());
    } catch (ValidationFailed $e) {
        return new JsonResponse(['errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
```

```json
{"errors": {"invoice.number": ["The invoice.number field is required."], "buyer.name": ["The buyer.name field is required."]}}
```

## تصميمك الخاص بـ Twig {#twig}

الـ views الخاصة بالمكتبة ملفات PHP عادية، لكن يمكنك تصميم الصفحة بـ Twig: حوّل قالب Twig إلى HTML ومرّره إلى `html()`. يهرّب Twig بياناتك كالمعتاد، وتضع المكتبة الـ HTML داخل صفحة كاملة بلغة المستند واتجاهه وخطوطه وألوانه:

```twig
{# templates/pdf/contract.html.twig #}
<h1>{{ contract.title }}</h1>
<p>{{ contract.client }}</p>
<p>{{ contract.total|number_format(2) }} EGP</p>
```

```php
#[Route('/contracts/{id}.pdf', name: 'contract_pdf')]
public function contract(int $id): Response
{
    $html = $this->renderView('pdf/contract.html.twig', [
        'contract' => ['title' => 'عقد صيانة', 'client' => 'مؤسسة النور', 'total' => 1250.5],
    ]);

    return DocumentResponse::from($this->docs->html($html)->title('عقد صيانة')->pdf("contract-{$id}.pdf"), inline: true);
}
```

اكتب قالب Twig على أنه محتوى الصفحة، بدون `<html>` أو `<body>`. وفي [الـ views الخاصة بك](/ar/guide/plain-php#views) طريقة PHP مع أدوات مثل `$doc->tafqeet()`.

## إرسال الملف بالبريد {#mailer}

يرفق Symfony Mailer المحتوى مباشرة:

```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/invoices/{number}/send', name: 'invoice_send', methods: ['POST'])]
public function send(string $number, Request $request, MailerInterface $mailer): Response
{
    if (! $this->isCsrfTokenValid('send-invoice-'.$number, $request->request->getString('_token'))) {
        throw $this->createAccessDeniedException();
    }

    $file = $this->docs->template('invoice', $this->invoice($number))->pdf("invoice-{$number}.pdf");

    $mailer->send((new Email())
        ->from('billing@example.com')
        ->to('client@example.com')
        ->subject("Invoice {$number}")
        ->text('Your invoice is attached.')
        ->attach($file->content(), $file->filename(), $file->mimeType()));

    return new Response('sent');
}
```

إرسال بريد يغيّر شيئًا، لذلك لا يقبل الـ route إلا `POST` ويتحقق من رمز CSRF ‏(`symfony/security-csrf`)؛ فلا يستطيع رابط أو صورة في موقع آخر إرساله. والزر نموذج صغير:

```twig
<form method="post" action="{{ path('invoice_send', {number: number}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('send-invoice-' ~ number) }}">
    <button>أرسل الفاتورة بالبريد</button>
</form>
```

ومع المستندات الكثيرة، أنشئها في handler ‏Messenger بدلًا من أثناء الطلب، واحفظ كل ملف بـ `->save()` أو أرسله من هناك.
