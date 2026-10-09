# Contract

A contract (عقد) between two or more parties, such as a services, supply, rental or employment agreement that your legal, sales or HR team prepares. The `contract` template numbers the clauses in Arabic words (البند الأول، البند الثاني ...) and adds signature boxes for every party, witnesses and initials on every page.

<div class="preview">
  <figure><a href="/samples/contract-ar.pdf" target="_blank"><img src="/previews/contract-ar.png" alt="First page of an Arabic contract: the title, the opening sentence with the date and place, the two parties, the preamble and the clauses البند الأول to البند الرابع"></a><figcaption>Arabic (PDF)</figcaption></figure>
  <figure><a href="/samples/contract-en.pdf" target="_blank"><img src="/previews/contract-en.png" alt="The same contract with English labels"></a><figcaption>English (PDF)</figcaption></figure>
</div>

## When to use it {#when-to-use}

- A client accepts your price quotation and you send a services or software development contract to sign.
- Supply or maintenance agreements with the same clauses for many customers, filled from your database.
- Office, shop or equipment rental contracts with the rent, the term and the deposit in the clauses.
- Employment contracts generated from the HR record of each new hire.
- Agreements with a third party, such as a guarantor bank or a subcontractor.

## Quick example {#example}

Pass the contract details, the parties and the clauses:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

