# Official letter

An official letter (خطاب رسمي) on your company letterhead, the kind a manager or HR sends to a client, a bank, an embassy or a government office. The `letter` template adds the reference number, the Gregorian and Hijri dates, the subject line, the signature, the stamp and the copies list.

<div class="preview">
  <figure><a href="/samples/letter-ar.pdf" target="_blank"><img src="/previews/letter-ar.png" alt="Arabic official letter: letterhead, reference number, Gregorian and Hijri dates, recipient, subject, three paragraphs, the sender's title and name, and a copies list"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/letter-en.pdf" target="_blank"><img src="/previews/letter-en.png" alt="The same letter with English labels"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- Offer or cover letters to a client, as in the sample above.
- Salary and employment letters (إفادة، شهادة لمن يهمه الأمر) that employees take to a bank or an embassy.
- Letters to government offices that expect a reference number and the Hijri date, as is common in Saudi Arabia.
- Notices to suppliers or tenants: a payment reminder, a contract renewal or a termination notice.
- Internal memos sent to a manager with copies to other departments.

## Quick example {#example}

Pass the letter data:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$letter = Doc::template('letter', [
    'reference' => 'ص/2026/417',
    'date' => '2026-10-08',
    'recipient' => [
        'name' => 'المهندس أحمد عبد الرحمن',
        'title' => 'مدير إدارة تقنية المعلومات',
        'organization' => 'مؤسسة النور للتجارة',
    ],
    'subject' => 'عرض تنفيذ نظام إدارة المستندات',
    'body' => "إشارة إلى اجتماعنا المنعقد بتاريخ 1 أكتوبر 2026، يسعدنا أن نقدم لكم عرضنا لتنفيذ نظام إدارة المستندات الإلكترونية، والذي يشمل إصدار الفواتير والخطابات والتقارير باللغتين العربية والإنجليزية.\n\n"
        ."تبلغ مدة التنفيذ 45 يوم عمل من تاريخ التعاقد، ويتضمن العرض تدريب فريقكم والدعم الفني لمدة 12 شهراً.\n\n"
        .'نأمل أن ينال عرضنا رضاكم، ونحن على استعداد للرد على أي استفسارات.',
    'sender' => [
        'name' => 'عمرو محمد',
        'title' => 'المدير التنفيذي',
    ],
    'cc' => ['الإدارة المالية', 'الملف العام'],
])->locale('ar');
```

Then return the file you need:

::: code-group

```php [PDF]
return $letter->pdf()->download('letter-417.pdf');
```

```php [Word]
return $letter->word()->download('letter-417.docx');
```

:::

You get one A4 page, the same as the preview above:

- the letterhead: the company name and address from the theme, the logo, and a line in the primary colour;
- الرقم ص/2026/417، التاريخ 2026/10/08 and الموافق 27 ربيع الآخر 1448 هـ, the Hijri date worked out from `date`;
- the recipient: السيد/ مدير إدارة تقنية المعلومات, the name in bold and the organisation;
- the centred subject الموضوع: عرض تنفيذ نظام إدارة المستندات, the greeting (تحية طيبة وبعد،), three justified paragraphs, and the closing (وتفضلوا بقبول فائق الاحترام والتقدير،);
- the sender's title and name, with room to sign;
- صورة إلى: and the copies list, then a footer with the page number.

## Fields {#fields}

The data is validated before anything is drawn. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `reference` | No | text | | Your outgoing reference number (الرقم / Ref.). |
| `date` | Yes | date | | The letter date, printed as `2026/10/08`, and the source of the Hijri date. |
| `show_hijri` | No | `true` or `false` | `true` | Prints the Hijri date under the Gregorian date when the letter is in Arabic. |
| `recipient.name` | Yes | text | | The recipient's name, in bold. |
| `recipient.title` | No | text | | The recipient's position. Printed above the name as السيد/ مدير إدارة ... in Arabic. |
| `recipient.organization` | No | text | | The recipient's company or office, under the name. |
| `subject` | Yes | text | | The subject line, centred: الموضوع: ... / Subject: ... |
| `greeting` | No | text | تحية طيبة وبعد، / Dear Sir/Madam, | The line before the body. |
| `body` | Yes | text or list of paragraphs | | The letter text. In a string, a blank line starts a new paragraph. |
| `closing` | No | text | وتفضلوا بقبول فائق الاحترام والتقدير، / Yours sincerely, | The line after the body. |
| `sender.name` | Yes | text | | The signer's name, in bold. |
| `sender.title` | No | text | | The signer's position, above the signature. |
| `signature` | No | image path or data URI | | A scanned signature, printed between the title and the name. |
| `stamp` | No | image path or data URI | | The company stamp, printed beside the signature. |
| `cc` | No | list of text | empty | Copies to (صورة إلى / Copy to), one line each. |

### Theme values {#theme}

| Theme key | Where it shows |
| --- | --- |
| `company.name` | The letterhead, bold, in the primary colour. |
| `company.address` | Under the company name, and in the footer. |
| `company.phone` | In the footer, kept left to right. |
| `company.email` | In the footer, kept left to right. |
| `logo` | The other side of the letterhead, 18 mm high. |
| `primary` | The company name and the line under the letterhead. |
| `muted` | The labels of the reference block and the copies heading. |

A letterhead with full contact details in the footer:

```php
Doc::template('letter', $data)
    ->theme([
        'logo' => public_path('images/logo.png'),
        'company' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'phone' => '+20 2 3333 4444',
            'email' => 'info@biztech.example',
        ],
    ])
    ->locale('ar')
    ->pdf();
