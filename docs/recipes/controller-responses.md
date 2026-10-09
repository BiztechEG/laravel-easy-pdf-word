# Download, preview or store

One controller that sends the same invoice three ways: as a download, shown in the browser, or stored on S3 with a temporary link. It also covers Arabic file names, returning the file directly, and letting the request choose PDF or Word.

## The situation {#situation}

The customer's order page has three buttons next to each paid order:

- **View invoice** opens the PDF in a new browser tab.
- **Download** saves it, as PDF or, for the accountant, as a Word file.
- **Share link** gives the mobile app or a WhatsApp message a link that works for 30 minutes, without the customer signing in on another device.

The file names are Arabic (`فاتورة-ORD-2026-1024.pdf`), and only the order's customer may get the invoice.

## The solution {#solution}

The invoice itself comes from the `OrderInvoice` class of [Email an invoice](/recipes/email-invoice#solution), which turns an `Order` into a `PendingDocument`. Any `Doc::template()`, `Doc::view()` or `Doc::make()` document works the same way.

::: details The OrderInvoice class
```php
// app/Documents/OrderInvoice.php
namespace App\Documents;

use App\Models\Order;
use App\Models\OrderItem;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class OrderInvoice
{
    public static function for(Order $order): PendingDocument
    {
        return Doc::template('invoice', [
            'invoice' => [
                'number' => $order->number,
                'date' => $order->paid_at,
                'currency' => 'EGP',
                'tax_rate' => 14,
            ],
            'buyer' => [
                'name' => $order->customer->name,
                'address' => $order->shipping_address,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'description' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
            ]),
        ])->locale('ar');
    }
}
```
:::

### The routes

```php
// routes/web.php
use App\Http\Controllers\OrderInvoiceController;

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/invoice', [OrderInvoiceController::class, 'show'])
        ->can('view', 'order')->name('orders.invoice');
    Route::get('/orders/{order}/invoice/download', [OrderInvoiceController::class, 'download'])
        ->can('view', 'order')->name('orders.invoice.download');
    Route::post('/orders/{order}/invoice/share', [OrderInvoiceController::class, 'share'])
        ->can('view', 'order')->name('orders.invoice.share');
});
```

`->can('view', 'order')` runs the `view` method of your `OrderPolicy` with the order from the URL, so a customer cannot open someone else's invoice by changing the number.

### The controller

```php
// app/Http/Controllers/OrderInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\OrderInvoice;
use App\Models\Order;
use BiztechEG\EasyPdfWord\PdfDocument;
use BiztechEG\EasyPdfWord\WordDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OrderInvoiceController extends Controller
{
    /** Show the invoice in the browser. */
    public function show(Order $order): PdfDocument
    {
        return OrderInvoice::for($order)->pdf("فاتورة-{$order->number}.pdf");
    }

    /** Download it, as PDF or Word (?format=word). */
    public function download(Request $request, Order $order): Response
    {
        return $this->file($request, $order)->download();
    }

    /** Store it on S3 and answer with a link that works for 30 minutes. */
    public function share(Request $request, Order $order): JsonResponse
    {
        $file = $this->file($request, $order);
        $path = "invoices/{$order->number}.{$file->extension()}";

        $file->save($path, 's3');

        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30)),
        ]);
    }

    private function file(Request $request, Order $order): PdfDocument|WordDocument
    {
        $format = $request->validate(['format' => ['nullable', 'in:pdf,word']])['format'] ?? 'pdf';
        $name = "فاتورة-{$order->number}";

        return $format === 'word'
            ? OrderInvoice::for($order)->word("{$name}.docx")
            : OrderInvoice::for($order)->pdf("{$name}.pdf");
    }
}
```

In the order page:

```blade
<a href="{{ route('orders.invoice', $order) }}" target="_blank">عرض الفاتورة</a>
<a href="{{ route('orders.invoice.download', $order) }}">تنزيل PDF</a>
<a href="{{ route('orders.invoice.download', [$order, 'format' => 'word']) }}">تنزيل Word</a>
```

## How each response works {#responses}

`->pdf()` and `->word()` return a file object (`PdfDocument` or `WordDocument`). It renders on the first call that needs the bytes, and only once.

| You write | The browser gets |
| --- | --- |
| `return $document->pdf('name.pdf');` | The PDF shown in the browser (`Content-Disposition: inline`) |
| `->stream()` or `->inline()` | The same, written out |
| `->download()` | A download (`Content-Disposition: attachment`) |
| `->download('other-name.pdf')` | A download under another name |
| `->save($path, 's3')` | Nothing: the file is written to the disk and the path is returned |
| `->content()` | The raw bytes, for your own response or API |

**Returning the file directly.** A file object is a Laravel `Responsable`, so `show()` returns it as it is and Laravel shows it inline, under the name given to `->pdf()`. A route closure works the same: `Route::get('/orders/{order}/print', fn (Order $order) => OrderInvoice::for($order)->pdf());`.

**Download names.** `download()` and `stream()` without a name use the one given to `->pdf()` or `->word()`; without any name, the template's name (`invoice.pdf`). The extension is added if it is missing, and slashes are replaced by dashes.

**Arabic file names.** The package sends the name twice in `Content-Disposition`:

```text
attachment; filename=fator-ORD-2026-1024.pdf; filename*=utf-8''%D9%81%D8%A7%D8%AA%D9%88%D8%B1%D8%A9-ORD-2026-1024.pdf
```

Browsers use the UTF-8 name (`filename*`) and save `فاتورة-ORD-2026-1024.pdf`. Old clients that read only `filename` get the name written in Latin letters, `fator-ORD-2026-1024.pdf`, so a Latin part such as the order number keeps it recognisable.

**Choosing PDF or Word.** The request's `format` is validated, so `?format=xlsx` is refused with the usual validation response (a redirect back, or `422` for JSON requests) instead of an error. Word files need `phpoffice/phpword`.

**Storing and sharing.** `save()` writes to any Laravel disk and throws if the disk refuses the write, so a failed save is never reported as a link. `temporaryUrl()` is Laravel's: it works on S3, and on local disks configured with `'serve' => true`. Keep storage paths Latin (`invoices/ORD-2026-1024.pdf`) and use Arabic for the names people see.

## Variations {#variations}

### Render once, serve the stored copy

An invoice does not change after payment. Render it on the first request and send the stored file afterwards:

```php
// app/Http/Controllers/StoredOrderInvoiceController.php
namespace App\Http\Controllers;

use App\Documents\OrderInvoice;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class StoredOrderInvoiceController extends Controller
{
    public function download(Order $order)
    {
        $path = "invoices/{$order->number}.pdf";

        if (! Storage::disk('s3')->exists($path)) {
            OrderInvoice::for($order)->pdf()->save($path, 's3');
        }

        return Storage::disk('s3')->download($path, "فاتورة-{$order->number}.pdf");
    }
}
```

The second request reads the file from S3 and renders nothing. Laravel's `download()` also sends the Arabic name with `filename*`.

### PDF and Word in one download

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$invoice = OrderInvoice::for($order);
$name = "فاتورة-{$order->number}";

return Doc::zip([$invoice->pdf("{$name}.pdf"), $invoice->word("{$name}.docx")], "{$name}.zip")->download();
```

ZIP files need the PHP `zip` extension.

### Look at the HTML while you design

During development, return the HTML that goes to the PDF engine to check it in the browser's inspector:

```php
return response(OrderInvoice::for($order)->toHtml());
```

Do not leave such a route in production. For trying templates with sample data, use the [preview page](/guide/preview).

## Related pages {#related}

- [Output and delivery](/guide/output): all output methods, ZIP files and queued saving.
- [Word files](/guide/word): what the Word version of a template contains.
- [Security](/guide/security): what the package escapes, and what stays your job.
- [Email an invoice](/recipes/email-invoice): the same document as a mail attachment.
- [Tax invoice](/templates/invoice): the template behind `OrderInvoice`.
