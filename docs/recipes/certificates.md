# Course certificates in bulk

When a course ends, issue a certificate to every participant who passed: Arabic wording that follows each person's gender, a QR code that opens a verification page in your app, each PDF saved to storage by a queue worker and then emailed to its owner.

## The situation {#situation}

أكاديمية بيزتك للتدريب has just finished a 40-hour course, تطوير تطبيقات الويب باستخدام Laravel. The course page in the admin panel has an **Issue certificates** button. Pressing it should:

- give every participant who passed a numbered certificate of completion, worded for them: لإتمامه بنجاح for a man, لإتمامها بنجاح for a woman,
- print a QR code on each certificate that employers can scan to confirm it is genuine,
- keep every certificate on storage, so it can be downloaded again years later,
- email each participant their certificate,
- return at once, even for a course of a hundred participants.

## The solution {#solution}

### 1. The models

```php
// app/Models/Participant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// columns: id, course_id, name, email, gender ("male" or "female"), grade, passed,
//          certificate_number, certificate_code, certificate_issued_at
class Participant extends Model
{
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'certificate_issued_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificatePath(): string
    {
        return "certificates/{$this->course_id}/{$this->certificate_number}.pdf";
    }
}
```

`Course` has `title`, `starts_on` and `ends_on` (cast to `date`), `hours` and `trainer_name`, and a `participants()` relation. The certificate number is printed on the certificate; the `certificate_code` is a long random string used only in the verification link, so nobody can guess other people's certificates by counting numbers.

### 2. One class that builds a certificate

```php
// app/Documents/CertificateDocument.php
namespace App\Documents;

use App\Models\Participant;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PendingDocument;

class CertificateDocument
{
    public static function for(Participant $participant): PendingDocument
    {
        $course = $participant->course;

        return Doc::template('certificate', [
            'type' => 'completion',
            'gender' => $participant->gender,              // "male" or "female"
            'number' => $participant->certificate_number,
            'date' => $participant->certificate_issued_at,
            'recipient' => $participant->name,
            'course' => $course->title,
            'from' => $course->starts_on,
            'to' => $course->ends_on,
            'hours' => $course->hours,
            'grade' => $participant->grade,
            'signatures' => [
                ['name' => $course->trainer_name, 'title' => 'المدرب'],
                ['name' => 'عمرو محمد', 'title' => 'مدير الأكاديمية'],
            ],
            'verify_url' => route('certificates.verify', $participant->certificate_code),
        ])->locale('ar');
    }
}
```

The [certificate template](/templates/certificate) does the Arabic grammar for you:

- `gender` picks the wording: تُمنح هذه الشهادة إلى ... لإتمامها بنجاح for `female`, لإتمامه بنجاح for `male`. If your app stores gender another way, map it here, for example `$participant->sex === 'f' ? 'female' : 'male'`.
- `hours` is counted the Arabic way: ساعة تدريبية واحدة، ساعتين تدريبيتين، 6 ساعات تدريبية، 40 ساعة تدريبية.
- `from` and `to` print as خلال الفترة من ... إلى ...; with only one of them, as بتاريخ ....
- `verify_url` is drawn as a QR code in the corner, with امسح الرمز للتحقق من الشهادة under it.
- The academy's name at the top comes from `issuer`, or by default from the company name in the theme (`theme.company.name` in the package config).

<div class="preview">
  <figure><a href="/images/recipes-a/certificate.png" target="_blank"><img src="/images/recipes-a/certificate.png" alt="An Arabic certificate of completion for سارة محمود عبد الله with the course name, dates, hours, grade, two signatures and a verification QR code"></a><figcaption>The certificate for a female participant</figcaption></figure>
</div>

### 3. Issue, save and email in the queue

```php
// app/Http/Controllers/IssueCertificatesController.php
namespace App\Http\Controllers;

use App\Documents\CertificateDocument;
use App\Jobs\SendCertificate;
use App\Models\Course;
use Illuminate\Support\Str;

class IssueCertificatesController extends Controller
{
    public function __invoke(Course $course)
    {
        $participants = $course->participants()
            ->where('passed', true)
            ->whereNull('certificate_issued_at')
            ->get();

        foreach ($participants as $participant) {
            $participant->update([
                'certificate_number' => sprintf('CRT-%d-%05d', now()->year, $participant->id),
                'certificate_code' => Str::random(32),
                'certificate_issued_at' => now(),
            ]);

            CertificateDocument::for($participant)
                ->queue($participant->certificatePath(), 's3')
                ->chain([new SendCertificate($participant)]);
        }

        return back()->with('status', 'جارٍ إصدار الشهادات وإرسالها إلى المشاركين.');
    }
}
```

For each participant, `->queue()` sends a job that renders the PDF and saves it to `certificates/{course}/CRT-2026-00001.pdf` on S3. `->chain()` adds a second job that runs only after the file is saved; if saving fails, no mail goes out with a missing attachment. Participants who already have a certificate are skipped, so pressing the button twice does not send anything twice.

The chained job sends the mail:

```php
// app/Jobs/SendCertificate.php
namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\Participant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCertificate implements ShouldQueue
{
    use Queueable;

    public function __construct(public Participant $participant) {}

    public function handle(): void
    {
        Mail::to($this->participant)->send(new CertificateMail($this->participant));
    }
}
```