```

## Variants and options {#options}

### Reference number {#reference}

`reference` is printed as you give it, in the reference block next to the dates. Letters often use a prefix with slashes, such as ص/2026/417 (صادر, outgoing). Leave it out and the row is not printed.

### Hijri date {#hijri}

In Arabic letters the template adds the Hijri (Umm al-Qura) date of `date` under the Gregorian one, labelled الموافق: 2026-10-08 becomes 27 ربيع الآخر 1448 هـ. This needs the `intl` PHP extension; without it the row is skipped. Only right-to-left documents show it, so English letters do not. To leave it out of an Arabic letter, pass `'show_hijri' => false`. With `->numerals('arabic')` it reads ٢٧ ربيع الآخر ١٤٤٨ هـ. More in [Arabic support](/guide/arabic).

### Body, greeting and closing {#body}

`body` is a string or a list of paragraphs. In a string, separate paragraphs with a blank line (`\n\n`). A list is easier when the paragraphs come from your code, for example an employment letter:

```php
Doc::template('letter', [
    'date' => '2026-10-08',
    'recipient' => ['name' => 'السادة/ البنك الأهلي المصري', 'organization' => 'فرع الدقي'],
    'subject' => 'إفادة عمل',
    'body' => [
        'نود إفادتكم بأن السيدة سارة محمود عبد الله تعمل لدى شركتنا بوظيفة مطورة برمجيات أولى منذ 1 مارس 2022.',
        'وقد أُعطيت لها هذه الإفادة بناءً على طلبها دون أدنى مسؤولية على الشركة.',
    ],
    'show_hijri' => false,
    'greeting' => 'تحية طيبة وبعد،،،',
    'closing' => 'وتفضلوا بقبول وافر الاحترام،',
    'sender' => ['name' => 'منى سامي', 'title' => 'مدير الموارد البشرية'],
])->locale('ar')->pdf();
```

`greeting` and `closing` replace the default lines. Paragraphs are justified in both the PDF and the Word file.

### Recipient {#recipient}

The recipient block has up to three lines: `recipient.title` (in Arabic after السيد/), `recipient.name` in bold, and `recipient.organization`. When you write to an organisation rather than a person, put the full form of address in `name`, as in السادة/ البنك الأهلي المصري above, and leave `title` out.

### Signature and stamp {#signature-stamp}

`signature` and `stamp` take an image: a path or a data URI. The signature goes between the sender's title and name; the stamp goes beside it:

```php
$data['signature'] = storage_path('app/signatures/ceo.png');
$data['stamp'] = storage_path('app/signatures/stamp.png');
```

Local images are read only from the folders allowed in the config (`public`, `storage/app` and `resources` by default), and URLs only when remote images are turned on. An image that is not allowed is skipped without an error, so check the result once. Use a PNG with a transparent background. See [Images](/guide/images).

::: warning
Keep signature and stamp files out of the `public` folder, where anyone could download them. `storage/app` is a safer place.
:::

### Copies {#cc}

`cc` lists who receives a copy. Each entry is printed on its own line under صورة إلى: (Copy to:), after the signature.

### Letterhead and footer {#letterhead}

The letterhead and the footer come from the theme, so every letter from your app looks the same. The footer joins `company.address`, `company.phone` and `company.email` with `|` and adds the page number. Values you leave empty are skipped.

## Word file {#word}

The letter has a `pdf.blade.php` for the PDF and a separate `word.php` for Word, built from the same data. The Word file has the same parts in the same order: the letterhead with a line under it, the reference and date block (with the same Hijri rule), the recipient, the subject, the justified paragraphs, the stamp and signature, and the copies. The footer becomes a line of small grey text with Word page numbers.

A Word file is useful when the letter needs a last edit before it is printed and signed by hand. Word files need `phpoffice/phpword`; see [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template letter --as=my-letter
```

This copies the template to `resources/doc-templates/my-letter/`:

- `template.php`: the fields and defaults, for example `'show_hijri' => false`;
- `pdf.blade.php`: the PDF layout and its CSS;
- `word.php`: the Word layout;
- `footer.html.php`: the contact footer;
- `lang/ar.php` and `lang/en.php`: the labels and the default greeting and closing.

Edit `pdf.blade.php` and `word.php` together, so both files stay alike. Use the copy with `Doc::template('my-letter', $data)`. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Contract](/templates/contract): for agreements that follow an offer letter.
- [Certificate](/templates/certificate): for course and appreciation certificates.
- [A template designed in Word](/recipes/word-designed-template): when your letterhead already exists as a Word file.
- [Branding per customer](/recipes/multi-tenant-branding): a different letterhead for each tenant.
- [Images](/guide/images) and [Arabic support](/guide/arabic): signature images and Hijri dates.
