# A price list built in code

Build a price list with product pictures straight from your catalogue with `Doc::make()`: no template folder and no Blade view, and the same code gives the PDF and the Word file.

## The situation {#situation}

مؤسسة النور للتجارة in Riyadh sells point-of-sale equipment to shops and resellers. The sales team sends a price list every week, and the catalogue changes all the time:

- products are grouped by category, in the order the catalogue manager sets;
- each row shows the product picture, the name with a short description, the code, the unit and the price;
- products that are no longer sold must not appear, and an empty category must not leave an empty heading;
- the list ends with a QR code that opens the online store;
- the PDF goes to customers on WhatsApp and email, and the Word copy lets a salesperson adjust prices for a special offer.

The number of categories and products changes every week, so a template with fixed fields does not fit. The builder adds one heading and one table per category, in a loop.

## The solution {#solution}

### 1. The models

The catalogue already has two tables: `categories` (`name`, `position`) and `products` (`category_id`, `name`, `description`, `sku`, `unit`, `price`, `image_path` on the public disk, `is_active`).

```php
// app/Models/Category.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

```php
// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
```

### 2. The document

```php
// app/Documents/PriceList.php
namespace App\Documents;

use App\Models\Category;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Support\Facades\Storage;

class PriceList
{
    public function document(): PendingDocument
    {
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('position')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());

        $doc = Doc::make()
            ->table([[
                ['image' => public_path('images/logo.png'), 'width' => 38],
                ['lines' => [
                    ['text' => 'قائمة الأسعار', 'bold' => true, 'size' => 22, 'color' => '#0F766E'],
                    ['text' => 'سارية من '.now()->format('Y/m/d').' وحتى إشعار آخر', 'color' => '#6B7280'],
                    ['text' => 'الأسعار بالريال السعودي وتشمل ضريبة القيمة المضافة', 'color' => '#6B7280', 'size' => 9],
                ], 'align' => 'end'],
            ]], ['borders' => false, 'columns' => [35, 65]])
            ->line('#0F766E');

        foreach ($categories as $category) {
            $rows = [['صورة', 'الصنف', 'الكود', 'الوحدة', 'السعر']];

            foreach ($category->products as $product) {
                $rows[] = [
                    $product->image_path
                        ? ['image' => Storage::disk('public')->path($product->image_path), 'width' => 16]
                        : '',
                    ['lines' => array_values(array_filter([
                        ['text' => $product->name, 'bold' => true],
                        $product->description ? ['text' => $product->description, 'color' => '#6B7280', 'size' => 8.5] : null,
                    ]))],
                    ['text' => $product->sku, 'ltr' => true],
                    $product->unit,
                    ['text' => number_format((float) $product->price, 2), 'bold' => true],
                ];
            }

            $doc->heading($category->name, 2, ['color' => '#0F766E'])
                ->table($rows, [
                    'header' => true,
                    'striped' => '#F9FAFB',
                    'font_size' => 10,
                    'columns' => [13, 47, 15, 10, ['width' => 15, 'align' => 'end']],
                ]);
        }

        return $doc
            ->spacer(4)
            ->qr(route('store.index'), 28, 'center')
            ->paragraph('امسح الرمز لتصفح المتجر الإلكتروني والطلب مباشرة', ['align' => 'center', 'color' => '#6B7280'])
            ->title('قائمة الأسعار')
            ->locale('ar')
            ->margins(12, 12, 16, 12)
            ->footer('<div style="text-align: center;">مؤسسة النور للتجارة | هاتف 920012345 | صفحة {page} من {pages}</div>');
    }
}
```

How it is put together:

- **The query** loads only active products, sorted by name, in one extra query, and `filter()` drops the categories left with no products, so no heading stands alone.
- **The header** is a borderless table of one row: the logo in the first cell (35% wide) and three lines of text aligned to the end in the second. In an Arabic document the first cell is on the right.
- **A table per category.** `header` makes the first row a header that repeats on every page, in white on the theme's `primary` colour (teal `#0F766E` unless you change it in the config), and `striped` shades every other row with the colour you give. `columns` gives each column a width in percent; the price column is aligned to the end, which is the left side in Arabic.
- **Cells** can be plain text, an image (`width` in mm, 30 by default), `lines` for several lines with their own styles, or text with `ltr`, which keeps codes such as `POS-T15` in order inside right-to-left text. `array_filter()` leaves out the description line when a product has none.
- **Images** are read from the public disk. Local files are allowed under `public/`, `storage/app` and `resources/` by default.
- **`heading()`** stays on the same page as the table that follows it.
- **`qr()`** draws a QR code of the given text, 28 mm wide and centred.
- **`margins(12, 12, 16, 12)`** are top, right, bottom and left in mm; the bottom margin leaves room for the footer. `{page}` and `{pages}` in the footer become the page numbers; in the Word file the footer becomes plain text with real page-number fields.
- **`title()`** sets the document title in the file's properties, which PDF readers show in the window title.

