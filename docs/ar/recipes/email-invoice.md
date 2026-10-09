# إرسال فاتورة بالبريد

عندما يُدفع الطلب، أرسل إلى العميل فاتورته الضريبية العربية ملفَّ PDF مرفقاً ببريد إلكتروني. تستخدم حالة الاستخدام هذه Mailable يعمل عبر الـ queue، وتعرض الطريقة نفسها بإشعار (notification)، وتشرح ما يجعل إرسال المستندات عبر الـ queue موثوقاً.

## السيناريو {#situation}

متجر إلكتروني في مصر يبيع للمستهلكين. عندما تؤكد بوابة الدفع عملية الدفع، يُعلَّم الطلب مدفوعاً، ويجب أن تصل إلى العميل خلال دقيقة رسالة بريد فيها فاتورته الضريبية بصيغة PDF.

ثلاثة أمور مهمة:

- يجب أن يبقى طلب الدفع سريعاً، لذلك يُنشأ ملف PDF وتُرسل الرسالة من خلال queue worker.
- إذا أُلغيت معاملة الدفع (rollback) فلا تُرسل أي رسالة.
- تعرض الفاتورة الطلب كما هو في قاعدة البيانات، مع ضريبة القيمة المضافة والتفقيط (المبلغ كتابةً).

## الحل {#solution}

### 1. بيانات البائع

يأخذ [قالب الفاتورة الضريبية](/ar/templates/invoice) بيانات البائع من بيانات الشركة في الهوية (theme) الخاصة بالحزمة، فاضبطها مرة واحدة في ملف الإعدادات (انشره بالأمر `php artisan vendor:publish --tag=easy-pdf-word-config`):

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

### 2. الـ models

في المتجر ثلاثة models: `Customer` (`name`، `email`) و`Order` (`number`، `customer_id`، `shipping_address`، `status`، `paid_at`) و`OrderItem` (`order_id`، `name`، `quantity`، `unit_price`، `discount`). يحتاج الطلب إلى هذه العلاقات:

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

يحوّل `OrderItem` الحقلين `unit_price` و`discount` إلى `decimal:2`، ويستخدم `Customer` الـ trait `Notifiable` كما يفعل `App\Models\User`.

### 3. كلاس واحد يبني الفاتورة

ضع تحويل بيانات الـ models إلى بيانات القالب في كلاس صغير واحد. بذلك يُنشئ الـ Mailable والإشعار وأي controller للتنزيل المستندَ نفسه تماماً.

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

يقبل القالب الـ collection والتاريخ من نوع `Carbon` كما هما، ويتحقق من البيانات، ويحسب إجمالي كل سطر وضريبة القيمة المضافة 14% والإجمالي والتفقيط. والنتيجة `PendingDocument`: لا يُولَّد شيء حتى تطلب ملفاً.

### 4. الـ Mailable

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

ملف PDF الناتج عن `->pdf()` يصلح مرفقاً كما هو، والاسم الذي تعطيه لـ `->pdf()` هو اسم المرفق.

ونص الرسالة view عادي بصيغة Markdown:

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

### 5. الإرسال عند دفع الطلب

