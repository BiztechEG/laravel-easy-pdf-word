# WordPress plugin

The **Easy PDF & Word** plugin gives a WordPress shop its documents without code: a tax invoice for each WooCommerce order (with the ZATCA QR code for Saudi Arabia, or the Egyptian e-invoice layout), a credit note for each refund and a delivery note, as PDF or Word files, in Arabic, English or Turkish. For developers it adds `easy_pdf_word()`, the library's document factory, for any other document.

To build your own plugin on the library instead, see [WordPress](/guide/wordpress).

The plugin is tested on WordPress 7.0 and 7.1 with WooCommerce 11.1 and 11.2, on PHP 8.2 to 8.5.

<div class="preview">
  <figure><img src="/images/guide-b/wordpress-plugin-invoice-ar.png" alt="An Arabic tax invoice with the ZATCA QR code, made by the plugin from a WooCommerce order"><figcaption>A WooCommerce order's invoice, with the ZATCA QR code</figcaption></figure>
</div>

## Install {#install}

1. In WordPress, go to *Plugins > Add New > Upload Plugin* and upload `easy-pdf-word.zip`, or install the plugin from the WordPress plugin directory.
2. Activate it.
3. Open *WooCommerce > Easy PDF & Word* (*Settings > Easy PDF & Word* without WooCommerce), fill in your company and tax number, and open the sample invoice to check them.

The server needs PHP 8.2 or newer with `mbstring` and `gd`, `zip` for Word files and `intl` for Hijri dates.

The zip is about 4.5 MB, much smaller than installing the library with Composer: the documents use the library's own fonts (Cairo, Tajawal and Noto Naskh Arabic), so mPDF's other fonts stay out.

::: tip Other plugins with mPDF or PhpWord
The plugin's copies of the library, mPDF and PhpWord are renamed under its own prefix, `BiztechEG\EasyPdfWordWP\Vendor\`. Another plugin with its own mPDF or PhpWord, of any version, never meets them.
:::

## Settings {#settings}

Shop managers can change the settings (with WooCommerce, the `manage_woocommerce` capability); without WooCommerce, administrators can.

<div class="preview">
  <figure><img src="/images/guide-b/wordpress-plugin-settings.png" alt="The plugin's settings page under WooCommerce"><figcaption>The settings page</figcaption></figure>
</div>

| Section | Settings |
| --- | --- |
| Company | Name, address, phone, email, tax number, logo and main colour. An empty name or address uses the site name and the WooCommerce store address. The logo is a PNG or JPEG from the media library. |
| Documents | Language, digits, font and PDF engine (see below). |
| Invoices | The invoice type, the number prefixes and digits, and the next invoice and credit note numbers. |
| Customers | The documents customers can download from *My account*, and the order statuses in which the invoice is available. |
| Emails | For each document, the WooCommerce emails it goes with. |

