# Contracts from your data

Generate a ready-to-sign contract from your database: the parties, the dates and amounts come from the record, and the clauses come from a clause library your legal team edits without a deploy. The same contract opens in the browser, goes out by email and downloads as a Word file for review.

## The situation {#situation}

A property management company in Riyadh rents out apartments on behalf of their owners. Every new lease needs a residential lease contract:

- the landlord and the lessee, with their ID or commercial registration numbers, come from the `contacts` table;
- the unit, the start and end dates, the monthly rent (in figures and in words), the deposit and the payment day come from the lease;
- the standard clauses are written by the legal team, who change a word now and then and do not want to wait for a developer;
- some leases have special terms of their own, and two witnesses sign every contract.

Staff check the contract in the browser before printing, the legal team reviews odd cases in Word, and the final PDF is kept on S3 and emailed to the lessee.

## The solution {#solution}

### 1. The tables

```php
// database/migrations/2026_10_01_000000_create_leases_table.php
Schema::create('leases', function (Blueprint $table) {
    $table->id();
    $table->string('number');                        // L-2026-0145
    $table->foreignId('unit_id')->constrained();
    $table->foreignId('landlord_id')->constrained('contacts');
    $table->foreignId('lessee_id')->constrained('contacts');
    $table->date('starts_on');
    $table->date('ends_on');
    $table->decimal('monthly_rent', 12, 2);
    $table->decimal('deposit', 12, 2);
    $table->unsignedTinyInteger('payment_day');
    $table->date('signed_on')->nullable();
    $table->text('special_terms')->nullable();
    $table->json('witnesses')->nullable();
    $table->string('contract_path')->nullable();     // the PDF on S3
    $table->timestamps();
});

Schema::create('contract_clauses', function (Blueprint $table) {
    $table->id();
    $table->string('contract_type');                 // residential-lease, office-lease ...
    $table->unsignedSmallInteger('position');
    $table->string('title');
    $table->text('body');
});
```

A contact has `name`, `id_label` (for example "سجل تجاري رقم" or "هوية وطنية رقم"), `id_number`, `address`, `email` and, for companies, `representative` and `representative_title`. A unit has `name`, `building`, `address` and `city`.

The clause bodies are plain text with markers the code fills in, for example:

```text
الأجرة الشهرية :rent ريال (:rent_words).
تُدفع الأجرة مقدماً في اليوم :payment_day من كل شهر ميلادي بالتحويل إلى حساب الطرف الأول.
```

Each line of a clause becomes its own paragraph in the contract.

### 2. The contract, built from the lease

```php
// app/Models/Lease.php
namespace App\Models;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lease extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'signed_on' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
            'witnesses' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'landlord_id');
    }

    public function lessee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'lessee_id');
    }

    public function contractDocument(): PendingDocument
    {
        $this->loadMissing('unit', 'landlord', 'lessee');

        $values = [
            ':unit' => "{$this->unit->name} في {$this->unit->building}",
            ':address' => $this->unit->address,
            ':start' => $this->starts_on->format('Y/m/d'),
            ':end' => $this->ends_on->format('Y/m/d'),
            ':rent' => number_format((float) $this->monthly_rent, 2),
            ':rent_words' => tafqeet($this->monthly_rent, 'SAR', only: true),
            ':deposit' => number_format((float) $this->deposit, 2),
            ':payment_day' => $this->payment_day,
        ];

        $clauses = ContractClause::query()
            ->where('contract_type', 'residential-lease')
            ->orderBy('position')
            ->get()
            ->map(fn (ContractClause $clause) => [
                'title' => $clause->title,
                'text' => strtr($clause->body, $values),
            ]);

        if ($this->special_terms) {
            $clauses->push(['title' => 'شروط خاصة', 'text' => $this->special_terms]);
        }

        return Doc::template('contract', [
            'contract' => [
                'title' => 'عقد إيجار وحدة سكنية',
                'number' => $this->number,
                'date' => $this->signed_on ?? now(),
                'place' => $this->unit->city,
            ],
            'parties' => [
                $this->party($this->landlord, 'المؤجر'),
                $this->party($this->lessee, 'المستأجر'),
            ],
            'preamble' => "يملك الطرف الأول {$values[':unit']}، ويرغب الطرف الثاني في استئجارها للسكن، وقد عاينها المعاينة النافية للجهالة وقبلها بحالتها الراهنة.",
            'clauses' => $clauses,
            'copies' => 2,
            'witnesses' => $this->witnesses ?? [],
        ])->locale('ar');
    }

    private function party(Contact $contact, string $alias): array
    {
        return [
            'name' => $contact->name,
            'alias' => $alias,
            'id_label' => $contact->id_label,
            'id' => (string) $contact->id_number,
            'address' => $contact->address,
            'represented_by' => $contact->representative,
            'capacity' => $contact->representative_title,
        ];
    }

    public function contractFilename(string $extension = 'pdf'): string
    {
        return "عقد-إيجار-{$this->number}.{$extension}";
    }
}
```

What the `contract` template does with this:

