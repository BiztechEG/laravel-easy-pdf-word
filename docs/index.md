---
layout: home

hero:
  name: Laravel Easy PDF & Word
  text: Documents in any language, Arabic done right
  tagline: Generate PDF and Word files from Laravel with ready-made templates for invoices, quotations, payslips, contracts and more. Right-to-left layout, joined Arabic letters, Hijri dates and amounts in words work out of the box.
  image:
    src: /previews/invoice-ar.png
    alt: An Arabic tax invoice made by the package
  actions:
    - theme: brand
      text: Get started
      link: /guide/quick-start
    - theme: alt
      text: Browse templates
      link: /templates/
    - theme: alt
      text: Recipes
      link: /recipes/

features:
  - icon: 🔤
    title: Arabic that renders correctly
    details: Joined letters, right-to-left pages and tables, mixed Arabic and English, Arabic or Latin digits, Hijri dates and amounts in words (تفقيط).
  - icon: 📄
    title: PDF and Word from the same data
    details: Every template makes a PDF and a Word file. Build a document in code once and get both, or fill a .docx you designed in Word.
  - icon: 🧾
    title: Twelve ready-made templates
    details: Tax invoice with ZATCA QR, Egyptian e-invoice, credit note, quotation, purchase order, delivery note, receipt, payslip, contract, certificate, letter and report.
  - icon: ⚙️
    title: Two PDF engines
    details: mPDF runs anywhere PHP runs, even on shared hosting. Chromium (Browsershot or Gotenberg) gives full modern CSS. Switch per document, with automatic fallback.
  - icon: 📬
    title: Download, mail, zip or queue
    details: Return a file from a controller, attach it to a mail, bundle several in a ZIP, or let a queue worker render and store it on S3.
  - icon: ✅
    title: Built for real apps
    details: Template data is validated, user input is escaped, image paths are restricted, and Doc::fake() keeps your tests fast.
---

## Three lines to an Arabic invoice

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

return Doc::template('invoice', $data)
    ->locale('ar')
    ->pdf()
    ->download('فاتورة-1024.pdf');
```

The same call with `->word()` gives a Word file, and `->locale('en')` an English one. See the [quick start](/guide/quick-start) for the full example, or the [template gallery](/templates/) for what each template looks like.
