# Preview page

The preview page shows every template with its sample data, so you can try languages, digits and engines in the browser and download the files, without writing any code.

## What the page shows {#what}

Open `/doc-preview` in your app while it runs in the `local` environment:

![The preview page with the Arabic tax invoice](/images/guide-b/preview-page.png)

- **The list on the left** has every template your app can use: the bundled ones and those in `resources/doc-templates`. Each shows its title, name, description, whether it makes PDF and Word files, and its source: `package` for a bundled template, `project` for one of yours.
- **Language** switches between the template's languages (`ar` and `en` for the bundled templates).
- **Digits** switches between Latin (123) and Arabic (١٢٣) digits.
- **Engine** renders with the default engine, or with `mpdf`, `chromium` or `gotenberg`. An engine that is not set up falls back as usual (see [PDF engines](/guide/engines#fallback)).
- **View** shows the PDF, or the HTML the engine gets, which is handy when you work on a layout.
- **Open PDF** opens the PDF in a new tab, and **Download Word** downloads the Word file, for templates that have them.

The page renders each template's `sample` data from its `template.php`, so what you see is exactly what the template makes. When you edit a template of your own, reload the page to see the change.

## Direct links {#links}

Each preview is a plain URL, which you can open or share with your team:

```text
/doc-preview?template=invoice
/doc-preview/invoice?locale=ar&numerals=arabic
/doc-preview/invoice?locale=en&engine=chromium
/doc-preview/receipt?format=html
/doc-preview/payslip?locale=ar&format=docx
```

| Parameter | Values | Default |
| --- | --- | --- |
| `locale` | One of the template's languages | The template's first language |
| `numerals` | `latin`, `arabic` | `latin` |
| `engine` | `mpdf`, `chromium`, `gotenberg` | The app's default engine |
| `format` | `pdf`, `html`, `docx` (or `word`) | `pdf` |

Values outside these lists fall back to the default. In Blade, link to the page with its route names: `route('easy-pdf-word.preview.index')` and `route('easy-pdf-word.preview.show', 'invoice')`.

## Turn it on outside `local` {#outside-local}

The page is on in the `local` environment and off everywhere else. To turn it on for a staging server, or for admins in production:

```dotenv
DOC_PREVIEW=true
```

Outside `local`, the page also needs the `viewDocPreview` gate, so only the people you choose can open it. Define it in a service provider:

```php
// app/Providers/AppServiceProvider.php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDocPreview', fn (User $user) => in_array($user->email, [
        'khaled@biztech.example',
        'sara@biztech.example',
    ]));
}
```

Without the gate, or for anyone it refuses (including guests), the page answers 403. `DOC_PREVIEW=false` turns the page off in `local` too, and so does an empty `DOC_PREVIEW=`; remove the line to get the default back.

The page only renders the templates' sample data, never your app's data. Rendering still costs CPU, so keep it behind the gate.

## Path and middleware {#config}

The page's address and middleware are set in `config/easy-pdf-word.php`:

```php
'preview' => [
    'enabled' => env('DOC_PREVIEW'),
    'path' => 'admin/doc-preview',
    'middleware' => ['web', 'auth'],
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `env('DOC_PREVIEW')` | `null`: on in `local` only. `true` or `false`: on or off everywhere. |
| `path` | `'doc-preview'` | The URL of the page. |
| `middleware` | `['web']` | Middleware for the page's routes. Add `auth` to send guests to your login page instead of a 403. |

The gate check is always added after your middleware. The routes are registered when the app boots, so change these settings in the config file or `.env`, not at runtime.

The page sends a strict Content Security Policy, and the HTML view runs in a sandbox with no scripts.

## Developing the package {#package-development}

When you work on the package itself, or on templates in a clone of it, serve the page without an app:

```bash
git clone https://github.com/BiztechEG/laravel-easy-pdf-word.git
cd laravel-easy-pdf-word
composer install
composer preview
```

`composer preview` serves the page at `http://127.0.0.1:8000/doc-preview`, with the `local` environment and the page turned on.