The Mailable attaches the stored file, so the participant receives exactly the certificate kept on storage:

```php
// app/Mail/CertificateMail.php
namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "شهادة دورة {$this->participant->course->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.certificates.issued');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('s3', $this->participant->certificatePath())
                ->as("شهادة-{$this->participant->certificate_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

```blade
{{-- resources/views/mail/certificates/issued.blade.php --}}
<x-mail::message>
# تهانينا {{ $participant->name }}

يسعدنا أن نرسل إليك شهادة دورة «{{ $participant->course->title }}»، وتجدها مرفقة بهذه الرسالة.

يمكن لأي جهة التحقق من الشهادة بمسح رمز QR المطبوع عليها.

مع التحية،<br>
{{ config('app.name') }}
</x-mail::message>
```

Each job carries what it needs: the certificate data for the first one (encrypted with the app key), the participant's id for the second. A worker on another server saves to S3, which the web server can read too; a `local` disk would leave the files on the worker's machine.

### 4. The verification page

```php
// routes/web.php
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\IssueCertificatesController;

Route::post('/courses/{course}/certificates', IssueCertificatesController::class)
    ->middleware('auth')->name('courses.certificates');

Route::get('/certificates/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/certificates/{code}/download', [CertificateController::class, 'download'])->name('certificates.download');
```

```php
// app/Http/Controllers/CertificateController.php
namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /** The page the QR code opens. */
    public function verify(string $code)
    {
        $participant = Participant::with('course')->where('certificate_code', $code)->firstOrFail();

        return view('certificates.verify', ['participant' => $participant]);
    }

    /** A copy of the stored certificate, linked from the verification page. */
    public function download(string $code)
    {
        $participant = Participant::where('certificate_code', $code)->firstOrFail();

        return Storage::disk('s3')->download($participant->certificatePath(), "شهادة-{$participant->certificate_number}.pdf");
    }
}
```

```blade
{{-- resources/views/certificates/verify.blade.php --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>التحقق من شهادة</title>
</head>
<body>
    <h1>شهادة صحيحة</h1>
    <p>رقم الشهادة: {{ $participant->certificate_number }}</p>
    <p>
        صدرت إلى <strong>{{ $participant->name }}</strong>
        عن دورة «{{ $participant->course->title }}»
        بتاريخ {{ $participant->certificate_issued_at->format('Y/m/d') }}.
    </p>
    <a href="{{ route('certificates.download', $participant->certificate_code) }}">تنزيل نسخة من الشهادة</a>
</body>
</html>
```

The verification routes are public on purpose: whoever scans the QR is usually not a user of your app. An unknown code returns 404. In your app, the page would extend your public layout.

::: warning Use your public domain in the link
`route()` writes a full URL from the domain of the current request, or from `APP_URL` on a queue worker or in a command. The link is fixed in the PDF for years, so issue certificates from your public domain (not from `localhost` or a staging server) and keep the route's path stable.
:::

## Variations {#variations}

### English certificates

For participants who prefer English, change the language. The template has English wording for every type:

```php
CertificateDocument::for($participant)->locale('en')->pdf();
```

Store the preference on the participant and call `->locale($participant->locale)` in `CertificateDocument`. The signature titles in that class are your own text, so translate them too, for example with `__()`.

### A one-day workshop with an attendance certificate

Use `type` `attendance` (شهادة حضور ... لحضوره / لحضورها) and give only one date:

```php
CertificateDocument::for($participant)
    ->with(['type' => 'attendance', 'from' => '2026-10-15', 'to' => null, 'hours' => 6, 'grade' => null])
    ->pdf();
```

`->with()` replaces the given keys of the data, so the rest of the class stays the same. This prints بتاريخ 2026/10/15 and بعدد 6 ساعات تدريبية. The other types are `participation` and `appreciation` (شهادة شكر وتقدير).

### A Word copy for manual changes

The certificate template also makes a Word file, for the rare certificate someone wants to adjust by hand (needs `phpoffice/phpword`):

```php
return CertificateDocument::for($participant)->word("شهادة-{$participant->certificate_number}.docx")->download();
```

### Re-issue after a name correction

If a participant's name was misspelled, fix it and queue their certificate again to the same path. The new file replaces the old one, and the QR link stays the same because the code does not change:

```php
$participant->update(['name' => 'سارة محمود عبد الله']);

CertificateDocument::for($participant)
    ->queue($participant->certificatePath(), 's3')
    ->chain([new SendCertificate($participant)]);
```

## Testing it {#testing}

With `Queue::fake()` you can check what the button queues without rendering anything:

```php
use App\Jobs\SendCertificate;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use Illuminate\Support\Facades\Queue;

Queue::fake();

$this->actingAs($admin)->post("/courses/{$course->id}/certificates");

Queue::assertPushedWithChain(SaveDocument::class, [SendCertificate::class]);
Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->path === 'certificates/1/CRT-2026-00001.pdf' && $job->disk === 's3');
```

More in [Testing document features](/recipes/testing-documents).

## Related pages {#related}

- [Certificate](/templates/certificate): every field, the four types and the wording.
- [Output and delivery](/guide/output): queued saving, `->onQueue()` and `->chain()`.
- [Arabic support](/guide/arabic): how Arabic counts and dates are written.
- [Email an invoice](/recipes/email-invoice): attaching a freshly made PDF instead of a stored one.