أطلق حدثاً (event) في المكان الذي يُؤكَّد فيه الدفع، داخل المعاملة نفسها التي تعلّم الطلب مدفوعاً:

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
// في الـ webhook الخاص بالدفع أو في الـ controller
use App\Events\OrderPaid;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid', 'paid_at' => now()]);

    OrderPaid::dispatch($order);
});
```

ويرسل listener الرسالة. يعثر Laravel على الـ listeners في `app/Listeners` تلقائياً:

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

لأن الـ Mailable يطبّق `ShouldQueueAfterCommit`، فإن `send()` يضعه في الـ queue بدلاً من إرساله أثناء الطلب. شغّل worker بالأمر `php artisan queue:work`، فتصل إلى العميل رسالة مرفق بها `فاتورة-ORD-2026-1024.pdf`.

## الـ queue بالطريقة الصحيحة {#queued-mail}

لكل اختيار في الحل سبب:

- **يُنشأ ملف PDF داخل `attachments()`.** يستدعي Laravel الدالة `attachments()` على الـ worker عند بناء الرسالة، فيُولَّد الملف هناك لا أثناء طلب الدفع. لا تُنشئ الملف في الـ constructor: سيُولَّد عندها أثناء الطلب، والملف الذي ينتظر التوليد لا يمكن تحويله (serialize) لوضعه في الـ queue.
- **يحمل الـ Mailable الـ model لا مصفوفة.** يخزّن `SerializesModels` رقم الطلب (id) فقط، ثم يقرأ الـ worker الطلب من جديد، فتعرض الفاتورة البيانات كما هي وقت الإرسال، ويبقى حجم المهمة في الـ queue صغيراً.
- **ينتظر `ShouldQueueAfterCommit` تأكيد المعاملة (commit).** من دونه قد يلتقط worker سريع المهمة قبل تأكيد المعاملة فيجد طلباً غير مدفوع أو لا يجده أصلاً. وإذا أُلغيت المعاملة فلن توضع الرسالة في الـ queue.
- **يحدد المستند لغته بنفسه.** لا يعمل الـ worker باللغة التي ضبطها middleware لطلب العميل، لذلك كُتب `->locale('ar')` في الكلاس `OrderInvoice`. وانظر [كل عميل بلغته](#variations) للطريقة الأخرى.
- **إعادة المحاولة تعيد توليد الملف.** إذا فشل خادم البريد وأُعيدت المهمة، تُستدعى `attachments()` من جديد ويُولَّد ملف PDF مرة أخرى. تستغرق الفاتورة أقل من ثانية بكثير مع mPDF؛ اجعل `--timeout` الخاص بالـ worker (60 ثانية افتراضياً) أكبر من زمن توليد واحد مع الإرسال.

## نسخة الإشعار {#notification}

إذا كنت ترسل تحديثات الطلبات إشعاراتٍ (notifications)، فأرفق الملف نفسه بـ `MailMessage`:

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

القواعد نفسها تنطبق هنا: يُنشأ الملف داخل `toMail()` التي تعمل على الـ worker، ويحمل الإشعار الـ model (الكلاس `Notification` في Laravel يستخدم `SerializesModels` أصلاً).

## تنويعات {#variations}

### ملف PDF وملف Word في ملف ZIP واحد

كثيراً ما يطلب المحاسبون نسخة Word أيضاً. ضع الملفين في أرشيف واحد:

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

تحمل الرسالة الملف `فاتورة-ORD-2026-1024.zip` وبداخله ملف PDF وملف Word. تحتاج ملفات ZIP إلى إضافة `zip` في PHP، وتحتاج ملفات Word إلى الحزمة `phpoffice/phpword`. والملفان من `PendingDocument` واحد، فيعرضان البيانات نفسها.

### ملف PDF وملف Word مرفقين منفصلين

ومن دون أرشيف، أعد الملفين:

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

### الاحتفاظ بنسخة مما أُرسل

احفظ الملف الذي ترفقه. يُولَّد الملف مرة واحدة: الملف المحفوظ والمرفق هما البايتات نفسها.

```php
public function attachments(): array
{
    $pdf = OrderInvoice::for($this->order)->pdf("فاتورة-{$this->order->number}.pdf");
    $pdf->save("invoices/{$this->order->number}.pdf", 's3');

    return [$pdf];
}
```

### كل عميل بلغته

دع Laravel يختار اللغة. طبّق `HasLocalePreference` على العميل:

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

ثم احذف `->locale('ar')` من `OrderInvoice`. يبني `Mail::to($customer)` الرسالة بلغة العميل، والمستند الذي لا يحدد `->locale()` يتبع لغة التطبيق (ما لم تضبط `locale` في إعدادات الحزمة). يتلقى العميل الذي لغته `en` فاتورة إنجليزية من اليسار إلى اليمين، ويتلقى الباقون فاتورة عربية. وترجم عنوان الرسالة والـ view أيضاً باستخدام `__()`.

## اختبار الحل {#testing}

```php
use App\Events\OrderPaid;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Mail;

Mail::fake();

OrderPaid::dispatch($order);

Mail::assertQueued(OrderInvoiceMail::class, fn (OrderInvoiceMail $mail) => $mail->hasTo($order->customer->email)
    && $mail->attachments()[0]->filename() === "فاتورة-{$order->number}.pdf");
```

لا تولّد `filename()` ملف PDF، فيبقى الاختبار سريعاً. ولفحص محتوى الفاتورة دون توليدها استخدم `Doc::fake()`؛ انظر [اختبار ميزات المستندات](/ar/recipes/testing-documents) و[اختبار تطبيقك](/ar/guide/testing).

## صفحات ذات صلة {#related}

- [الفاتورة الضريبية](/ar/templates/invoice): كل حقول القالب `invoice`.
- [الإخراج والتسليم](/ar/guide/output): المرفقات وملفات ZIP والحفظ عبر الـ queue.
- [ملفات Word](/ar/guide/word): ما تحتويه نسخة Word.
- [الإعدادات](/ar/guide/configuration): الهوية وبيانات الشركة.
- [تنزيل أو عرض أو حفظ](/ar/recipes/controller-responses): الكلاس `OrderInvoice` نفسه خلف controller.
