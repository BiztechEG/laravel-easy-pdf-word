# Email an invoice

When an order is paid, email the customer their Arabic tax invoice as a PDF attachment. This recipe uses a queued Mailable, shows the same with a notification, and covers what makes queued mail with documents reliable.

## The situation {#situation}

An online shop in Egypt sells to consumers. When the payment gateway confirms a payment, the order is marked paid, and the customer should receive an email with their tax invoice (فاتورة ضريبية) as a PDF within a minute.

Three things matter:

- The payment request must stay fast, so the PDF is made and the mail is sent by a queue worker.
- If the payment transaction is rolled back, no mail goes out.
- The invoice shows the order as it is in the database, with VAT and the amount in words.

## The solution {#solution}

### 1. The seller's details

The [tax invoice template](/templates/invoice) takes the seller from the company in the package's theme, so set it once in the config (publish it with `php artisan vendor:publish --tag=easy-pdf-word-config`):

```php
// config/easy-pdf-word.php
'theme' => [
    // primary, text, muted, border, logo ...
    'company' => [
        'name' => 'شركة بيزتك للتجارة الإلكترونية',
        'address' => '15 شارع التحرير، الدقي، الجيزة',
        'phone' => '+20 100 000 0000',
        'tax_number' => '123-456-789',
    ],
],
```

### 2. The models

The shop has three models: `Customer` (`name`, `email`), `Order` (`number`, `customer_id`, `shipping_address`, `status`, `paid_at`) and `OrderItem` (`order_id`, `name`, `quantity`, `unit_price`, `discount`). The order needs these relations:

```php
// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

`OrderItem` casts `unit_price` and `discount` to `decimal:2`, and `Customer` uses the `Notifiable` trait, as `App\Models\User` does.

### 3. One class that builds the invoice

Put the mapping from your models to the template in one small class. The Mailable, the notification and any download controller then make exactly the same document.

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

The template accepts the collection and the `Carbon` date as they are, validates the data, and works out the line totals, the 14% VAT, the total and the amount in words. It returns a `PendingDocument`: nothing is rendered until you ask for a file.

### 4. The Mailable

```php
// app/Mail/OrderInvoiceMail.php
namespace App\Mail;

use App\Documents\OrderInvoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderInvoiceMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "فاتورة طلبك رقم {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.invoice');
    }

    public function attachments(): array
    {
        return [
            OrderInvoice::for($this->order)->pdf("فاتورة-{$this->order->number}.pdf"),
        ];
    }
}
```

A PDF made by `->pdf()` is a mail attachment as it is. The name you give `->pdf()` is the attachment's file name.

The mail body is an ordinary Markdown mail view:

```blade
{{-- resources/views/mail/orders/invoice.blade.php --}}
<x-mail::message>
# شكراً لطلبك

مرحباً {{ $order->customer->name }}،

تم استلام دفع الطلب رقم {{ $order->number }}، وتجد فاتورتك الضريبية مرفقة بهذه الرسالة.

مع التحية،<br>
{{ config('app.name') }}
</x-mail::message>
```

### 5. Send it when the order is paid

Fire an event where the payment is confirmed, inside the same transaction that marks the order paid:

```php
// app/Events/OrderPaid.php
namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
```

```php
// In your payment webhook or controller
use App\Events\OrderPaid;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid', 'paid_at' => now()]);

    OrderPaid::dispatch($order);
});
```

A listener sends the mail. Laravel finds listeners in `app/Listeners` on its own:

```php
// app/Listeners/SendOrderInvoice.php
namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Mail;

class SendOrderInvoice
{
    public function handle(OrderPaid $event): void
    {
        Mail::to($event->order->customer)->send(new OrderInvoiceMail($event->order));
    }
}
```

Because the Mailable implements `ShouldQueueAfterCommit`, `send()` puts it on the queue instead of sending it in the request. Run a worker (`php artisan queue:work`) and the customer receives the mail with `فاتورة-ORD-2026-1024.pdf` attached.

## Queued mail done right {#queued-mail}

Each choice above has a reason:

- **The PDF is made in `attachments()`.** Laravel calls `attachments()` on the worker, when it builds the mail, so the PDF is rendered there and not in the payment request. Do not make the file in the constructor: it would render in the request, and a file waiting to be rendered cannot be serialized onto the queue.
- **The Mailable holds the model, not an array.** `SerializesModels` stores only the order's id. The worker loads the order again, so the invoice shows the data at the time of sending, and the queue payload stays small.
- **`ShouldQueueAfterCommit` waits for the commit.** Without it, a fast worker can take the job before the transaction commits and find an unpaid order, or none. If the transaction rolls back, the mail is never queued.
- **The document sets its own language.** A worker does not run with the locale a middleware set for the customer's request, so `->locale('ar')` is written in the `OrderInvoice` class. See [Each customer in their language](#variations) for the other way.
- **Retries make the file again.** If the mail server fails and the job is retried, `attachments()` runs again and the PDF is rendered again. An invoice takes well under a second with mPDF; keep the worker's `--timeout` (60 seconds by default) above the time of one render plus sending.

## The notification version {#notification}

If you already send order updates as notifications, attach the same file to a `MailMessage`:

```php
// app/Notifications/OrderInvoiceNotification.php
namespace App\Notifications;