- **Parties** are printed in order as "الطرف الأول" and "الطرف الثاني", with the `alias` under each label and the representative and capacity for a company. The template needs at least two parties, and `id` must be a string, hence the cast: an ID stored as a number would fail the template's validation.
- **Clauses** are numbered for you: "البند الأول: محل العقد", "البند الثاني: مدة العقد" and so on. Every line of `text` starts a new paragraph. A clause without `title` gets only its number.
- **`strtr()`** fills all the markers in one pass and tries the longest marker first, so `:rent_words` is never read as `:rent` followed by `_words`. `tafqeet(4500, 'SAR', only: true)` returns "فقط أربعة آلاف وخمسمائة ريال لا غير".
- **The date** can be a Carbon instance; the opening line reads "إنه في يوم الخميس الموافق 2026/10/08 تحرر هذا العقد في الرياض بين كل من:".
- **`copies`** (2 by default) writes the closing sentence about the copies, followed by a signature box for each party and a line for each witness.
- Every page of the PDF ends with a line for each party to sign that page ("توقيع الطرف الأول: ...."), then the contract title and number and "صفحة 1 من 2". If the theme has a logo, it is printed centred at the top.

Because the clauses are rows, the legal team edits them in an admin screen and the next contract uses the new wording. Contracts already sent keep their text, since the PDF is stored (step 3).

<div class="preview">
  <figure><img src="/images/recipes-b/lease-contract.png" alt="First page of an Arabic residential lease: title and number, the opening line with the date and city, the two parties, a preamble and numbered clauses"><figcaption>First page of lease L-2026-0145</figcaption></figure>
</div>

### 3. Routes and controller

```php
// routes/web.php
use App\Http\Controllers\LeaseContractController;

Route::middleware('auth')->group(function () {
    Route::get('/leases/{lease}/contract', [LeaseContractController::class, 'show'])->name('leases.contract');
    Route::get('/leases/{lease}/contract.docx', [LeaseContractController::class, 'word'])->name('leases.contract.word');
    Route::post('/leases/{lease}/contract/send', [LeaseContractController::class, 'send'])->name('leases.contract.send');
});
```

```php
// app/Http/Controllers/LeaseContractController.php
namespace App\Http\Controllers;

use App\Mail\LeaseContractMail;
use App\Models\Lease;
use Illuminate\Support\Facades\Mail;

class LeaseContractController extends Controller
{
    /** Opens in the browser for a last check before printing. */
    public function show(Lease $lease)
    {
        return $lease->contractDocument()->pdf()->stream($lease->contractFilename());
    }

    public function word(Lease $lease)
    {
        return $lease->contractDocument()->word()->download($lease->contractFilename('docx'));
    }

    /** Keeps the final PDF on S3 and emails it to the lessee. */
    public function send(Lease $lease)
    {
        $path = $lease->contractDocument()->pdf()->save("leases/{$lease->number}/contract.pdf", 's3');
        $lease->update(['contract_path' => $path]);

        Mail::to($lease->lessee->email)->queue(new LeaseContractMail($lease));

        return back()->with('status', 'أُرسل العقد إلى المستأجر.');
    }
}
```

- `stream()` shows the PDF in the browser tab (`inline`), and `download()` saves it (`attachment`). The Arabic file name `عقد-إيجار-L-2026-0145.pdf` is sent in the UTF-8 form that browsers read, with an ASCII fallback for old clients.
- `save()` returns the path it wrote, so it can go straight into `contract_path`.
- The PDF is stored before the email is queued. The email then attaches the stored file, which is exactly what the lessee signs, even if a clause changes before the queue sends it.

### 4. The Mailable

```php
// app/Mail/LeaseContractMail.php
namespace App\Mail;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaseContractMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lease $lease) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "عقد إيجار الوحدة رقم {$this->lease->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lease-contract');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('s3', $this->lease->contract_path)
                ->as($this->lease->contractFilename())
                ->withMime('application/pdf'),
        ];
    }
}
```

The job carries only the lease's id (`SerializesModels`), and the worker reads the stored PDF from S3, so the queue payload stays small.

## Variations {#variations}

### A Word copy for the legal team, and a marked draft

When a lease has unusual special terms, send the legal team an editable Word file and a PDF marked as a draft, so nobody signs the draft by mistake. A rendered file can be returned from `attachments()` as it is:

```php
// app/Mail/LegalReviewMail.php
public function attachments(): array
{
    $contract = $this->lease->contractDocument();

    return [
        $contract->word($this->lease->contractFilename('docx')),
        $contract->watermark('مسودة')->pdf("عقد-إيجار-{$this->lease->number}-مسودة.pdf"),
    ];
}
```

The Word file has the same parties, numbered clauses and signature boxes, laid out right to left. The watermark is drawn diagonally across every page of the PDF; Word files have no watermark.

### Three copies, or your own closing sentence

If a real estate office keeps a third copy, the closing sentence must say so. `->data()` replaces top-level keys of the data you already passed, so the rest of the contract stays as `contractDocument()` built it:

```php
$lease->contractDocument()
    ->data([
        'copies' => 3,
        'closing' => 'حُرر هذا العقد من ثلاث نسخ، بيد كل طرف نسخة، وتُحفظ الثالثة لدى مكتب الوساطة العقارية.',
    ])
    ->pdf();
```

With `copies` alone, the template's own sentence says "ثلاث نسخ". `closing` replaces the whole sentence.

### Other kinds of contract

An office lease, a service contract or an employment contract uses the same template: change the `contract_type` in the clause query, the title, and the party aliases (for example "صاحب العمل" and "الموظف"). Keep one method per contract type on its model, so each one fills its own markers.

## Related pages {#related}

- [Contract](/templates/contract): every field of the `contract` template, with samples.
- [Arabic support](/guide/arabic): `tafqeet()`, Arabic digits and right-to-left text.
- [Output and delivery](/guide/output): `stream()`, `download()`, `save()` and mail attachments.
- [Word files](/guide/word): what the Word version of a template contains.
- [Page settings](/guide/page-settings): the watermark and the password.
- [Email an invoice](/recipes/email-invoice): attaching a document that is rendered when the email is sent.
