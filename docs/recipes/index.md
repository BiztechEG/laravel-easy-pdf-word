# Recipes

Each recipe starts from a real business situation and gives a complete solution you can copy into a Laravel app: routes, controllers, Mailables, jobs and Eloquent models, with the reasons behind each choice.

## Invoices and billing {#billing}

- [Email an invoice](/recipes/email-invoice): send the customer their Arabic tax invoice as a PDF attachment when an order is paid, from a queued Mailable or a notification.
- [Saudi ZATCA invoice](/recipes/zatca-invoice): issue a simplified tax invoice with the ZATCA phase 1 QR code, 15% VAT in riyals, the Hijri date and Arabic digits.
- [Print an ETA e-invoice](/recipes/egypt-e-invoice): print the invoice, credit note or debit note you submitted to the Egyptian Tax Authority, with the portal QR code.
- [Download, preview or store](/recipes/controller-responses): send one document as a download, show it in the browser, or store it on S3 and return a temporary link.

## Bulk and reports {#bulk}

- [Monthly payslips in one ZIP](/recipes/payslips-zip): give HR every employee's payslip in one archive, and move the work to a queue when the company grows.
- [Course certificates in bulk](/recipes/certificates): issue a certificate to every participant, worded by gender, with a QR code that opens a verification page, and email each one.
- [Sales report from a query](/recipes/sales-report): turn an Eloquent query into a sales report in PDF or Word, including reports with thousands of rows.

## Custom documents {#custom}

- [A template designed in Word](/recipes/word-designed-template): let someone design the document in Microsoft Word with placeholders, and fill it from your app.
- [Branding per customer](/recipes/multi-tenant-branding): give each customer or tenant their own logo, colours and company details on the same templates.
- [Contracts from your data](/recipes/contract): generate contracts with their parties, numbered clauses and signatures from your records.
- [A price list built in code](/recipes/price-list-builder): build a document block by block with `Doc::make()` and get the same content as PDF and Word.

## Quality {#quality}

- [Testing document features](/recipes/testing-documents): check that your app makes, saves and sends the right documents, without rendering a single PDF in your tests.

New to the package? Start with the [quick start](/guide/quick-start), and browse the [templates](/templates/) to see what each one looks like.
