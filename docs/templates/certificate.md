# Certificate

A certificate (شهادة) of completion, attendance, participation or appreciation, issued by a training centre, an academy, a school or a company to one person. The `certificate` template prints it on a framed A4 landscape page with up to three signatures and a QR code that links to a page where anyone can check it.

<div class="preview">
  <figure><a href="/samples/certificate-ar.pdf" target="_blank"><img src="/previews/certificate-ar.png" alt="Arabic certificate of completion in a double frame: the academy name, the title شهادة إتمام, the recipient's name, the course, the dates and hours, two signatures and a QR code"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/certificate-en.pdf" target="_blank"><img src="/previews/certificate-en.png" alt="The same certificate with English wording"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- Completion certificates at the end of a training course, with the dates, the hours and the grade.
- Attendance certificates for a workshop, a seminar or a conference day.
- Participation certificates for speakers, volunteers or competition teams.
- Certificates of appreciation (شهادة شكر وتقدير) for employees, partners or sponsors.
- A verification page where an employer scans the QR code and sees that the certificate is genuine.

## Quick example {#example}

Pass the certificate data:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$certificate = Doc::template('certificate', [
    'type' => 'completion',
    'gender' => 'female',
    'number' => 'CRT-2026-00731',
    'date' => '2026-10-08',
    'recipient' => 'سارة محمود عبد الله',
    'course' => 'تطوير تطبيقات الويب باستخدام Laravel',
    'from' => '2026-09-06',
    'to' => '2026-10-01',
    'hours' => 40,
    'grade' => 'امتياز',
    'issuer' => 'أكاديمية بيزتك للتدريب',
    'signatures' => [
        ['name' => 'م. أحمد عبد الرحمن', 'title' => 'المدرب'],
        ['name' => 'عمرو محمد', 'title' => 'مدير الأكاديمية'],
    ],
    'verify_url' => 'https://academy.example.com/certificates/CRT-2026-00731',
])->locale('ar');
```

Then return the file you need:

::: code-group

```php [PDF]
return $certificate->pdf()->download('CRT-2026-00731.pdf');
```

```php [Word]
return $certificate->word()->download('CRT-2026-00731.docx');
```

:::

You get one A4 landscape page in a double frame, the same as the preview above:

- the issue date and certificate number in one corner, the issuer in the middle, and the QR code with امسح الرمز للتحقق من الشهادة in the other corner;
- the title شهادة إتمام, then تُمنح هذه الشهادة إلى and the recipient's name in large type;
- لإتمامها بنجاح (the feminine form, because `gender` is `female`) and the course name;
- the line خلال الفترة من 2026/09/06 إلى 2026/10/01، بعدد 40 ساعة تدريبية، بتقدير امتياز;
- two signature lines with the names and titles under them.

## Fields {#fields}

The data is validated before anything is drawn. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `type` | Yes | `completion`, `attendance`, `participation`, `appreciation` | `completion` | The kind of certificate. Sets the title and the wording. See [Certificate kinds](#kinds). |
| `gender` | Yes | `male` or `female` | `male` | The recipient's gender, for the Arabic wording: لإتمامه or لإتمامها. |
| `number` | No | text | | The certificate number, in the top corner, kept left to right. |
| `date` | Yes | date | | The issue date (تاريخ الإصدار / Issued on). |
| `recipient` | Yes | text | | The person's name, in large bold type. |
| `course` | Yes | text | | The course, event or contribution, in the primary colour. |
| `details` | No | text | | An extra line under the course details. |
| `from` | No | date | | The first day of the course. |
| `to` | No | date, on or after `from` | | The last day of the course. |
| `hours` | No | number greater than 0 | | Training hours, with the right Arabic word form. Leave it out when there are none. |
| `grade` | No | text | | The grade: بتقدير امتياز / with the grade Excellent. |
| `issuer` | No | text | the theme's `company.name` | Who gives the certificate, at the top centre. |
| `signatures` | No | list, at most 3 | empty | The signatures, side by side. |
| `signatures.*.name` | Yes | text | | The signer's name, in bold under the line. |
| `signatures.*.title` | No | text | | The signer's title, under the name. |
| `verify_url` | No | text | | A link printed as a QR code, to check the certificate online. |

### Theme values {#theme}

| Theme key | Where it shows |
| --- | --- |
| `company.name` | The issuer, when `issuer` is empty. |
| `logo` | Above the issuer, 16 mm high. |
| `primary` | The double frame, the title, the course name and the line under the recipient's name. |
| `muted` | The corner text, the details line and the signature lines. |

## Variants and options {#options}

### Certificate kinds {#kinds}

| `type` | Arabic title | English title | Opening words |
| --- | --- | --- | --- |
| `completion` | شهادة إتمام | Certificate of Completion | تُمنح هذه الشهادة إلى / This certificate is awarded to |
| `attendance` | شهادة حضور | Certificate of Attendance | تُمنح هذه الشهادة إلى / This certificate is awarded to |
| `participation` | شهادة مشاركة | Certificate of Participation | تُمنح هذه الشهادة إلى / This certificate is awarded to |
| `appreciation` | شهادة شكر وتقدير | Certificate of Appreciation | تُقدَّم هذه الشهادة مع خالص الشكر والتقدير إلى / This certificate is presented with sincere thanks to |

The Arabic opening words are passive (تُمنح، تُقدَّم), so they read right whether the issuer is a company, an institute or a university.

### Wording by gender {#gender}

Arabic changes the words after the name with the recipient's gender. The template picks them from `type` and `gender`:

| `type` | `male` | `female` | English |
| --- | --- | --- | --- |
| `completion` | لإتمامه بنجاح | لإتمامها بنجاح | for successfully completing |
| `attendance` | لحضوره | لحضورها | for attending |
| `participation` | لمشاركته في | لمشاركتها في | for participating in |
| `appreciation` | تقديراً لجهوده المتميزة في | تقديراً لجهودها المتميزة في | in appreciation of an outstanding contribution to |

`gender` defaults to `male`, so pass it for every recipient when you issue certificates in bulk.

### Dates, hours and grade {#facts}

The line under the course joins whatever you give:

- `from` and `to`: خلال الفترة من 2026/09/06 إلى 2026/10/01 (from 2026/09/06 to 2026/10/01);
- only one of them, for a one-day event: بتاريخ 2026/10/01 (on 2026/10/01);
- `hours`, with the Arabic count form: بعدد ساعة تدريبية واحدة (1), بعدد ساعتين تدريبيتين (2), بعدد 6 ساعات تدريبية (3 to 10), بعدد 40 ساعة تدريبية (11 and more, and decimals such as 7.5). Past 100 the last two digits decide, so 103 is ساعات and 140 is ساعة. In English: one training hour, 40 training hours;
- `grade`: بتقدير امتياز (with the grade امتياز).

When none of them is given, the line is left out. `details` adds one more line under it, for example the name of the programme.

### Issuer {#issuer}

`issuer` is printed at the top centre, under the logo. Leave it out and the template uses `company.name` from the theme, so a company that issues its own certificates sets it once in the config.

### Signatures {#signatures}

Up to three signatures, each a line to sign on with the name and title under it, spread evenly across the page. A fourth one fails validation, because the page has no room for it.

### Verification QR {#qr}

`verify_url` is printed as a QR code in the top corner, with the caption امسح الرمز للتحقق من الشهادة (Scan to verify). Point it to a page in your app that looks the certificate up by its number and shows the recipient and the course. Leave it out and no QR code is printed.

## Word file {#word}

The certificate has a `pdf.blade.php` for the PDF and a separate `word.php` for Word, built from the same data. The Word file is also A4 landscape, but it has no double frame: the logo, issuer, title, name, course and details are centred down the page, the signatures sit side by side, and the issue date, number and QR code come last at the bottom centre instead of in the top corners.

Word files need `phpoffice/phpword`. See [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template certificate --as=my-certificate
```

This copies the template to `resources/doc-templates/my-certificate/`:

- `template.php`: the fields, the defaults (for example `'type' => 'attendance'`) and the page settings;
- `pdf.blade.php`: the PDF design, with the frame and its CSS (colours, sizes, the frame style);
- `word.php`: the Word layout;
- `lang/ar.php` and `lang/en.php`: the titles and the wording for each kind and gender.

Edit `pdf.blade.php` and `word.php` together when you move things around, so both files stay alike. Use the copy with `Doc::template('my-certificate', $data)`. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Course certificates in bulk](/recipes/certificates): one certificate per trainee, from your database.
- [Official letter](/templates/letter): for letters of recommendation or experience.
- [Payslip](/templates/payslip): another document issued per person.
- [Page settings](/guide/page-settings): paper size and orientation.
- [Images](/guide/images): where the logo can be read from.
