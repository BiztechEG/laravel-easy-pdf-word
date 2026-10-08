# Sales report from a query

Turn an Eloquent query into a monthly sales report as a PDF or Word file: first with the ready-made `report` template, then with `Doc::make()` when you want your own layout. The last part covers reports with thousands of rows.

## The situation {#situation}

A trading company in Riyadh with three branches issues its invoices from a Laravel app. On the first working day of each month the finance manager asks for last month's sales as a file:

- every invoice of the month with its date, customer, branch, amount before VAT, VAT and total, without cancelled invoices;
- the totals at the bottom, and four figures at the top: total sales, number of invoices, average invoice and the best branch;
- seven columns, so the pages are landscape;
- a PDF from a link in the admin panel, and a Word copy when someone wants to edit it.

The invoices already live in an `invoices` table, read through an `Invoice` model:

```php
// app/Models/Invoice.php (the parts this recipe uses)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

The table also has `number`, `branch` and `status` (`paid`, `cancelled` ...).

## The solution {#solution}

### 1. One class that builds the report

Keep the query and the document together in a small class. The controller, a queued job and a scheduled task can then all make the same report.

```php
// app/Reports/MonthlySalesReport.php
namespace App\Reports;

use App\Models\Invoice;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class MonthlySalesReport
{
    public function __construct(private CarbonImmutable $month) {}

    public function document(): PendingDocument
    {
        $invoices = $this->invoices();

        $topBranch = $invoices->groupBy('branch')
            ->map(fn ($branchInvoices) => $branchInvoices->sum('total'))
            ->sortDesc()
            ->keys()
            ->first();

        return Doc::template('report', [
            'title' => 'تقرير المبيعات الشهري',
            'subtitle' => 'فواتير شهر '.$this->month->locale('ar')->translatedFormat('F Y'),
            'columns' => [
                ['key' => 'number', 'label' => 'رقم الفاتورة'],
                ['key' => 'date', 'label' => 'التاريخ', 'format' => 'date'],
                ['key' => 'customer', 'label' => 'العميل'],
                ['key' => 'branch', 'label' => 'الفرع'],
                ['key' => 'subtotal', 'label' => 'قبل الضريبة', 'format' => 'money'],
                ['key' => 'vat', 'label' => 'الضريبة', 'format' => 'money'],
                ['key' => 'total', 'label' => 'الإجمالي (ر.س)', 'format' => 'money'],
            ],
            'rows' => $invoices->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'date' => $invoice->issued_at,
                'customer' => $invoice->customer->name,
                'branch' => $invoice->branch,
                'subtotal' => $invoice->subtotal,
                'vat' => $invoice->vat,
                'total' => $invoice->total,
            ]),
            'sum' => ['subtotal', 'vat', 'total'],
            'summary' => [
                'إجمالي المبيعات' => number_format($invoices->sum('total'), 2).' ر.س',
                'عدد الفواتير' => $invoices->count(),
                'متوسط الفاتورة' => number_format($invoices->avg('total') ?? 0, 2).' ر.س',
                'أعلى فرع' => $topBranch ?? '-',
            ],
        ])
            ->locale('ar')
            ->landscape();
    }

    public function filename(string $extension = 'pdf'): string
    {
        return 'sales-'.$this->month->format('Y-m').'.'.$extension;
    }

    private function invoices(): Collection
    {
        return Invoice::query()
            ->with('customer:id,name')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$this->month->startOfMonth(), $this->month->endOfMonth()])
            ->orderBy('issued_at')
            ->get();
    }
}
```

Why it is written this way:

- **One query, no N+1.** `with('customer:id,name')` loads the customers in one extra query, however many invoices the month has.
- **Rows as plain arrays.** Mapping each invoice to an array decides exactly what the report shows. The template also accepts the models themselves (see [Variations](#variations)).
- **Formats do the number work.** `money` and `number` print thousands separators with `decimals` places (2 by default) and align the column to the end; `date` prints `Y/m/d`. The `decimal:2` casts give strings such as `"2437.00"`, which the template reads as numbers.
- **`sum` adds the totals row** for the listed keys, and `summary` adds the cards above the table. Card values are printed as you give them, so format them yourself.
- **`->landscape()`** turns the template's A4 page sideways. The table header repeats on every page, and the template's footer prints the title and "صفحة 1 من 2".

The company name at the top right comes from the theme (`theme.company.name` in `config/easy-pdf-word.php`, which defaults to `APP_NAME`), and the generation time is added for you.

### 2. The route and the controller

```php
// routes/web.php
use App\Http\Controllers\SalesReportController;

Route::get('/reports/sales', SalesReportController::class)
    ->middleware('auth')
    ->name('reports.sales');
```

```php
// app/Http/Controllers/SalesReportController.php
namespace App\Http\Controllers;

