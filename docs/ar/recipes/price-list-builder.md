# قائمة أسعار مبنية بالكود

ابنِ قائمة أسعار بصور المنتجات مباشرة من الكتالوج باستخدام `Doc::make()`: دون مجلد قالب ودون ملف Blade، والكود نفسه يعطيك ملف PDF وملف Word.

## الموقف {#situation}

مؤسسة النور للتجارة في الرياض تبيع أجهزة نقاط البيع للمحلات والموزعين. يرسل فريق المبيعات قائمة أسعار كل أسبوع، والكتالوج يتغير باستمرار:

- المنتجات مجمّعة حسب الفئة، بالترتيب الذي يحدده مدير الكتالوج؛
- كل صف فيه صورة المنتج، والاسم مع وصف قصير، والكود، والوحدة، والسعر؛
- المنتجات المتوقفة لا تظهر، والفئة الفارغة لا تترك عنواناً بلا منتجات؛
- تنتهي القائمة برمز QR يفتح المتجر الإلكتروني؛
- يُرسل ملف PDF إلى العملاء عبر واتساب والبريد، ونسخة Word تتيح لمندوب المبيعات تعديل الأسعار لعرض خاص.

عدد الفئات والمنتجات يتغير كل أسبوع، فلا يناسبه قالب بحقول ثابتة. أما بناء المستند بالكود فيضيف عنواناً وجدولاً لكل فئة داخل حلقة تكرار.

## الحل {#solution}

### 1. النماذج

في الكتالوج جدولان بالفعل: `categories` (`name` و`position`) و`products` (`category_id` و`name` و`description` و`sku` و`unit` و`price` و`image_path` على الـ disk المسمى `public` و`is_active`).

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

### 2. المستند

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

كيف بُني:

- **الاستعلام** لا يحمّل إلا المنتجات النشطة مرتبة بالاسم في استعلام إضافي واحد، و`filter()` يحذف الفئات التي لم يبقَ فيها منتجات، فلا يبقى عنوان وحده.
- **الترويسة** جدول بلا حدود من صف واحد: الشعار في الخلية الأولى (بعرض 35%) وثلاثة أسطر محاذاة إلى نهاية السطر في الثانية. وفي المستند العربي تكون الخلية الأولى على اليمين.
- **جدول لكل فئة.** `header` يجعل الصف الأول رأساً للجدول يتكرر في كل صفحة، بخط أبيض على لون `primary` في الهوية (الأخضر المزرق `#0F766E` ما لم تغيّره في الإعدادات)، و`striped` يظلل صفاً ويترك صفاً باللون الذي تحدده. و`columns` يعطي كل عمود عرضه بالنسبة المئوية، وعمود السعر محاذى إلى نهاية السطر، وهو الجانب الأيسر في العربية.
- **الخلايا** قد تكون نصاً عادياً، أو صورة (`width` بالمليمتر، والقيمة الافتراضية 30)، أو `lines` لعدة أسطر لكل منها تنسيقه، أو نصاً مع `ltr` الذي يحفظ ترتيب أكواد مثل `POS-T15` داخل نص من اليمين إلى اليسار. ويحذف `array_filter()` سطر الوصف إذا لم يكن للمنتج وصف.
- **الصور** تُقرأ من الـ disk المسمى `public`. والملفات المحلية مسموح بها افتراضياً داخل `public/` و`storage/app` و`resources/`.
- **`heading()`** يبقى في الصفحة نفسها مع الجدول الذي يليه.
- **`qr()`** يرسم رمز QR للنص المعطى، بعرض 28 مليمتراً وفي المنتصف.
- **`margins(12, 12, 16, 12)`** هي الهوامش العلوي والأيمن والسفلي والأيسر بالمليمتر، والهامش السفلي يترك مكاناً لتذييل الصفحة. ويتحول `{page}` و`{pages}` في التذييل إلى أرقام الصفحات، وفي ملف Word يصبح التذييل نصاً عادياً فيه حقول أرقام صفحات حقيقية.
- **`title()`** يضبط عنوان المستند في خصائص الملف، وتعرضه برامج قراءة PDF في عنوان النافذة.

<div class="preview">
  <figure><img src="/images/recipes-b/price-list.png" alt="قائمة أسعار عربية: الشعار والعنوان، ثم ثلاث فئات لكل منها صف رأس أخضر ومنتجات بالصورة والاسم والوصف والكود والوحدة والسعر، ورمز QR في النهاية"><figcaption>قائمة الأسعار ملف PDF (صفحة A4 واحدة)</figcaption></figure>
</div>

### 3. الـ routes والـ controller

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

ينشئ Laravel كائن `PriceList` لكل طلب، ويُبنى المستند من الكتالوج من جديد في كل مرة. وفي ملف Word الجداول نفسها والصور والألوان ورمز QR، من اليمين إلى اليسار، فيستطيع مندوب المبيعات فتحه وتعديل سعر وإرساله.

::: tip الصور وحجم الملف
تُضمَّن كل صورة في الملف بحجمها الكامل. احفظ صورة مصغرة (بعرض بضع مئات من البكسل) لكل منتج واستخدمها هنا بدلاً من الصورة الأصلية، وإلا صارت قائمة من 300 منتج ملفاً ضخماً.
:::

## تنويعات {#variations}

### بطاقات كتالوج، ثلاث في كل صف

لكتالوج بدلاً من قائمة، ضع منتجاً واحداً في كل خلية وثلاث خلايا في كل صف:

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

يرسم `border` إطاراً حول كل بطاقة، ويملأ `array_pad()` الصف الأخير بخلايا فارغة ليبقى بثلاثة أعمدة. والصور والأسطر داخل `lines` تأخذ كل منها `align` الخاص بها.

### نشر القائمة كل ليلة

بدلاً من بناء الملف مع كل نقرة، احفظه مرة كل ليلة على الـ disk المسمى `public` وضع رابطه في الموقع:

```php
// routes/console.php
use App\Documents\PriceList;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(PriceList::class)->document()->pdf()->save('price-list.pdf', 'public'))
    ->dailyAt('02:00');
```

يصبح الملف متاحاً على `Storage::disk('public')->url('price-list.pdf')` (بعد `php artisan storage:link`).

## صفحات ذات صلة {#related}

- [بناء المستند بالكود](/ar/guide/builder): كل الكتل وأنواع الخلايا وخيارات الجداول في `Doc::make()`.
- [الصور](/ar/guide/images): مجلدات الصور المسموح بها والروابط والصيغ.
- [إعدادات الصفحة](/ar/guide/page-settings): الهوامش ورأس الصفحة وتذييلها وعنوان المستند.
- [ملفات Word](/ar/guide/word): ما يحتفظ به ملف Word من بناء المستند بالكود.
- [الإخراج والتسليم](/ar/guide/output): التنزيل والحفظ على disk.
- [تقرير مبيعات من قاعدة البيانات](/ar/recipes/sales-report): مستند آخر مبني بالكود، بجداول مجمّعة وإجماليات.
