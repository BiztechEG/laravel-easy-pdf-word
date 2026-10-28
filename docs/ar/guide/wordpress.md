# WordPress

تبني هذه الصفحة إضافة (plugin) صغيرة لـ WordPress تعطي طلبات WooCommerce فاتورة ضريبية بصيغة PDF: زر تنزيل للعميل في صفحة *حسابي*، والملف نفسه لمديري المتجر، والفاتورة مرفقة برسالة "اكتمل الطلب". تستخدم المكتبة بدون Laravel، فاقرأ أولًا صفحة [PHP بدون إطار عمل](/ar/guide/plain-php) إن لم تكن قد قرأتها. والطريقة نفسها تصلح لأي إضافة أو قالب يُنشئ مستندات.

جُرّب الكود في هذه الصفحة على WordPress 7.1 مع WooCommerce 11.2.

## التثبيت داخل إضافتك {#install}

أنشئ مجلد الإضافة وثبّت المكتبة فيه بـ Composer:

```bash
cd wp-content/plugins
mkdir my-invoices && cd my-invoices
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

تأتي الإضافة ومعها مجلد `vendor`. ارفع المجلد عبر FTP أو SSH، أو شغّل `composer install --no-dev` على الخادم: فحجمه مع خطوط mPDF أكثر من 100 MB، وهذا أكبر من حد الرفع في شاشة الإضافات لدى كثير من الاستضافات. ويحتاج الخادم إلى PHP 8.2 أو أحدث مع `mbstring` و`gd` (و`intl` للتاريخ الهجري و`zip` لملفات Word).

::: warning إضافات أخرى فيها mPDF أو PhpWord
إذا كانت إضافة أخرى مفعّلة تحمل نسختها الخاصة من mPDF أو PhpWord، فأول إضافة يحمّلها WordPress هي التي تحدد النسخة التي تستخدمها كل الإضافات. اجعلها على نسخ متوافقة (mPDF 8.2.4 أو أحدث، وPhpWord 1.4 أو أحدث)، أو ثبّت حزم كل الإضافات من ملف `composer.json` واحد للموقع كله.
:::

## الـ factory {#factory}

ابدأ ملف الإضافة بتحميل Composer وإنشاء الـ factory مرة واحدة:

```php
<?php
/**
 * Plugin Name: My Invoices
 * Description: PDF invoices for WooCommerce orders, in Arabic and English.
 * Requires PHP: 8.2
 */

use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;

defined('ABSPATH') || exit;

require __DIR__.'/vendor/autoload.php';

