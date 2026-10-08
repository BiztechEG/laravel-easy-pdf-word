# Payslip

A payslip (قسيمة راتب) is the monthly statement that payroll or HR gives each employee: what they earned, what was deducted and the net pay. The `payslip` template prints it on one A4 page and calculates the totals and the net pay in figures and in words for you.

<div class="preview">
  <figure><a href="/samples/payslip-ar.pdf" target="_blank"><img src="/previews/payslip-ar.png" alt="Arabic payslip: employee details, earnings and deductions side by side, the net salary 18,534.50 in a bar, the net in words, attendance boxes and signature boxes"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/payslip-en.pdf" target="_blank"><img src="/previews/payslip-en.png" alt="The same payslip with English labels"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- The monthly payroll run: one payslip per employee, emailed to each person or collected in a ZIP for the accountant.
- An employee self-service page where staff download the payslip of any past month.
- Salaries paid in cash or by cheque, where each employee signs the printed payslip on receipt.
- A bonus month or a final settlement with extra earnings lines (end-of-service, unused leave).
- A copy of recent payslips for a bank loan or a credit card application.

## Quick example {#example}

Pass one employee's month to the template:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$payslip = Doc::template('payslip', [
    'period' => '2026-09',
    'number' => 'PS-2026-09-0142',
    'currency' => 'EGP',
    'employee' => [
        'name' => 'سارة محمود عبد الله',
        'code' => 'EMP-0142',
        'job_title' => 'مطورة برمجيات أولى',
        'department' => 'تقنية المعلومات',
        'national_id' => '29501011234567',
        'hire_date' => '2022-03-01',
        'bank' => 'البنك الأهلي المصري',
        'bank_account' => 'EG38 0003 0000 0000 1234 5678 901',
    ],
    'earnings' => [
        ['name' => 'الراتب الأساسي', 'amount' => 18000],
        ['name' => 'بدل سكن', 'amount' => 3000],
        ['name' => 'بدل انتقال', 'amount' => 1200],
        ['name' => 'ساعات إضافية', 'amount' => 1450],
    ],
    'deductions' => [
        ['name' => 'التأمينات الاجتماعية', 'amount' => 1980],
        ['name' => 'ضريبة كسب العمل', 'amount' => 2135.50],
        ['name' => 'قسط سلفة', 'amount' => 1000],
    ],
    'attendance' => [
        'working_days' => 22,
        'present_days' => 21,
        'leave_days' => 1,
        'overtime_hours' => 10,
    ],
    'payment' => ['method' => 'bank', 'date' => '2026-09-28'],
])->locale('ar');
```

Then return the file you need:

::: code-group

```php [PDF]
return $payslip->pdf()->download('PS-2026-09-0142.pdf');
```

```php [Word]
return $payslip->word()->download('PS-2026-09-0142.docx');
```

:::

You get one A4 page, the same as the preview above:

- the title قسيمة راتب with the month written out, عن شهر سبتمبر 2026, and the payslip number;
- the employee details two to a row, and the bank account in a row of its own;
- earnings and deductions side by side, with إجمالي الاستحقاقات 23,650.00 and إجمالي الاستقطاعات 5,115.50;
- the net pay in a bar, `صافي الراتب 18,534.50 ج.م`, and under it in words: فقط ثمانية عشر ألفاً وخمسمائة وأربعة وثلاثون جنيهاً وخمسون قرشاً لا غير;
- four attendance boxes, the line طريقة الصرف: تحويل بنكي | تاريخ الصرف: 2026/09/28, and the signature boxes المحاسب، شؤون العاملين، توقيع الموظف بالاستلام;
- the footer `سري - قسيمة راتب سبتمبر 2026 - EMP-0142` with the page number.

With `->locale('en')` the month reads "For September 2026" and the net pay in words "eighteen thousand five hundred thirty-four EGP and 50/100 only" (English words need the `intl` PHP extension).

## Fields {#fields}

The data is validated before anything is drawn. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `period` | Yes | `YYYY-MM`, e.g. `2026-09` | | The month. Printed by name in the document's language: سبتمبر 2026, September 2026. |
| `number` | No | text | | The payslip number, under the title. |
| `currency` | Yes | 3-letter code | `EGP` | Decides the decimals, the currency label next to the net pay and the currency in words. |
| `employee.name` | Yes | text | | The employee's name, in bold. |
| `employee.code` | No | text | | The employee ID. Also printed in the footer. |
| `employee.job_title` | No | text | | Job title. |
| `employee.department` | No | text | | Department. |
| `employee.national_id` | No | text | | National ID, kept left to right. |
| `employee.hire_date` | No | date | | Hire date, printed as `2022/03/01`. |
| `employee.bank` | No | text | | The bank name, printed before the account number. |
| `employee.bank_account` | No | text | | Account number or IBAN, in a row of its own. |
| `earnings` | Yes | list, at least 1 | | Earnings lines: basic salary, allowances, overtime ... |
| `earnings.*.name` | Yes | text | | Line name. |
| `earnings.*.amount` | Yes | number, 0 or more | | Line amount. |
| `deductions` | No | list | empty | Deduction lines: social insurance, income tax, loan instalment ... |
| `deductions.*.name` | Yes | text | | Line name. |
| `deductions.*.amount` | Yes | number, 0 or more | | Line amount. |
| `attendance.working_days` | No | number | | Working days in the month. |
| `attendance.present_days` | No | number | | Days present. |
| `attendance.absent_days` | No | number | | Days absent. |
| `attendance.leave_days` | No | number | | Leave days. |
| `attendance.overtime_hours` | No | number | | Overtime hours. |
| `payment.method` | No | `bank`, `cash`, `cheque` | | How the salary was paid: تحويل بنكي، نقداً، شيك. |
| `payment.date` | No | date | | When it was paid. |
| `notes` | No | text | | A line of notes under the payment line. |
| `signatures` | No | list of text | `accountant`, `hr`, `employee` | The signature boxes, in order. See [Signatures](#signatures). |

The template calculates these values; you do not pass them:

| Value | How it is calculated |
| --- | --- |
| `totals.earnings` | The sum of `earnings.*.amount`, each amount rounded to the currency's decimals first. |
| `totals.deductions` | The sum of `deductions.*.amount`. |
| `totals.net` | `totals.earnings` minus `totals.deductions`. Printed in figures and in words. |

### Theme values {#theme}

| Theme key | Where it shows |
| --- | --- |
| `company.name` | Top corner, bold, in the primary colour. |
| `company.address` | Under the company name. |
| `logo` | Above the company name, 30 mm wide. |
| `primary` | The title, the header row of the earnings table and the net pay bar. |
| `muted` | Field labels, the amount in words and the signature lines. |
| `border` | The lines of the employee table and the attendance boxes. |

Set them in `config/easy-pdf-word.php` or per document with `->theme([...])`; see [Configuration](/guide/configuration).

## Variants and options {#options}

### Earnings and deductions {#earnings-deductions}

Earnings sit on one side and deductions on the other, one line per row; when one list is longer, the other side of those rows stays empty. The last row holds the two totals. Each amount is rounded to the currency's decimals: 2 for most currencies, 3 for `KWD`, `BHD`, `OMR` and `JOD`. A payslip with no deductions leaves that side empty and the net pay equals the earnings.

### Net pay in words {#net-in-words}

The net pay is written in words in the document's language, under the net pay bar:

- in Arabic documents, as tafqeet with the currency name: جنيهاً وقرشاً for `EGP`, ريال وهللة for `SAR`, دينار وفلس for `KWD`, and so on;
- in English documents, through the `intl` extension: "eighteen thousand five hundred thirty-four EGP and 50/100 only". Without `intl` the line is left out.

When deductions are larger than earnings the net pay is negative, and the line in words is left out.

For a Saudi payslip, set the currency and the signatures you use:

```php
$data['currency'] = 'SAR';
$data['signatures'] = ['hr', 'المدير العام'];
```

The net pay then shows `ر.س` and is written in riyals, and the signature boxes read شؤون العاملين and المدير العام.

### The month {#period}

`period` takes the month as `2026-09`. The template prints it by name in the document's language: عن شهر سبتمبر 2026 in Arabic, For September 2026 in English. The same month name appears in the footer.

### Employee details {#employee}

The employee table shows the details two to a row and skips any you leave out. The employee ID and the national ID stay left to right inside Arabic text. `bank` and `bank_account` share a row of their own under the label الحساب البنكي, so a long IBAN has room.

### Attendance {#attendance}

The template draws one box for each attendance figure you pass, in the order you pass them, and skips the row when there is no `attendance`. Decimal values such as `1.5` leave days are printed as they are.

### Payment and notes {#payment}

`payment.method` and `payment.date` print one line: طريقة الصرف: تحويل بنكي | تاريخ الصرف: 2026/09/28. Either part can be left out. `notes` adds a line under it.

| `payment.method` | Arabic | English |
| --- | --- | --- |
| `bank` | تحويل بنكي | Bank transfer |
| `cash` | نقداً | Cash |
| `cheque` | شيك | Cheque |

### Signatures {#signatures}

`signatures` lists the signature boxes in order. These roles are translated; any other text is printed as you wrote it:

| Role | Arabic | English |
| --- | --- | --- |
| `accountant` | المحاسب | Accountant |
| `hr` | شؤون العاملين | Human resources |
| `employee` | توقيع الموظف بالاستلام | Received by employee |

Pass an empty list, `'signatures' => []`, to print no signature boxes, for example on payslips that are only emailed.

### Footer {#footer}

Every page has a small footer: `سري - قسيمة راتب سبتمبر 2026 - EMP-0142` (Confidential - Payslip September 2026 - EMP-0142) on one side and the page number on the other. The employee ID is added only when `employee.code` is given.

## Word file {#word}

The payslip has a single `layout.php` that builds both files, so the Word file has the same tables, net pay bar, attendance boxes and signature boxes as the PDF. The footer becomes a line of small grey text in the Word footer, with Word page numbers. HR can open the Word file to add a note before sending it.

Word files need `phpoffice/phpword`. See [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template payslip --as=my-payslip
```

This copies the template to `resources/doc-templates/my-payslip/`:

- `template.php`: the fields and defaults, for example `'currency' => 'SAR'`;
- `layout.php`: the layout of both the PDF and the Word file;
- `footer.blade.php`: the confidential footer;
- `lang/ar.php` and `lang/en.php`: the labels. In Saudi Arabia you might change `'national_id' => 'الرقم القومي'` to `'رقم الهوية / الإقامة'`.

Use the copy with `Doc::template('my-payslip', $data)`. A new field needs a rule in `template.php` and a line in `layout.php` that prints it. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Monthly payslips in one ZIP](/recipes/payslips-zip): render every employee's payslip and download them together.
- [Receipt and payment voucher](/templates/receipt): for advances and other payments to employees.
- [Official letter](/templates/letter): for salary and employment letters.
- [Testing document features](/recipes/testing-documents): check payslips in your tests without rendering them.
- [Arabic support](/guide/arabic): amounts in words, Arabic digits and currencies.