<div class="preview">
  <figure><img src="/images/recipes-b/price-list.png" alt="Arabic price list: logo and title, then three categories, each with a green header row and products with a picture, name, description, code, unit and price, and a QR code at the end"><figcaption>The price list as a PDF (one A4 page)</figcaption></figure>
</div>

### 3. Routes and controller

```php
// routes/web.php
use App\Http\Controllers\PriceListController;

Route::get('/price-list.pdf', [PriceListController::class, 'pdf'])->name('price-list.pdf');
Route::get('/price-list.docx', [PriceListController::class, 'word'])->name('price-list.word');
```

```php
// app/Http/Controllers/PriceListController.php
namespace App\Http\Controllers;

use App\Documents\PriceList;

class PriceListController extends Controller
{
    public function pdf(PriceList $priceList)
    {
        return $priceList->document()->pdf()->download('قائمة-الأسعار.pdf');
    }

    public function word(PriceList $priceList)
    {
        return $priceList->document()->word()->download('قائمة-الأسعار.docx');
    }
}
```

Laravel builds `PriceList` for each request, and the document is made fresh from the catalogue every time. The Word file has the same tables, pictures, colours and QR code, laid out right to left, so a salesperson can open it, change a price and send it on.

::: tip Pictures and file size
Every picture is embedded in the file at its full size. Store a small thumbnail (a few hundred pixels wide) for each product and use it here rather than the original photo, or a list of 300 products becomes a very large file.
:::

## Variations {#variations}

### Catalogue cards, three to a row

For a catalogue rather than a list, put one product in each cell and three cells in each row:

```php
$products = Product::where('is_active', true)->orderBy('name')->get();

$cards = $products->map(fn (Product $product) => ['lines' => array_values(array_filter([
    $product->image_path ? ['image' => Storage::disk('public')->path($product->image_path), 'width' => 30, 'align' => 'center'] : null,
    ['text' => $product->name, 'bold' => true, 'align' => 'center'],
    ['text' => number_format((float) $product->price, 2).' ر.س', 'color' => '#0F766E', 'bold' => true, 'align' => 'center'],
])), 'border' => '#E5E7EB']);

$rows = $cards->chunk(3)->map(fn ($row) => array_pad($row->values()->all(), 3, ''))->all();

$doc = Doc::make()
    ->heading('كتالوج المنتجات')
    ->table($rows, ['borders' => false, 'columns' => [33.3, 33.3, 33.4]])
    ->locale('ar');
```

`border` draws a frame around each card, and `array_pad()` fills the last row with empty cells so it keeps three columns. Pictures and lines inside `lines` take their own `align`.

### Publish the list every night

Instead of building the file on each click, save it once a night to the public disk and link to it from the website:

```php
// routes/console.php
use App\Documents\PriceList;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(PriceList::class)->document()->pdf()->save('price-list.pdf', 'public'))
    ->dailyAt('02:00');
```

The file is then at `Storage::disk('public')->url('price-list.pdf')` (after `php artisan storage:link`).

## Related pages {#related}

- [Building in code](/guide/builder): every block, cell type and table option of `Doc::make()`.
- [Images](/guide/images): allowed image folders, URLs and formats.
- [Page settings](/guide/page-settings): margins, header, footer and the document title.
- [Word files](/guide/word): what the Word file keeps from the builder.
- [Output and delivery](/guide/output): downloading and saving to a disk.
- [Sales report from a query](/recipes/sales-report): another document built in code, with grouped tables and totals.