use App\Reports\MonthlySalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $report = new MonthlySalesReport(CarbonImmutable::parse($request->input('month').'-01'));

        return $request->query('format') === 'docx'
            ? $report->document()->word()->download($report->filename('docx'))
            : $report->document()->pdf()->download($report->filename());
    }
}
```

`/reports/sales?month=2026-09` downloads `sales-2026-09.pdf`, and `&format=docx` the Word copy, made by the template's Word layout from the same data. The month is validated before the query runs, and the date is built from `"2026-09-01"`: `createFromFormat('Y-m', ...)` would take today's day, and on the 31st it would jump to the next month.

<div class="preview">
  <figure><img src="/images/recipes-b/sales-report.png" alt="Landscape Arabic sales report: title, the month, four summary cards, then a table of invoices with number, date, customer, branch, amount before VAT, VAT and total"><figcaption>First page of the September report (PDF)</figcaption></figure>
</div>

## Full control with Doc::make() {#builder}

The template gives one table. When the manager wants the invoices grouped by branch, each group with its own subtotal, build the document in code. Add this method to `MonthlySalesReport`:

```php
public function detailedDocument(): PendingDocument
{
    $invoices = $this->invoices();
    $money = fn ($amount) => number_format((float) $amount, 2);
    $card = fn (string $label, string $value) => [
        'lines' => [
            ['text' => $label, 'color' => '#6B7280', 'size' => 9],
            ['text' => $value, 'bold' => true, 'size' => 13, 'color' => '#0F766E'],
        ],
        'border' => '#E5E7EB',
    ];

    $doc = Doc::make()
        ->heading('تقرير المبيعات حسب الفرع')
        ->paragraph([
            ['text' => 'الفترة: ', 'bold' => true],
            $this->month->startOfMonth()->format('Y/m/d').' - '.$this->month->endOfMonth()->format('Y/m/d'),
        ])
        ->table([[
            $card('إجمالي المبيعات', $money($invoices->sum('total')).' ر.س'),
            $card('ضريبة القيمة المضافة', $money($invoices->sum('vat')).' ر.س'),
            $card('عدد الفواتير', (string) $invoices->count()),
        ]], ['borders' => false]);

    foreach ($invoices->groupBy('branch') as $branch => $branchInvoices) {
        $rows = [['رقم الفاتورة', 'التاريخ', 'العميل', 'قبل الضريبة', 'الضريبة', 'الإجمالي']];

        foreach ($branchInvoices as $invoice) {
            $rows[] = [
                ['text' => $invoice->number, 'ltr' => true],
                $invoice->issued_at->format('Y/m/d'),
                $invoice->customer->name,
                $money($invoice->subtotal),
                $money($invoice->vat),
                $money($invoice->total),
            ];
        }

        $rows[] = [
            ['text' => 'إجمالي الفرع', 'colspan' => 3],
            $money($branchInvoices->sum('subtotal')),
            $money($branchInvoices->sum('vat')),
            $money($branchInvoices->sum('total')),
        ];

        $doc->heading("فرع {$branch}", 2)->table($rows, [
            'header' => true,
            'footer' => true,
            'striped' => '#F9FAFB',
            'font_size' => 9.5,
            'columns' => [18, 13, 27, ['width' => 14, 'align' => 'end'], ['width' => 12, 'align' => 'end'], ['width' => 16, 'align' => 'end']],
        ]);
    }

    return $doc
        ->paragraph('أُعد هذا التقرير آلياً من نظام المبيعات.', ['color' => '#6B7280', 'size' => 9])
        ->title('تقرير المبيعات '.$this->month->format('Y-m'))
        ->locale('ar')
        ->landscape()
        ->margins(12)
        ->footer('<div style="text-align: center;">صفحة {page} من {pages}</div>');
}
```

What each piece does:

- A table is a list of rows, and a row a list of cells. A cell is a string, or an array with `text` or `lines` plus styles (`bold`, `color`, `size`, `ltr`, `colspan`, `border`).
- `header` makes the first row the header (repeated on every page), `footer` makes the last row bold, and `striped` shades every other row.
- `columns` gives widths in percent and, for the money columns, `'align' => 'end'`, which is the left side in an Arabic document.
- `'ltr' => true` keeps `INV-2026-1004` in order inside right-to-left text.
- A heading stays on the same page as the table after it, so a branch title is never left alone at the bottom of a page.
- `->footer()` takes HTML with `{page}` and `{pages}`; Word files get real page-number fields.

The same `PendingDocument` gives both formats: `$report->detailedDocument()->pdf()` and `$report->detailedDocument()->word()`.

<div class="preview">
  <figure><img src="/images/recipes-b/sales-report-builder.png" alt="Landscape Arabic report built in code: three summary cards, then one table per branch with a bold branch total row"><figcaption>The same month, grouped by branch with Doc::make()</figcaption></figure>
</div>

## Large reports {#large-reports}

A month of a busy shop can hold thousands of invoices. Three things change then.

**Memory.** mPDF keeps the whole table in memory while it lays it out, about 85 KB per row. A report of 1,000 rows needs more than PHP's default 128 MB `memory_limit`, and running out of memory ends the process: there is no fallback engine for that. Raise the limit for the code that renders the report, or render it with Chromium (`->driver('chromium')`), which renders 2,000 rows in about 55 MB. See [PDF engines](/guide/engines) to set Chromium up.

**Time.** Rendering thousands of rows takes longer than a web request should. Let a queue worker do it and tell the user when the file is ready.

**The job payload.** `->queue()` puts the document's data into the job. For a report with thousands of rows that is a large payload (SQS takes up to 1 MB, Beanstalkd 64 KB by default). Queue a job of your own that carries only what is needed to run the query again, here the month and the user to notify:

```php
// app/Jobs/BuildSalesReport.php
namespace App\Jobs;