$contract = Doc::template('contract', [
    'contract' => [
        'title' => 'عقد تقديم خدمات برمجية',
        'number' => 'C-2026-031',
        'date' => '2026-10-08',
        'place' => 'القاهرة',
    ],
    'parties' => [
        [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'alias' => 'مقدم الخدمة',
            'id_label' => 'سجل تجاري رقم',
            'id' => '123456',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'represented_by' => 'عمرو محمد',
            'capacity' => 'المدير التنفيذي',
        ],
        [
            'name' => 'مؤسسة النور للتجارة',
            'alias' => 'العميل',
            'id_label' => 'سجل تجاري رقم',
            'id' => '654321',
            'address' => 'مدينة نصر، القاهرة',
            'represented_by' => 'أحمد عبد الرحمن',
            'capacity' => 'المدير العام',
        ],
    ],
    'preamble' => 'يعمل الطرف الأول في مجال تطوير البرمجيات، ويرغب الطرف الثاني في تطوير نظام لإدارة المخزون والمبيعات، وقد اطلع على عرض السعر رقم QT-2026-0088 وقبله.',
    'clauses' => [
        ['title' => 'التمهيد', 'text' => 'يعتبر التمهيد السابق وعرض السعر المشار إليه جزءاً لا يتجزأ من هذا العقد.'],
        ['title' => 'موضوع العقد', 'text' => "يلتزم الطرف الأول بتطوير نظام لإدارة المخزون والمبيعات يشمل:\n1. إدارة الأصناف والمخازن.\n2. فواتير المبيعات والمشتريات.\n3. التقارير الدورية باللغتين العربية والإنجليزية."],
        ['title' => 'مدة التنفيذ', 'text' => 'مدة التنفيذ خمسة وأربعون يوم عمل من تاريخ استلام الدفعة الأولى.'],
        ['title' => 'قيمة العقد وطريقة السداد', 'text' => "قيمة العقد 71,250 جنيهاً مصرياً شاملة ضريبة القيمة المضافة، تُسدد على النحو التالي:\nالدفعة الأولى 40% عند توقيع العقد.\nالدفعة الثانية 40% عند التسليم الابتدائي.\nالدفعة الأخيرة 20% بعد شهر من التشغيل الفعلي."],
        ['title' => 'السرية', 'text' => 'يلتزم كل طرف بالمحافظة على سرية المعلومات التي يطلع عليها بسبب هذا العقد.'],
        ['title' => 'فض النزاعات', 'text' => 'يخضع هذا العقد لأحكام القانون المصري، وتختص محاكم القاهرة بنظر أي نزاع ينشأ عنه.'],
    ],
    'witnesses' => ['محمود علي', 'منى سامي'],
])->locale('ar');
```

Then return the file you need. The Word file is handy when the other party's lawyer wants to review the text:

::: code-group

```php [PDF]
return $contract->pdf()->download('C-2026-031.pdf');
```

```php [Word]
return $contract->word()->download('C-2026-031.docx');
```

:::

You get a two-page A4 contract:

- the title and `رقم العقد: C-2026-031`, then the opening sentence, with the weekday worked out from the date: إنه في يوم الخميس الموافق 2026/10/08 تحرر هذا العقد في القاهرة بين كل من:
- a table of the parties: الطرف الأول (مقدم الخدمة) and الطرف الثاني (العميل), each with the registration number, the address and الممثل القانوني: عمرو محمد (المدير التنفيذي);
- the preamble under تمهيد, then وبعد أن أقر الطرفان بأهليتهما القانونية للتعاقد، اتفقا على ما يلي:
- the clauses البند الأول: التمهيد to البند السادس: فض النزاعات, each line of a clause as its own paragraph;
- the closing sentence حُرر هذا العقد من نسختين، بيد كل طرف نسخة للعمل بموجبها عند اللزوم.
- a signature box for each party and one for each witness (الشاهد الأول، الشاهد الثاني);
- on every page, a footer with initials boxes (توقيع الطرف الأول: ....) and the contract title, number and page number.

## Fields {#fields}

The data is validated before anything is drawn. Fields with a default can be left out.

| Field | Required | Type or values | Default | What it does |
| --- | --- | --- | --- | --- |
| `contract.title` | Yes | text | | The title at the top, also printed in the footer. |
| `contract.number` | No | text | | The contract number, under the title and in the footer. |
| `contract.date` | Yes | date | | The date in the opening sentence, with its weekday (الخميس / Thursday). |
| `contract.place` | No | text | | Where the contract is made. Without it the opening sentence leaves out the place. |
| `parties` | Yes | list, at least 2 | | The parties, in order: the first is الطرف الأول, the second الطرف الثاني ... |
| `parties.*.name` | Yes | text | | The party's name, in bold. |
| `parties.*.alias` | No | text | | How the contract refers to the party, such as مقدم الخدمة or المؤجر. Printed in brackets. |
| `parties.*.id_label` | No | text | | The label before `id`, such as سجل تجاري رقم or رقم قومي. |
| `parties.*.id` | No | text | | The registration or ID number, kept left to right. |
| `parties.*.address` | No | text | | The party's address. |
| `parties.*.represented_by` | No | text | | Who signs for the party (الممثل القانوني / Represented by). |
| `parties.*.capacity` | No | text | | The signer's position, printed in brackets after the name. |
| `preamble` | No | text | | The preamble (تمهيد). Each line becomes a paragraph. |
| `clauses` | Yes | list, at least 1 | | The clauses, numbered in order. |
| `clauses.*.title` | No | text | | The clause title, after the number: البند الأول: موضوع العقد. |
| `clauses.*.text` | Yes | text | | The clause text. Each line becomes a paragraph. |
| `copies` | No | whole number, 1 or more | `2` | The number of copies in the closing sentence. |
| `closing` | No | text | | Replaces the closing sentence about the copies. |
| `witnesses` | No | list of names | empty | One signature box per witness. |

### Theme values {#theme}

| Theme key | Where it shows |
| --- | --- |
| `logo` | Centred above the title, 30 mm wide. |
| `primary` | The title, the line under it, the clause headings and the party labels. |
| `muted` | The contract number, the aliases and the signature lines. |
| `border` | The lines of the parties table. |

The contract does not print the company name from the theme: every party, your company included, comes from `parties`.

## Variants and options {#options}

### Parties {#parties}

Each party gets a row in the parties table and a signature box at the end. The label comes from its position, in words up to the twentieth party (الطرف الأول، الطرف الثاني ... الطرف العشرون in Arabic, First party ... Twentieth party in English), and `alias` adds the name the clauses use, such as (العميل). `id_label` and `id` print one line, for example سجل تجاري رقم 123456 or رقم قومي 29501011234567; give `id` alone to print just the number.

### Three or more parties {#more-parties}

Add a third array to `parties`, for example a guarantor:

```php
$data['parties'][] = [
    'name' => 'بنك القاهرة',
    'alias' => 'الضامن',
    'id_label' => 'سجل تجاري رقم',
    'id' => '778899',
];
```

With three or more parties the template changes the agreement sentence to the plural, وبعد أن أقرت الأطراف بأهليتها القانونية للتعاقد، اتفقت على ما يلي, prints the signature boxes three to a row, and adds an initials box for الطرف الثالث in the footer. The footer has room for the initials of the first three parties.

### Clause numbering {#clauses}

Clauses are numbered by their position in the list, whatever their array keys:

- in Arabic, with ordinal words from البند الأول to البند العشرون; from the 21st clause on, with digits: البند 21;
- in English, with digits: Clause 1, Clause 2.

Each line of a clause's `text` becomes its own paragraph, so a list is written with line breaks (`\n`) and your own numbers, as in the موضوع العقد clause above. A clause without `title` shows only its number.

### Copies and closing {#copies}

The closing sentence names the number of copies in words: نسخة واحدة، نسختين، ثلاث نسخ ... up to عشر نسخ in Arabic and up to five copies in English; past that it uses digits, for example حُرر هذا العقد من 12 نسخة. To write your own sentence, pass `closing`:

```php
$data['closing'] = 'حُرر هذا العقد من ثلاث نسخ أصلية، تسلم كل طرف نسخة منها، وأودعت الثالثة لدى الضامن.';
```

### Witnesses {#witnesses}

`witnesses` is a list of names. Each one gets a box with its label (الشاهد الأول، الشاهد الثاني ... / Witness 1, Witness 2 ...), the name and a signature line, three to a row under the heading الشهود.

### Initials on every page {#initials}

The footer repeats on every page: a box for each of the first three parties to initial the page (توقيع الطرف الأول: ....... / First party initials), then the contract title and number, and صفحة 1 من 2. This is why the template's bottom margin is larger (28 mm).

## Word file {#word}

The contract has a single `layout.php` that builds both files, so the Word file has the same headings, parties table, clauses, signature boxes and witnesses as the PDF. The footer becomes one line of small grey text in the Word footer: the initials, the title and number, and Word page numbers.

Word files need `phpoffice/phpword`. See [Word files](/guide/word).

## Customise it {#customise}

```bash
php artisan doc:template contract --as=my-contract
```

This copies the template to `resources/doc-templates/my-contract/`:

- `template.php`: the fields and defaults, for example `'copies' => 3`;
- `layout.php`: the layout of both the PDF and the Word file;
- `footer.blade.php`: the initials and page number footer;
- `lang/ar.php` and `lang/en.php`: the opening and closing sentences, the ordinal words and every label. For a rental contract you might change `intro` to start with your own wording.

Use the copy with `Doc::template('my-contract', $data)`. See [Your own templates](/guide/custom-templates).

## Related {#related}

- [Contracts from your data](/recipes/contract): build the parties and clauses from your models.
- [Price quotation](/templates/quotation): the offer that usually comes before the contract.
- [Official letter](/templates/letter): for cover letters and notices about a contract.
- [A template designed in Word](/recipes/word-designed-template): when your lawyers deliver the contract as a Word file.
- [Word files](/guide/word): fonts and right-to-left text in Word.