- **Language**: the site language by default. WooCommerce sends each customer email in the customer's language, and its documents follow. A shop manager whose own profile is in another language still gets documents in the site language. Choose Arabic, English or Turkish to always use one.
- **Digits**: 0123456789 or ٠١٢٣٤٥٦٧٨٩.
- **Font**: Cairo, Tajawal or Noto Naskh Arabic.
- **PDF engine**: mPDF, built in, works on any host. [Gotenberg](/guide/engines#gotenberg) draws pages as Chrome does; give its address, such as `http://gotenberg:3000`. When Gotenberg cannot be reached, mPDF makes the document.
- **Sample invoice**: the button under the settings opens the bundled sample invoice with the saved settings, in the invoice type you chose.

## The documents {#documents}

| Document | Template | One for |
| --- | --- | --- |
| Invoice | [Tax invoice](/templates/invoice), or the [Egyptian e-invoice](/templates/eg-invoice) | each order |
| Credit note | [Credit note](/templates/credit-note), or the Egyptian e-invoice as a credit note | each refund |
| Delivery note | [Delivery note](/templates/delivery-note), without prices | each order |

The invoice type decides the invoice and the credit notes:

- **Tax invoice**: the seller, the buyer, the lines, the VAT and the total, with the amount in words.
- **Tax invoice with the ZATCA QR code**: the same, with the Saudi phase 1 QR code built from the seller, the tax number, the date and the totals.
- **Egyptian e-invoice layout**: the issuer's tax registration number, the receiver as a business (B) or a person (P), and each line's VAT (T1). It needs the tax number setting.

How an order becomes a document:

- Prices are before tax. A coupon shows as a discount on each line it applies to. Shipping and fees are lines of their own, and a negative fee comes off the lines as a discount. The document's total matches the order's.
- The tax invoice has one VAT rate: the order's rate, 0 when the order has no tax, or the average when its lines have different rates; the totals still match the order. The Egyptian layout shows each line's own rate.
- The buyer is the billing company, or the billing name, with the billing address.
- A credit note lists the refunded items, or one line with the refund's reason when an amount was refunded without items. It names the invoice it corrects.
- The delivery note leaves out refunded quantities and shows the shipping address.

### Numbers {#numbers}

An invoice gets its number the first time it is made, whether it is downloaded or attached to an email: `INV-0001`, `INV-0002` and so on. A credit note gets `CN-0001` the same way. The number and its date are kept with the order, so the document keeps them every time it is opened.

- The prefixes and the number of digits are settings. `{year}` in a prefix becomes the current year: `INV-{year}-` gives `INV-2026-0001`.
- The next number can only go up, so two documents never share a number. To start from 1001, set the next number to 1001.
- Two requests at once, such as a double click or an email sent while the invoice is being downloaded, still give the order one number.

## Where they show up {#buttons}

- **The order page**: a *Documents* box with PDF and Word buttons for the invoice, the delivery note and each credit note.
- **The orders list**: an invoice button on each order, and *Download invoices (ZIP)* among the bulk actions, for up to 50 orders at a time.
- **My account**: an *Invoice* button next to each order, and the order's documents under its details.
- **Emails**: by default the invoice goes with *Completed order* and *Order details*, and the credit note of the latest refund with *Refunded order*. An order that is not paid yet, such as a payment request, gets no invoice. If a document cannot be made, the email still goes out without it.

## Who can download what {#permissions}

- Shop managers (the `edit_shop_orders` capability) can download every document of every order.
- A customer can download only their own orders' documents, only the documents the *Customers* settings list (the invoice and credit notes by default), and the invoice only once the order is in one of the chosen statuses (*Processing* and *Completed* by default).
- Visitors who are not logged in are sent to the *My account* login form. Orders placed as a guest have no account, so their customers get the documents by email.
- Each link carries a nonce for that document of that order and that user, so a link copied to someone else does not work for them.

## For developers {#developers}

### The factory {#factory}

`easy_pdf_word()` returns the library's document factory, set up from the plugin's settings: the language, digits, font, engine, company and logo. It has the same methods as in [Plain PHP](/guide/plain-php):

```php
add_action('admin_post_my_certificate', function () {
    check_admin_referer('my_certificate');

    if (! current_user_can('edit_posts')) {
        wp_die(esc_html__('Sorry, you are not allowed to access this page.'), 403);
    }

    easy_pdf_word()
        ->template('certificate', [
            'type' => 'completion',
            'gender' => 'female',
            'number' => 'C-1024',
            'date' => current_time('Y-m-d'),
            'recipient' => 'سارة العتيبي',
            'course' => 'إدارة المشاريع',
        ])
        ->pdf('certificate.pdf')
        ->send(inline: true);

    exit;
});
```

The bundled library is prefixed, so the factory's class is `BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\DocumentFactory`. Code and templates written with the library's own class names, such as `use BiztechEG\EasyPdfWord\Support\Currency;`, still work: those names load the bundled classes.

### Templates in your theme {#theme-templates}

Put a template folder in an `easy-pdf-word` folder in your theme, or your child theme, and it is used instead of the bundled template with the same name. Start from a copy of a [bundled template](https://github.com/BiztechEG/easy-pdf-word/tree/main/resources/templates), or write your own as in [Your own templates](/guide/custom-templates):

```
wp-content/themes/my-theme/easy-pdf-word/invoice/
├── template.php
├── pdf.html.php
└── lang/ar.json
```

### Hooks {#hooks}

| Hook | Type | Use |
| --- | --- | --- |
| `easy_pdf_word_config` | filter | The library's settings before the factory is made, as in [Configuration](/guide/configuration). |
| `easy_pdf_word_template_paths` | filter | The folders searched for templates before the bundled ones. |
| `easy_pdf_word_factory` | action | Fires once with the factory, for example to add an engine with `->extend()`. |
| `easy_pdf_word_warning` | action | A warning, such as an image left out or a document an email went without. |
| `easy_pdf_word_settings` | filter | The plugin's settings. |
| `easy_pdf_word_woocommerce_data` | filter | An order document's data: `($data, $type, $order, $refund, $template)`. |
| `easy_pdf_word_woocommerce_template` | filter | An order document's template: `($template, $type, $order, $refund)`. |
| `easy_pdf_word_woocommerce_filename` | filter | Its file name: `($name, $type, $order, $refund)`. |
| `easy_pdf_word_document_number` | filter | A new invoice or credit note number: `($formatted, $sequence, $number)`. |
| `easy_pdf_word_customer_can_download` | filter | Whether a customer can download a document: `($allowed, $type, $order, $user_id)`. |

`$type` is `invoice`, `credit-note` or `delivery-note`; `$refund` is the refund of a credit note, or `null`.

Add the buyer's VAT number, kept by a checkout field in the order's meta, and a note to every invoice:

```php
add_filter('easy_pdf_word_woocommerce_data', function (array $data, string $type, WC_Order $order) {
    if ($type === 'invoice') {
        $data['buyer']['tax_number'] = $order->get_meta('_billing_vat_number');
        $data['invoice']['notes'] = 'شكراً لتعاملكم معنا.';
    }

    return $data;
}, 10, 3);
```

Log the warnings somewhere you read:

```php
add_action('easy_pdf_word_warning', function (string $message) {
    error_log('Easy PDF & Word: '.$message);
});
```

With `WP_DEBUG` on, the plugin writes them to the PHP error log itself.

## Temporary files and privacy {#privacy}

- Documents are made in an `easy-pdf-word` folder in WordPress's temp folder. On shared hosting, where the system's temp folder is shared with other accounts, set `WP_TEMP_DIR` in `wp-config.php` to a folder of your own account.
- An email's attachment is saved in a folder of its own that only the site's user can open, and deleted when the request ends.
- Images, such as the logo, can only come from the uploads folder, and remote images are off.
- Deleting the plugin removes its settings and number counters. The invoice and credit note numbers stay in the orders, since customers already have those documents.

## Common problems {#problems}

| Problem | Fix |
| --- | --- |
| "The Egyptian invoice needs your tax registration number" | Fill in *Tax number* in the settings. |
| The invoice is not attached to an email | Check that the email is ticked under *Emails*, and that the order's status is ticked under *Invoice available when the order is*. |
| A customer sees no invoice button | Same as above, and the order must belong to the customer's account: orders placed as a guest have no *My account* page. |
| The logo is missing | Choose a PNG or JPEG from the media library. |
| Gotenberg is chosen but PDFs look like mPDF's | Gotenberg could not be reached at its address, so mPDF made them. Check the address from the web server. |
| The invoice shows a VAT rate such as 13.04% | The order's lines have different VAT rates; the invoice shows their average. The Egyptian layout shows each line's rate, or use your own template. |
| Out of memory or time on large orders | Raise `WP_MEMORY_LIMIT` in `wp-config.php`; see [Troubleshooting](/guide/troubleshooting). |