use App\Models\User;
use App\Notifications\SalesReportReady;
use App\Reports\MonthlySalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildSalesReport implements ShouldQueue
{
    use Queueable;

    // Longer than the PDF engine's timeout plus a render by the fallback engine.
    public int $timeout = 300;

    public function __construct(public string $month, public int $userId) {}

    public function handle(): void
    {
        // mPDF keeps the whole table in memory while it lays it out.
        ini_set('memory_limit', '1024M');

        $report = new MonthlySalesReport(CarbonImmutable::parse($this->month.'-01'));
        $path = 'reports/'.$report->filename();

        $report->document()->pdf()->save($path, 's3');

        User::findOrFail($this->userId)->notify(new SalesReportReady($path));
    }
}
```

The worker runs the query itself, so the rows never travel through the queue. Keep `retry_after` in `config/queue.php` above the job's `$timeout`, so a slow report is not started twice.

The request only dispatches the job:

```php
// routes/web.php
use App\Jobs\BuildSalesReport;
use Illuminate\Http\Request;

Route::post('/reports/sales', function (Request $request) {
    $request->validate(['month' => ['required', 'date_format:Y-m']]);

    BuildSalesReport::dispatch($request->input('month'), $request->user()->id);

    return back()->with('status', 'نجهّز التقرير الآن، وسيصلك إشعار عندما يكتمل.');
})->middleware('auth');
```

And the notification sends a link that works for a day:

```php
// app/Notifications/SalesReportReady.php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SalesReportReady extends Notification
{
    public function __construct(public string $path) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تقرير المبيعات جاهز')
            ->line('اكتمل تقرير المبيعات الشهري، ويمكنك تنزيله خلال 24 ساعة.')
            ->action('تنزيل التقرير', Storage::disk('s3')->temporaryUrl($this->path, now()->addDay()));
    }
}
```

::: tip Chromium for big reports
To use Chromium for this report only, add `->driver('chromium')` after `->landscape()` in `document()`. If Chromium fails, the package renders with the fallback engine (mPDF by default) and logs a warning, so keep the raised memory limit as a safety net.
:::

## Variations {#variations}

### Pass the models as rows

The template accepts a collection of models. Dotted keys read loaded relations:

```php
'columns' => [
    ['key' => 'number', 'label' => 'رقم الفاتورة'],
    ['key' => 'customer.name', 'label' => 'العميل'],
    ['key' => 'total', 'label' => 'الإجمالي', 'format' => 'money'],
],
'rows' => $invoices,   // loaded with ->with('customer:id,name')
```

Each model becomes its `toArray()`, so dates arrive as serialized strings and hidden attributes are left out. Map the rows yourself, as in the solution, when you need computed values or exact date handling.

### Arabic digits, or English labels

Add `->numerals('arabic')` to print `١١٢,٩٨٢.٩٠`. With `->locale('en')` the page turns left to right and the template's own words ("Total", "Generated", "Page 1 of 2") switch to English; the column labels and the summary are your data, so pass English ones too, for example with `__()`.

### Every month on its own

Let the scheduler queue last month's report on the first of each month:

```php
// routes/console.php
use App\Jobs\BuildSalesReport;
use App\Models\User;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => BuildSalesReport::dispatch(
    now()->subMonth()->format('Y-m'),
    User::where('email', 'finance@alnoor.example')->value('id'),
))->monthlyOn(1, '06:00');
```

## Related pages {#related}

- [Table report](/templates/report): every field of the `report` template.
- [Building in code](/guide/builder): all blocks, cells and styles of `Doc::make()`.
- [Page settings](/guide/page-settings): paper, orientation, margins, header and footer.
- [PDF engines](/guide/engines): mPDF, Chromium and the fallback engine.
- [Output and delivery](/guide/output): downloads, saving to disks and queued rendering.
