# Branding per customer

In a SaaS app every customer company (tenant) wants its documents to carry its own logo, colour, company details, language and digits. This recipe keeps those settings on the tenant and applies them to any template with one method, and shows how one tenant can get its own copy of a template.

## The situation {#situation}

An invoicing platform hosts many companies. Each company issues invoices to its own customers from the same Laravel app, and each invoice must look like it came from that company. Two of them:

| | BizTech (Cairo) | Gulf Star Trading Co. (Riyadh) |
|---|---|---|
| Logo and colour | blue `#1D4ED8` | amber `#B45309` |
| Language | Arabic, right to left | English, left to right |
| Digits | Arabic digits (١٢٣) | Latin digits (123) |
| Currency and VAT | EGP, 14% | SAR, 15% |
| Extras | | ZATCA QR code, and its bank details on every invoice |

The code that makes an invoice must be the same for all of them, and a new company must not need a deploy.

## The solution {#solution}

### 1. Keep the brand on the tenant

```php
// database/migrations/2026_10_01_000000_create_tenants_table.php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();             // biztech, gulf-star
    $table->string('legal_name');
    $table->string('logo_path')->nullable();      // a PNG or JPEG on the public disk
    $table->string('brand_color', 7)->nullable(); // #1D4ED8
    $table->string('address')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->string('tax_number')->nullable();
    $table->char('country', 2);                   // EG, SA ...
    $table->string('locale', 5)->default('ar');
    $table->string('numerals', 6)->default('latin');
    $table->char('currency', 3);
    $table->decimal('vat_rate', 5, 2);
    $table->timestamps();
});
```

### 2. One method that brands any document

```php
// app/Models/Tenant.php
namespace App\Models;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    /** A document with this tenant's brand, language and digits. */
    public function document(string $template, array $data = []): PendingDocument
    {
        return Doc::template($this->templateName($template), $data)
            ->theme($this->documentTheme())
            ->locale($this->locale)
            ->numerals($this->numerals);
    }

    public function documentTheme(): array
    {
        return [
            'primary' => $this->brand_color,
            'logo' => $this->logo_path ? Storage::disk('public')->path($this->logo_path) : null,
            'company' => [
                'name' => $this->legal_name,
                'address' => $this->address,
                'phone' => $this->phone,
                'email' => $this->email,
                'tax_number' => $this->tax_number,
            ],
        ];
    }

    /** "invoice.gulf-star" when this tenant has its own copy of the template. */
    public function templateName(string $template): string
    {
        $own = "{$template}.{$this->slug}";

        return Doc::templates()->exists($own) ? $own : $template;
    }
}
```

How it works:

- **`->theme()`** merges the array over the `theme` in `config/easy-pdf-word.php`, key by key. The ready-made templates read the colour, the logo and the company details from it. The invoice prints `company` as the seller when you pass no `seller`, and the contract prints the logo at the top.
- **Colours are checked.** A value that is not a colour (or `null`, when a tenant has not picked one) falls back to the default teal `#0F766E`, so a bad value cannot break the page.
- **The logo** is a file path. Files under `public/`, `storage/app` and `resources/` are allowed by default (`images.paths` in the config), and the public disk lives in `storage/app/public`. Use PNG or JPEG: an SVG logo shows in PDFs but is left out of Word files.
- **`->locale()`** switches the direction and the template's own words (Arabic "فاتورة ضريبية", English "Tax Invoice"). **`->numerals()`** takes `arabic` or `latin`.
- **`templateName()`** picks the tenant's own copy of a template when one exists (step 4), and the shared one otherwise.

Every setting stays a normal method call, so a single document can still change it: `$tenant->document('invoice', $data)->locale('en')`.

### 3. The invoice data and the controller

```php
// app/Models/Invoice.php (the parts this recipe uses)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected function casts(): array
    {
        return ['issued_at' => 'date', 'due_at' => 'date'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function toDocumentData(): array
    {
        return [
            'invoice' => [
                'number' => $this->number,
                'date' => $this->issued_at,
                'due_date' => $this->due_at,
                'currency' => $this->tenant->currency,
                'tax_rate' => $this->tenant->vat_rate,
            ],
            'buyer' => [
                'name' => $this->customer->name,
                'address' => $this->customer->address,
                'tax_number' => $this->customer->tax_number,
            ],
            'items' => $this->items->map->only(['description', 'quantity', 'unit_price']),
            'qr' => $this->tenant->country === 'SA' ? 'zatca' : null,
        ];
    }
}
```

There is no `seller` key: the seller comes from the tenant's theme. `qr => 'zatca'` makes the invoice template build the ZATCA QR code from the seller, the VAT number and the totals, only for tenants in Saudi Arabia.

```php
// routes/web.php
use App\Http\Controllers\InvoicePdfController;

Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])
    ->middleware('auth')
    ->name('invoices.pdf');
```

```php
// app/Http/Controllers/InvoicePdfController.php
namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController extends Controller
{
    public function show(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);

        return $invoice->tenant
            ->document('invoice', $invoice->toDocumentData())
            ->pdf()
            ->download("{$invoice->number}.pdf");
    }
}
```

```php
// app/Policies/InvoicePolicy.php
namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->tenant_id === $invoice->tenant_id;
    }
}
```

Invoice ids are easy to guess, so the policy makes sure a user only downloads invoices of their own company; anyone else gets a 403.

### 4. One tenant, its own template {#own-template}

Gulf Star wants its bank details under the totals. Copy the invoice template under a name that ends with the tenant's slug:

```bash
php artisan doc:template invoice --as=invoice.gulf-star
```

This copies the template to `resources/doc-templates/invoice.gulf-star/`. Add the box to `pdf.html.php`, just before the notes:

