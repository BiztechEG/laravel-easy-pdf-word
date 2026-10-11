# WordPress

This page builds a small WordPress plugin that gives WooCommerce orders a PDF tax invoice: a download button for the customer in *My account*, the same file for shop managers, and the invoice attached to the "order completed" email. It uses the library without Laravel, so read [Plain PHP](/guide/plain-php) first if you have not. The same pattern works for any plugin or theme that makes documents.

The code on this page was run on WordPress 7.1 with WooCommerce 11.2.

## Install in your plugin {#install}

Create the plugin folder and install the library there with Composer:

```bash
cd wp-content/plugins
mkdir my-invoices && cd my-invoices
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

The plugin ships with its `vendor` folder. Upload the folder by FTP or SSH, or run `composer install --no-dev` on the server: with mPDF's own fonts it is over 100 MB, larger than the upload limit of the plugin screen on many hosts. The server needs PHP 8.2 or newer with `mbstring` and `gd` (`intl` for Hijri dates, `zip` for Word files).

::: warning Other plugins with mPDF or PhpWord
If another active plugin bundles its own copy of mPDF or PhpWord, the first plugin WordPress loads decides which copy every plugin gets. Keep them on compatible versions (mPDF 8.2 or newer, PhpWord 1.4 or newer), or install all plugins' packages from one `composer.json` for the whole site.
:::

## The factory {#factory}

Start the plugin file by loading Composer and creating the factory once:

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
                'name' => get_bloginfo('name'),
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

- The documents follow the site language: an Arabic site gets Arabic invoices, right to left.
- `get_temp_dir()` is the folder WordPress itself writes temporary files to, so mPDF works on hosts where the system temp folder is closed.
- `doc-templates` is where your own templates go, or your copies of the bundled ones; see [Your own templates](/guide/custom-templates).
- Engine warnings go to the PHP error log, which WordPress writes to `wp-content/debug.log` when `WP_DEBUG_LOG` is on.

## Order data for the invoice {#data}

The bundled [tax invoice](/templates/invoice) needs the invoice number and date, the buyer and the lines. This function reads them from a WooCommerce order:

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

The prices are before tax, and a coupon shows as a discount on each line. The template works out the subtotal, the VAT and the total, and writes the amount in words. The seller comes from the theme's `company`.

The invoice has one VAT rate: 15% and the ZATCA QR code for Saudi Arabia here. For Egypt, use `14` and remove `qr`. If your shop has several tax rates, make your own template.

## A download link {#download}

WordPress sends requests to `wp-admin/admin-post.php?action=...` to the matching `admin_post_...` hook. The handler checks the link's nonce and that the visitor may see the order, then sends the PDF:

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

- Only `admin_post_` is hooked, not `admin_post_nopriv_`, so visitors who are not logged in get nothing.
- The order's customer and shop managers can download it; another customer gets a 403, even with a valid link.
- `->send()` sends the file as a download and `exit` stops WordPress from printing anything after it. Use `->send(inline: true)` to show it in the browser instead.

Show the link to customers as a button next to each order in *My account > Orders*:

```php
add_filter('woocommerce_my_account_my_orders_actions', function (array $actions, WC_Order $order) {
    $actions['invoice'] = ['url' => my_invoice_url($order), 'name' => __('PDF invoice', 'my-invoices')];

    return $actions;
}, 10, 2);
```

## Attach it to an email {#email}

WooCommerce emails take file paths as attachments. Save the PDF to the temporary folder, attach it, and delete it when the request ends:

```php
add_filter('woocommerce_email_attachments', function (array $attachments, string $email_id, $order) {
    if ($email_id !== 'customer_completed_order' || ! $order instanceof WC_Order) {
        return $attachments;
    }

    $path = get_temp_dir().'invoice-'.$order->get_order_number().'.pdf';
    $attachments[] = my_invoices_docs()->template('invoice', my_invoices_data($order))->pdf()->save($path);

    add_action('shutdown', fn () => @unlink($path));

    return $attachments;
}, 10, 3);
```

The same works with `wp_mail()` outside WooCommerce: `wp_mail($to, $subject, $message, '', [$path])`.

## Keep files {#storage}

To keep each invoice, save it once and serve the saved file. Do not save invoices in the public `uploads` folder unless anyone with the address may see them; a folder outside the web root, or one your host blocks from the web, keeps them private:

```php
$path = WP_CONTENT_DIR.'/invoices/'.$order->get_id().'.pdf';   // block /wp-content/invoices/ on your server

if (! file_exists($path)) {
    my_invoices_docs()->template('invoice', my_invoices_data($order))->pdf()->save($path);
}
```

## Common problems {#problems}

| Problem | Fix |
| --- | --- |
| The download is an HTML error page or a broken PDF | Something printed output before `->send()`: a PHP notice, a space before `<?php`, or another plugin. When the headers were already sent, `send()` throws "output already started at ..." with the file and line. |
| `Cannot create the mPDF temp folder` | Set `pdf.drivers.mpdf.temp_dir` to a writable folder, as in [the factory](#factory). |
| Out of memory or time on long reports | Raise `WP_MEMORY_LIMIT` in `wp-config.php`; see [Troubleshooting](/guide/troubleshooting). |
| Arabic letters are not joined | Use the bundled fonts or [fix your font](/guide/fonts) for mPDF. |