use App\Documents\OrderInvoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderInvoiceNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("فاتورة طلبك رقم {$this->order->number}")
            ->greeting("مرحباً {$notifiable->name}")
            ->line('شكراً لك، تم استلام الدفع وتجد فاتورتك الضريبية مرفقة بهذه الرسالة.')
            ->attach(OrderInvoice::for($this->order)->pdf("فاتورة-{$this->order->number}.pdf"));
    }
}
```

```php
$order->customer->notify(new OrderInvoiceNotification($order));
```

The same rules apply: the file is made in `toMail()`, which runs on the worker, and the notification holds the model (Laravel's `Notification` class already uses `SerializesModels`).

## Variations {#variations}

### PDF and Word in one ZIP

Accountants often want the Word copy too. Put both files in one archive:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

public function attachments(): array
{
    $invoice = OrderInvoice::for($this->order);
    $name = "فاتورة-{$this->order->number}";

    return [
        Doc::zip([
            $invoice->pdf("{$name}.pdf"),
            $invoice->word("{$name}.docx"),
        ], "{$name}.zip"),
    ];
}
```

The mail carries `فاتورة-ORD-2026-1024.zip` with the PDF and the Word file inside. ZIP files need the PHP `zip` extension, and Word files need `phpoffice/phpword`. The two files come from the same `PendingDocument`, so they show the same data.

### PDF and Word as two attachments

Without the archive, return both files:

```php
public function attachments(): array
{
    $invoice = OrderInvoice::for($this->order);
    $name = "فاتورة-{$this->order->number}";

    return [
        $invoice->pdf("{$name}.pdf"),
        $invoice->word("{$name}.docx"),
    ];
}
```

### Keep a copy of what was sent

Save the file you attach. It is rendered once: the saved file and the attachment are the same bytes.

```php
public function attachments(): array
{
    $pdf = OrderInvoice::for($this->order)->pdf("فاتورة-{$this->order->number}.pdf");
    $pdf->save("invoices/{$this->order->number}.pdf", 's3');

    return [$pdf];
}
```

### Each customer in their language

Let Laravel pick the language. Implement `HasLocalePreference` on the customer:

```php
// app/Models/Customer.php
namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model implements HasLocalePreference
{
    use Notifiable;

    public function preferredLocale(): string
    {
        return $this->locale ?? 'ar';
    }
}
```

Then remove `->locale('ar')` from `OrderInvoice`. `Mail::to($customer)` builds the mail in the customer's locale, and a document without `->locale()` follows the app locale (unless you set `locale` in the package config). A customer with `locale = 'en'` receives an English, left-to-right invoice; the others an Arabic one. Translate the subject and the mail view with `__()` as well.

## Testing it {#testing}

```php
use App\Events\OrderPaid;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Mail;

Mail::fake();

OrderPaid::dispatch($order);

Mail::assertQueued(OrderInvoiceMail::class, fn (OrderInvoiceMail $mail) => $mail->hasTo($order->customer->email)
    && $mail->attachments()[0]->filename() === "فاتورة-{$order->number}.pdf");
```

`filename()` does not render the PDF, so this test stays fast. To check the invoice's content without rendering, use `Doc::fake()`; see [Testing document features](/recipes/testing-documents) and [Testing your app](/guide/testing).

## Related pages {#related}

- [Tax invoice](/templates/invoice): every field of the `invoice` template.
- [Output and delivery](/guide/output): mail attachments, ZIP files and queued saving.
- [Word files](/guide/word): what the Word copy contains.
- [Configuration](/guide/configuration): the theme and the company details.
- [Download, preview or store](/recipes/controller-responses): the same `OrderInvoice` class behind a controller.
