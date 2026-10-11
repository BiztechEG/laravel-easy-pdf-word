# Symfony

This page adds the library to a Symfony app: one service for the whole app, controllers that download or show PDF and Word files, Twig for your own designs, and emails with the invoice attached. It uses the library without Laravel, so read [Plain PHP](/guide/plain-php) first if you have not.

The code on this page was run on Symfony 7.4.

## Install {#install}

```bash
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

## One service for the app {#service}

`EasyPdfWord::create()` returns a `DocumentFactory`. Register that factory as a service, so controllers and your own services receive it by its type. A small class creates it with your settings:

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
    # ... the default _defaults and App\ entries

    BiztechEG\EasyPdfWord\DocumentFactory:
        factory: ['@App\Documents\Documents', 'create']
```

- `documents/` holds your own templates; see [Your own templates](/guide/custom-templates).
- mPDF keeps its font cache under `var/cache`, which `cache:clear` empties.
- When an engine fails and mPDF takes over, the warning goes to Symfony's logger.

## Download or show a file {#responses}

A file from the library has its bytes, type and name. This helper turns it into a Symfony response, with a Latin fallback name for an Arabic file name:

```php
// src/Documents/DocumentResponse.php
namespace App\Documents;

use BiztechEG\EasyPdfWord\Output\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class DocumentResponse
{
    /** A download, or the file shown in the browser with $inline. */
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

`AsciiSlugger` is in `symfony/string`, which most apps already have; otherwise `composer require symfony/string`.

The controller receives the factory and returns the files:

```php
// src/Controller/InvoiceController.php
namespace App\Controller;

use App\Documents\DocumentResponse;
use BiztechEG\EasyPdfWord\DocumentFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        // Read the invoice from your database here.
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

- `/ar/invoices/INV-7.pdf` downloads the Arabic invoice and `/en/invoices/INV-7.pdf` the English one; `?view` shows it in the browser. `->locale($request->getLocale())` follows the `{_locale}` in the address. Without it, every document uses the factory's `locale`. Symfony's request locale is `en` unless a route or `framework.default_locale` sets it, so pass it only when your app sets it.
- The seller is the `company` from the service's theme.
- With an Arabic file name, browsers save `فاتورة-INV-7.pdf`; old clients get `fatwrt-INV-7.pdf`.

## Wrong data in an API {#validation}

Template data is checked before anything is rendered. In an API, turn `ValidationFailed` into a 422 response:

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

## Your own design with Twig {#twig}

The library's own views are plain PHP files, but you can design a page in Twig: render the Twig template to HTML and pass it to `html()`. Twig escapes your data as usual, and the library wraps the HTML in a full page with the document's language, direction, fonts and theme:

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

Write the Twig template as the page's content, without `<html>` or `<body>`. [Your own views](/guide/plain-php#views) has the PHP way, with helpers such as `$doc->tafqeet()`.

## Email a file {#mailer}

Symfony Mailer attaches the bytes directly:

```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/invoices/{number}/send', name: 'invoice_send')]
public function send(string $number, MailerInterface $mailer): Response
{
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

For many documents, make them in a Messenger handler instead of during the request, and `->save()` each file or send it from there.