function my_invoices_docs(): DocumentFactory
{
    static $docs;

    return $docs ??= EasyPdfWord::create([
        'locale' => str_starts_with(get_locale(), 'ar') ? 'ar' : 'en',
        'theme' => [
            'company' => [
                'name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
                'tax_number' => '300000000000003',
            ],
        ],
        'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => get_temp_dir().'easy-pdf-word']]],
        'templates' => ['paths' => [__DIR__.'/doc-templates']],
    ], [
        'warn' => fn (string $message) => error_log($message),
    ]);
}
```

- تتبع المستندات لغة الموقع: الموقع العربي يحصل على فواتير عربية من اليمين إلى اليسار.
- تعيد `get_bloginfo('name')` اسم الموقع مهرَّبًا لـ HTML (`&amp;` بدل `&`). وتعيده `wp_specialchars_decode()` نصًا عاديًا، لأن القالب يهرّبه مرة أخرى.
- `get_temp_dir()` هو المجلد الذي يكتب فيه WordPress نفسه ملفاته المؤقتة، فيعمل mPDF على الاستضافات التي تغلق مجلد النظام المؤقت.
- يحتوي `doc-templates` على قوالبك الخاصة أو نسخك من القوالب المرفقة؛ راجع [قوالبك الخاصة](/ar/guide/custom-templates).
- تذهب التحذيرات عن محرك فشل أو صورة تُركت إلى سجل أخطاء PHP، ويكتبه WordPress في `wp-content/debug.log` عند تفعيل `WP_DEBUG_LOG`.

## بيانات الطلب للفاتورة {#data}

تحتاج [الفاتورة الضريبية](/ar/templates/invoice) المرفقة إلى رقم الفاتورة وتاريخها والعميل والبنود. وهذه الدالة تقرؤها من طلب WooCommerce:

```php
function my_invoices_data(WC_Order $order): array
{
    $items = [];

    foreach ($order->get_items() as $item) {
        $items[] = [
            'description' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'unit_price' => $order->get_item_subtotal($item, false, false),
            'discount' => (float) $item->get_subtotal() - (float) $item->get_total(),
        ];
    }

    // الشحن والرسوم، كلٌّ منها في بند
    foreach ($order->get_items(['shipping', 'fee']) as $item) {
        $items[] = [
            'description' => $item->get_name(),
            'quantity' => 1,
            'unit_price' => (float) $item->get_total(),
        ];
    }

    return [
        'invoice' => [
            'number' => $order->get_order_number(),
            'date' => $order->get_date_created()->date('Y-m-d'),
            'currency' => $order->get_currency(),
            'tax_rate' => 15,
        ],
        'buyer' => [
            'name' => $order->get_formatted_billing_full_name() ?: $order->get_billing_email(),
            'address' => implode('، ', array_filter([$order->get_billing_address_1(), $order->get_billing_city()])),
        ],
        'items' => $items,
        'qr' => 'zatca',
    ];
}
```

الأسعار قبل الضريبة، ويظهر الكوبون خصمًا على كل بند. ويُضاف الشحن والرسوم (`get_items(['shipping', 'fee'])`) بنودًا مستقلة بإجماليها قبل الضريبة، فتخضع لنسبة ضريبة الفاتورة مثل المنتجات ويطابق إجمالي الفاتورة إجمالي الطلب. ويرفض القالب الرسوم ذات المبلغ السالب، لأن البند لا يكون أقل من صفر؛ فقدّم الخصومات في صورة كوبونات بدلًا منها. ويحسب القالب الإجمالي الفرعي والضريبة والإجمالي، ويكتب المبلغ بالحروف. ويأتي المورد من `company` في الـ theme.

للفاتورة نسبة ضريبة واحدة: 15% مع رمز QR الخاص بهيئة الزكاة في السعودية هنا. ولمصر استخدم `14` واحذف `qr`. وإن كان في متجرك أكثر من نسبة ضريبة فأنشئ قالبك الخاص.

## رابط التنزيل {#download}

يرسل WordPress الطلبات إلى `wp-admin/admin-post.php?action=...` إلى الـ hook ‏`admin_post_...` المطابق. يتحقق الـ handler من الـ nonce في الرابط ومن أن الزائر مسموح له برؤية الطلب، ثم يرسل ملف PDF:

```php
// /wp-admin/admin-post.php?action=my_invoice_pdf&order=123&_wpnonce=...
add_action('admin_post_my_invoice_pdf', function () {
    $order = wc_get_order(absint($_GET['order'] ?? 0));

    if (! $order || ! wp_verify_nonce($_GET['_wpnonce'] ?? '', 'my_invoice_pdf_'.$order->get_id())) {
        wp_die(__('This link has expired.'), 403);
    }

    if ($order->get_customer_id() !== get_current_user_id() && ! current_user_can('edit_shop_orders')) {
        wp_die(__('Sorry, you are not allowed to access this page.'), 403);
    }

    my_invoices_docs()
        ->template('invoice', my_invoices_data($order))
        ->pdf('invoice-'.$order->get_order_number().'.pdf')
        ->send();

    exit;
});

