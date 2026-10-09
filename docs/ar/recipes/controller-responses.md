# تنزيل أو عرض أو حفظ

controller واحد يرسل الفاتورة نفسها بثلاث طرق: تنزيلاً، أو عرضاً في المتصفح، أو حفظاً على S3 مع رابط مؤقت. ويتناول أيضاً أسماء الملفات العربية، وإعادة الملف مباشرة، واختيار PDF أو Word من الطلب.

## السيناريو {#situation}

في صفحة طلبات العميل ثلاثة أزرار بجانب كل طلب مدفوع:

- **عرض الفاتورة** يفتح ملف PDF في تبويب جديد.
- **تنزيل** يحفظ الملف بصيغة PDF، أو بصيغة Word للمحاسب.
- **مشاركة رابط** يعطي تطبيق الجوال أو رسالة واتساب رابطاً يعمل 30 دقيقة، دون أن يسجّل العميل دخوله على جهاز آخر.

أسماء الملفات عربية (`فاتورة-ORD-2026-1024.pdf`)، ولا يحصل على الفاتورة إلا صاحب الطلب.

## الحل {#solution}

تأتي الفاتورة نفسها من الكلاس `OrderInvoice` في [إرسال فاتورة بالبريد](/ar/recipes/email-invoice#solution)، الذي يحوّل `Order` إلى `PendingDocument`. ويعمل أي مستند من `Doc::template()` أو `Doc::view()` أو `Doc::make()` بالطريقة نفسها.

::: details الكلاس OrderInvoice
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

### الـ routes

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

يشغّل `->can('view', 'order')` الدالة `view` في الـ policy ‏`OrderPolicy` مع الطلب القادم في الرابط، فلا يستطيع عميل فتح فاتورة غيره بتغيير الرقم.

### الـ controller

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

وفي صفحة الطلب:

```blade
<a href="{{ route('orders.invoice', $order) }}" target="_blank">عرض الفاتورة</a>
<a href="{{ route('orders.invoice.download', $order) }}">تنزيل PDF</a>
<a href="{{ route('orders.invoice.download', [$order, 'format' => 'word']) }}">تنزيل Word</a>
```

## كيف تعمل كل استجابة {#responses}

تعيد `->pdf()` و`->word()` كائن ملف (`PdfDocument` أو `WordDocument`). يُولَّد الملف عند أول استدعاء يحتاج إلى محتواه، ومرة واحدة فقط.

| ما تكتبه | ما يصل إلى المتصفح |
| --- | --- |
| `return $document->pdf('name.pdf');` | ملف PDF معروضاً في المتصفح (`Content-Disposition: inline`) |
| `->stream()` أو `->inline()` | الشيء نفسه بصيغة صريحة |
| `->download()` | تنزيل (`Content-Disposition: attachment`) |
| `->download('other-name.pdf')` | تنزيل باسم آخر |
| `->save($path, 's3')` | لا شيء: يُكتب الملف على الـ disk ويُعاد المسار |
| `->content()` | البايتات الخام، لاستجابتك الخاصة أو لواجهة API |

**إعادة الملف مباشرة.** كائن الملف من نوع `Responsable` في Laravel، لذلك تعيده `show()` كما هو فيعرضه Laravel في المتصفح باسم الملف المعطى لـ `->pdf()`. والأمر نفسه في route بدالة closure: ‏`Route::get('/orders/{order}/print', fn (Order $order) => OrderInvoice::for($order)->pdf());`.

**أسماء التنزيل.** إذا استُدعيت `download()` و`stream()` بلا اسم فإنهما تستخدمان الاسم المعطى لـ `->pdf()` أو `->word()`، وإن لم يوجد اسم فاسم القالب (`invoice.pdf`). يُضاف الامتداد إذا كان ناقصاً، وتُستبدل الشرطات المائلة بشرطات عادية.

**أسماء الملفات العربية.** ترسل الحزمة الاسم مرتين في `Content-Disposition`:

```text
attachment; filename=____________-ORD-2026-1024.pdf; filename*=utf-8''%D9%81%D8%A7%D8%AA%D9%88%D8%B1%D8%A9-ORD-2026-1024.pdf
```

تستخدم المتصفحات الاسم بترميز UTF-8 ‏(`filename*`) فتحفظ الملف باسم `فاتورة-ORD-2026-1024.pdf`. أما البرامج القديمة التي لا تقرأ إلا `filename` فتحصل على شرطات سفلية مكان الحروف العربية، ولهذا يفيد وجود جزء لاتيني في الاسم مثل رقم الطلب.

**اختيار PDF أو Word.** يُتحقق من قيمة `format` في الطلب، فيُرفض `?format=xlsx` باستجابة التحقق المعتادة (إعادة توجيه إلى الصفحة السابقة، أو `422` لطلبات JSON) بدلاً من خطأ. تحتاج ملفات Word إلى `phpoffice/phpword`.

**الحفظ والمشاركة.** تكتب `save()` على أي disk في Laravel، وترمي استثناءً إذا رفض الـ disk الكتابة، فلا يُعطى رابط لملف لم يُحفظ. أما `temporaryUrl()` فمن Laravel: تعمل مع S3، ومع الـ disks المحلية المضبوطة بـ `'serve' => true`. اجعل مسارات التخزين لاتينية (`invoices/ORD-2026-1024.pdf`) واستخدم العربية في الأسماء التي يراها الناس.

## تنويعات {#variations}

### ولّد مرة واحدة وأرسل النسخة المحفوظة

الفاتورة لا تتغير بعد الدفع. ولّدها عند أول طلب وأرسل الملف المحفوظ بعد ذلك:

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

في الطلب الثاني يُقرأ الملف من S3 دون توليد أي شيء. وترسل `download()` الخاصة بـ Laravel الاسم العربي أيضاً عبر `filename*`.

### ملف PDF وملف Word في تنزيل واحد

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$invoice = OrderInvoice::for($order);
$name = "فاتورة-{$order->number}";

return Doc::zip([$invoice->pdf("{$name}.pdf"), $invoice->word("{$name}.docx")], "{$name}.zip")->download();
```

تحتاج ملفات ZIP إلى إضافة `zip` في PHP.

### مراجعة الـ HTML أثناء التصميم

أثناء التطوير، أعد الـ HTML الذي يذهب إلى محرك PDF لتفحصه بأدوات المطور في المتصفح:

```php
return response(OrderInvoice::for($order)->toHtml());
```

لا تترك route كهذا في بيئة الإنتاج. ولتجربة القوالب ببياناتها التجريبية استخدم [صفحة المعاينة](/ar/guide/preview).

## صفحات ذات صلة {#related}

- [الإخراج والتسليم](/ar/guide/output): كل طرق الإخراج وملفات ZIP والحفظ عبر الـ queue.
- [ملفات Word](/ar/guide/word): ما تحتويه نسخة Word من القالب.
- [الأمان](/ar/guide/security): ما تحميه الحزمة وما يبقى من مسؤوليتك.
- [إرسال فاتورة بالبريد](/ar/recipes/email-invoice): المستند نفسه مرفقاً برسالة بريد.
- [الفاتورة الضريبية](/ar/templates/invoice): القالب الذي يستخدمه `OrderInvoice`.
