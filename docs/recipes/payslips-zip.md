# Monthly payslips in one ZIP

At the end of the month, HR downloads every employee's payslip as a PDF, all in one ZIP file. For larger companies, a queue worker builds the ZIP or saves each payslip to storage, so no web request has to wait.

## The situation {#situation}

شركة بيزتك للحلول البرمجية calculates payroll in its Laravel app: one `Payslip` row per employee per month, with the earnings and deductions already worked out. On payday the HR manager wants:

- every payslip of the month as a PDF, named after the employee, in one ZIP to print, sign and archive,
- Arabic payslips with the net pay in words,
- a way that still works when the company grows from 40 to 400 employees.

## The solution {#solution}

### 1. The models

`Employee` holds the person (`code`, `name`, `job_title`, `department`, `national_id`, `hire_date`, `bank_name`, `bank_account`). `Payslip` holds one month:

```php
// app/Models/Payslip.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// columns: id, employee_id, period ("2026-09"), number, earnings (json), deductions (json),
//          working_days, present_days, leave_days, overtime_hours, paid_on
class Payslip extends Model
{
    protected function casts(): array
    {
        return ['earnings' => 'array', 'deductions' => 'array', 'paid_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

`earnings` and `deductions` are lists of `['name' => ..., 'amount' => ...]`, for example `[['name' => 'الراتب الأساسي', 'amount' => 18000], ['name' => 'بدل سكن', 'amount' => 3000]]`. `Employee` casts `hire_date` to `date`.

### 2. One class that builds a payslip

```php
// app/Documents/PayslipDocument.php
namespace App\Documents;

use App\Models\Payslip;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class PayslipDocument
{
    public static function for(Payslip $payslip): PendingDocument
    {
        $employee = $payslip->employee;

        return Doc::template('payslip', [
            'period' => $payslip->period,                 // "2026-09"
            'number' => $payslip->number,
            'currency' => 'EGP',
            'employee' => [
                'name' => $employee->name,
                'code' => $employee->code,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'national_id' => $employee->national_id,
                'hire_date' => $employee->hire_date,
                'bank' => $employee->bank_name,
                'bank_account' => $employee->bank_account,
            ],
            'earnings' => $payslip->earnings,             // [['name' => 'الراتب الأساسي', 'amount' => 18000], ...]
            'deductions' => $payslip->deductions,
            'attendance' => [
                'working_days' => $payslip->working_days,
                'present_days' => $payslip->present_days,
                'leave_days' => $payslip->leave_days,
                'overtime_hours' => $payslip->overtime_hours,
            ],
            'payment' => ['method' => 'bank', 'date' => $payslip->paid_on],
        ])->locale('ar');
    }

    /** "EMP-0142 - سارة محمود عبد الله.pdf" */
    public static function filename(Payslip $payslip): string
    {
        return "{$payslip->employee->code} - {$payslip->employee->name}.pdf";
    }
}
```

The [payslip template](/templates/payslip) adds up the earnings and deductions, prints the net pay in figures and words, and takes the company name and address from the theme in the package config. `period` must be a month written as `2026-09`.

### 3. The ZIP download

```php
// routes/web.php
use App\Http\Controllers\PayslipZipController;

Route::middleware('auth')->group(function () {
    Route::get('/hr/payslips/{period}/zip', PayslipZipController::class)
        ->where('period', '[0-9]{4}-[0-9]{2}')
        ->can('manage-payroll');
});
```

```php
// app/Http/Controllers/PayslipZipController.php
namespace App\Http\Controllers;

use App\Documents\PayslipDocument;
use App\Models\Payslip;
use BiztechEG\EasyPdfWord\Facades\Doc;

class PayslipZipController extends Controller
{
    public function __invoke(string $period)
    {
        $payslips = Payslip::with('employee')->where('period', $period)->get();

        abort_if($payslips->isEmpty(), 404);

        $files = $payslips->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)->pdf(PayslipDocument::filename($payslip)));

        return Doc::zip($files->all(), "قسائم-الرواتب-{$period}.zip")->download();
    }
}
```

A `manage-payroll` gate (or a policy) decides who may download salaries. `/hr/payslips/2026-09/zip` then downloads `قسائم-الرواتب-2026-09.zip`, with one file per employee:

```text
EMP-0141 - سارة محمود عبد الله.pdf
EMP-0142 - محمد أحمد علي.pdf
EMP-0143 - منى خالد حسن.pdf
...
```

How it works:

- `->pdf()` checks each payslip's data against the template's rules and prepares the file without rendering it; `Doc::zip()` renders the files one after the other while it writes the archive.
- Because the data is checked first, a payslip with a missing name or a negative amount stops the request with a validation error naming the field, before any PDF is made.
- Each file keeps the name given to `->pdf()`. Two employees with the same code and name would get `name.pdf` and `name (2).pdf`, never a lost file. Names cannot contain folders, so the archive always unpacks into one folder.
- The archive marks the names as UTF-8, so current Windows, macOS and Linux tools show them in Arabic.
- ZIP files need the PHP `zip` extension (`ext-zip`).

## Memory and time {#limits}

A ZIP is built in one PHP process, so it is bound by that process's limits. Measured with mPDF on a developer machine (your server may be slower):

| Payslips | Time | Extra memory | ZIP size |
| --- | --- | --- | --- |
| 50 | 4.2 s | about 30 MB | 1.4 MB |
| 200 | 16 s | about 90 MB | 5.6 MB |

Each payslip takes about 0.08 seconds and is about 33 KB. Time and memory grow with the number of employees, because every PDF is kept until the archive is written. Compare with your limits:

- PHP's `max_execution_time` for web requests (often 30 seconds) and your web server or proxy timeout (60 seconds in a default nginx setup).
- PHP's `memory_limit`, 128 MB by default, of which Laravel itself uses part.

A web request is comfortable for a few dozen employees. Beyond about a hundred, build the ZIP on a queue worker, as below. For a worker, give PHP more memory when you start it, for example `php -d memory_limit=512M artisan queue:work`; the worker's own `--memory` option only decides when it restarts and does not raise PHP's limit.

## Variations {#variations}

### Build the ZIP on a queue worker

HR clicks a button, the request returns at once, and a job builds the archive, stores it and emails HR a link:

```php
// app/Jobs/BuildPayslipsZip.php
namespace App\Jobs;