```php
<div style="margin-top: 5mm; border: 1px solid <?= $doc->e($border) ?>; padding: 3mm 4mm;">
    <strong style="color: <?= $doc->e($primary) ?>;">Bank details</strong><br>
    Al Rajhi Bank, Gulf Star Trading Co.<br>
    IBAN: <?= $doc->ltr('SA03 8000 0000 6080 1016 7519') ?>
</div>

<?php if (! empty($invoice['notes'])) { ?>
```

The Word file is built by `word.php` in the same folder, so add the box there too, before `if (! empty($invoice['notes'])) {`:

```php
    $word->spacer(3);
    $word->table([[[
        'lines' => [
            ['text' => 'Bank details', 'bold' => true, 'color' => $primary],
            ['text' => 'Al Rajhi Bank, Gulf Star Trading Co.'],
            ['text' => 'IBAN: SA03 8000 0000 6080 1016 7519', 'ltr' => true],
        ],
        'border' => $border,
    ]]], ['borders' => false, 'font_size' => 10]);
```

Nothing else changes. `templateName('invoice')` now returns `invoice.gulf-star` for Gulf Star and `invoice` for everybody else, and the copy still takes its colours and logo from the theme.

<div class="preview">
  <figure><img src="/images/recipes-b/tenant-biztech.png" alt="Arabic tax invoice in blue with the BizTech logo, Arabic digits and the amount in words"><figcaption>BizTech: Arabic, Arabic digits, blue</figcaption></figure>
  <figure><img src="/images/recipes-b/tenant-gulf-star.png" alt="English tax invoice in amber with the Gulf Star logo, a ZATCA QR code and a bank details box"><figcaption>Gulf Star: English, ZATCA QR, its own template</figcaption></figure>
</div>

Both come from the same controller and the same `toDocumentData()`.

::: warning Pick templates by name, not by changing paths
It is tempting to point `easy-pdf-word.templates.paths` at a per-tenant folder during the request. Do not: the list of templates is read once per process, so a later `config()` change is ignored by a long-running worker (Octane, queue workers), and a queued document is rendered by a worker that knows only the template's name. A name such as `invoice.gulf-star` works the same everywhere.
:::

To keep tenant copies apart from your own templates, add a second folder to the config and move the copies there:

```php
// config/easy-pdf-word.php
'templates' => [
    'paths' => [
        resource_path('doc-templates'),
        resource_path('doc-templates/tenants'),
    ],
],
```

Folders are searched in order, and the first template with the name wins. `doc:template` always copies into the first folder, so move the new copy into `tenants/` afterwards.

## Queued documents keep the brand {#queue}

Archiving every invoice to S3 runs best on a queue:

```php
$tenant->document('invoice', $invoice->toDocumentData())
    ->queue("tenants/{$tenant->id}/invoices/{$invoice->number}.pdf", disk: 's3');
```

The job carries the template name (`invoice.gulf-star`), the theme, the language and the digits, so the worker renders the same invoice the user would download. The logo travels as its path: the worker must be able to read that file, which is true when it runs on the same server. When workers run elsewhere, keep logos on S3 and pass them as data (next section).

Because the brand is part of each document, there is nothing to switch back after a document is made, and one worker can render documents of many tenants one after the other.

## Variations {#variations}

### Logos on S3

The theme logo must be a local file, a data URI or an allowed URL. With logos on S3, read the file and pass it as a data URI:

```php
'logo' => $this->logo_path
    ? 'data:'.Storage::disk('s3')->mimeType($this->logo_path).';base64,'.base64_encode(Storage::disk('s3')->get($this->logo_path))
    : null,
```

Keep logos small (a few dozen KB): the data URI is part of every queued job.

### Let each tenant set its brand

The values come from a settings form. Validate them so they work in both PDF and Word files: Word leaves colour names such as `navy` out, so accept hex colours.

```php
// app/Http/Controllers/BrandingController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function update(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg', 'max:1024'],
            'locale' => ['required', 'in:ar,en'],
            'numerals' => ['required', 'in:arabic,latin'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store("logos/{$tenant->id}", 'public');
        }

        unset($validated['logo']);
        $tenant->update($validated);

        return back()->with('status', 'Branding saved.');
    }
}
```

### The customer's language, not the tenant's

A Saudi tenant may bill some customers in Arabic and others in English. The last call wins, so override the language after `document()`:

```php
$invoice->tenant
    ->document('invoice', $invoice->toDocumentData())
    ->locale($invoice->customer->preferred_locale ?? $invoice->tenant->locale)
    ->pdf();
```

### A font per tenant

Add `->font('tajawal')` (or `cairo`, `naskh`, or a font you registered) after `document()`, or keep a `font` column on the tenant and pass it there. See [Fonts](/guide/fonts).

### The brand in your own templates

Your own templates get the same theme. In a PDF page, `$doc->theme('primary')` and `$doc->theme('company.name')`; in a Word-designed `word.docx`, the placeholders `${theme.company.name}` and `${theme.logo:150:60}`. So `$tenant->document('price-offer', $data)` brands a template you designed yourself, too.

## Related pages {#related}

- [Tax invoice](/templates/invoice): the invoice fields, the seller and the ZATCA QR code.
- [Your own templates](/guide/custom-templates): copying templates and how template folders are searched.
- [Configuration](/guide/configuration): the `theme`, `images` and `templates` settings.
- [Images](/guide/images): which logo paths and formats are allowed.
- [Arabic support](/guide/arabic): direction, Arabic digits and amounts in words.
- [Fonts](/guide/fonts): the bundled fonts and adding your own.
- [Output and delivery](/guide/output): queued rendering and saving to disks.
- [A template designed in Word](/recipes/word-designed-template): theme placeholders in a `.docx` template.