function my_invoice_url(WC_Order $order): string
{
    return wp_nonce_url(
        admin_url('admin-post.php?action=my_invoice_pdf&order='.$order->get_id()),
        'my_invoice_pdf_'.$order->get_id(),
    );
}
```

- الـ hook المستخدم هو `admin_post_` فقط وليس `admin_post_nopriv_`، فلا يحصل الزوار غير المسجلين على شيء.
- يستطيع صاحب الطلب ومديرو المتجر تنزيله، أما عميل آخر فيحصل على 403 حتى مع رابط صحيح.
- ترسل `->send()` الملف للتنزيل، ويمنع `exit` WordPress من طباعة أي شيء بعده. والمخرجات التي ما زالت تنتظر في مخازن المخرجات (output buffers) في PHP، كتنبيه من إضافة أخرى، تُحذف قبل إرسال الملف. واستخدم `->send(inline: true)` لعرضه في المتصفح بدلًا من ذلك.

واعرض الرابط للعملاء زرًّا بجوار كل طلب في *حسابي > الطلبات*:

```php
add_filter('woocommerce_my_account_my_orders_actions', function (array $actions, WC_Order $order) {
    $actions['invoice'] = ['url' => my_invoice_url($order), 'name' => __('PDF invoice', 'my-invoices')];

    return $actions;
}, 10, 2);
```

## إرفاقها برسالة بريد {#email}

تأخذ رسائل WooCommerce مسارات الملفات مرفقات. احفظ ملف PDF في المجلد المؤقت، وأرفقه، واحذفه عند انتهاء الطلب:

```php
add_filter('woocommerce_email_attachments', function (array $attachments, string $email_id, $order) {
    if ($email_id !== 'customer_completed_order' || ! $order instanceof WC_Order) {
        return $attachments;
    }

    // مجلد خاص بكل رسالة، فلا تشترك رسالتان في الوقت نفسه في ملف واحد
    $dir = get_temp_dir().'my-invoices-'.wp_generate_password(12, false);
    $path = $dir.'/invoice-'.sanitize_file_name($order->get_order_number()).'.pdf';
    $attachments[] = my_invoices_docs()->template('invoice', my_invoices_data($order))->pdf()->save($path);

    add_action('shutdown', function () use ($dir, $path) {
        @unlink($path);
        @rmdir($dir);
    });

    return $attachments;
}, 10, 3);
```

قد يحتوي رقم الطلب على أحرف مثل `/` (فقد ترقّم إضافة الطلبات بالشكل `2026/0042`)، لذلك تجعله `sanitize_file_name()` صالحًا لاسم ملف. ويبقي اسم المجلد العشوائي اسم المرفق نظيفًا، لأن الرسالة تعرض اسم الملف نفسه. والطريقة نفسها تعمل مع `wp_mail()` خارج WooCommerce: `wp_mail($to, $subject, $message, '', [$path])`.

## الاحتفاظ بالملفات {#storage}

للاحتفاظ بكل فاتورة، احفظها مرة واحدة ثم قدّم الملف المحفوظ. لا تحفظ الفواتير في مجلد `uploads` العام إلا إذا كان مسموحًا لأي شخص يعرف العنوان أن يراها؛ والمجلد الواقع خارج جذر الموقع، أو الذي تمنع استضافتك الوصول إليه من الويب، يبقيها خاصة:

```php
$path = WP_CONTENT_DIR.'/invoices/'.$order->get_id().'.pdf';   // امنع الوصول إلى /wp-content/invoices/ على خادمك

if (! file_exists($path)) {
    my_invoices_docs()->template('invoice', my_invoices_data($order))->pdf()->save($path);
}
```

## مشكلات شائعة {#problems}

| المشكلة | الحل |
| --- | --- |
| التنزيل صفحة خطأ HTML أو ملف PDF تالف | طُبع شيء قبل `->send()`: تنبيه من PHP، أو مسافة قبل `<?php`، أو إضافة أخرى. تحذف `send()` المخرجات التي ما زالت في مخازن المخرجات في PHP، أما المخرجات التي وصلت إلى المتصفح فلا يمكن استرجاعها: حينها ترمي `send()` الخطأ "Cannot send the file: output already started at ..." مع اسم الملف ورقم السطر. أزل تلك المخرجات من مصدرها. والمخرجات المطبوعة بعد الملف تفسده أيضًا، لذلك استدعِ `exit` بعد `send()` مباشرة. |
| `Cannot create the mPDF temp folder` | اضبط `pdf.drivers.mpdf.temp_dir` على مجلد قابل للكتابة، كما في [الـ factory](#factory). |
| نفاد الذاكرة أو الوقت مع التقارير الطويلة | ارفع `WP_MEMORY_LIMIT` في `wp-config.php`؛ راجع [حل المشكلات](/ar/guide/troubleshooting). |
| الحروف العربية غير متصلة | استخدم الخطوط المرفقة أو [أصلح خطك](/ar/guide/fonts) لـ mPDF. |