use App\Documents\PayslipDocument;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\PayslipsReady;
use BiztechEG\EasyPdfWord\Facades\Doc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildPayslipsZip implements ShouldQueue
{
    use Queueable;

    /** Seconds; keep retry_after in config/queue.php above this. */
    public $timeout = 900;

    public $tries = 1;

    public function __construct(public string $period, public User $requestedBy) {}

    public function handle(): void
    {
        $path = "payslips/{$this->period}.zip";

        $files = Payslip::with('employee')->where('period', $this->period)->get()
            ->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)->pdf(PayslipDocument::filename($payslip)));

        Doc::zip($files->all(), "قسائم-الرواتب-{$this->period}.zip")->save($path, 's3');

        $this->requestedBy->notify(new PayslipsReady($this->period, $path));
    }
}
```

```php
// app/Notifications/PayslipsReady.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PayslipsReady extends Notification
{
    use Queueable;

    public function __construct(public string $period, public string $path) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("قسائم رواتب {$this->period} جاهزة")
            ->line('تم تجهيز قسائم الرواتب لكل الموظفين في ملف واحد.')
            ->action('تنزيل الملف', Storage::disk('s3')->temporaryUrl($this->path, now()->addDay()));
    }
}
```

```php
// app/Http/Controllers/QueuePayslipsZipController.php
namespace App\Http\Controllers;

use App\Jobs\BuildPayslipsZip;
use Illuminate\Http\Request;

class QueuePayslipsZipController extends Controller
{
    public function __invoke(Request $request, string $period)
    {
        BuildPayslipsZip::dispatch($period, $request->user());

        return back()->with('status', 'جارٍ تجهيز القسائم، وستصلك رسالة بالرابط عند الانتهاء.');
    }
}
```

Route it with `Route::post('/hr/payslips/{period}/zip', QueuePayslipsZipController::class)` and the same `where` and `can` as the download.

Why these settings: `$timeout` lets the job run for 15 minutes. `retry_after` in `config/queue.php` (90 seconds by default) must be larger, otherwise the queue hands the same job to a second worker while the first is still building. `$tries = 1` because a failed run should be looked at, not repeated for another 15 minutes. The job carries only the period and the user's id, so its payload stays small however many employees there are.

### One file per employee, saved by the queue

If employees download their own payslips from a self-service page, save each one to storage with the package's queued saving. Each payslip becomes a small job, and several workers can render them in parallel:

```php
$period = '2026-09';

foreach (Payslip::with('employee')->where('period', $period)->lazyById() as $payslip) {
    PayslipDocument::for($payslip)
        ->queue("payslips/{$period}/{$payslip->employee->code}.pdf", 's3')
        ->onQueue('documents');
}
```

`lazyById()` reads the payslips in chunks, so the loop uses little memory. The data is validated before each job is queued, so a mistake fails in this loop, not on the worker. The job carries the payslip data, and Laravel encrypts it with the app key. Run a worker for the queue: `php artisan queue:work --queue=documents`.

The employee's page then sends the stored file:

```php
// app/Http/Controllers/MyPayslipController.php
namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MyPayslipController extends Controller
{
    public function __invoke(Request $request, string $period)
    {
        $payslip = Payslip::with('employee')
            ->where('employee_id', $request->user()->employee_id)
            ->where('period', $period)
            ->firstOrFail();

        return Storage::disk('s3')->download(
            "payslips/{$period}/{$payslip->employee->code}.pdf",
            "قسيمة-راتب-{$period}.pdf",
        );
    }
}
```

### Protect each payslip with a password

Salaries are private. Lock each PDF with the employee's national ID, which they know and colleagues usually do not:

```php
$files = $payslips->map(fn (Payslip $payslip) => PayslipDocument::for($payslip)
    ->password($payslip->employee->national_id)
    ->pdf(PayslipDocument::filename($payslip)));
```

PDF readers ask for the password before they open the file. The encryption is 128-bit RC4, the strongest mPDF offers: it keeps a file from being opened by chance, not from a determined attacker. Word files cannot take a password, and `->word()` refuses a document that has one.

## Related pages {#related}

- [Payslip](/templates/payslip): every field of the template.
- [Output and delivery](/guide/output): ZIP files, queued saving and its options.
- [PDF engines](/guide/engines): memory use of mPDF and Chromium for large documents.
- [Configuration](/guide/configuration): the company name and address in the theme.
- [Sales report from a query](/recipes/sales-report): another document built from many rows.
