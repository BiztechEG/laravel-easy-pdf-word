<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PdfDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MailTest extends TestCase
{
    public function test_a_pdf_is_a_mail_attachment(): void
    {
        $pdf = Doc::html('<h1>فاتورة</h1>')->locale('ar')->pdf('فاتورة-1024.pdf');
        $mail = new InvoiceMail($pdf);

        $mail->assertHasAttachedData($pdf->content(), 'فاتورة-1024.pdf', ['mime' => 'application/pdf']);
    }

    public function test_a_word_file_is_a_mail_attachment_named_after_the_template(): void
    {
        $word = Doc::template('letter', Doc::templates()->get('letter')->sample())->locale('ar')->word();
        $mail = new InvoiceMail($word);

        $this->assertSame('letter.docx', $word->filename());
        $mail->assertHasAttachedData($word->content(), 'letter.docx', [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_a_notification_can_attach_a_pdf(): void
    {
        $pdf = Doc::html('<p>receipt</p>')->pdf('receipt');
        $message = (new ReceiptNotification($pdf))->toMail(new \stdClass);

        $this->assertSame('receipt.pdf', $message->rawAttachments[0]['name']);
        $this->assertStringStartsWith('%PDF', $message->rawAttachments[0]['data']);
        $this->assertSame('application/pdf', $message->rawAttachments[0]['options']['mime']);
    }

    public function test_the_file_is_rendered_only_when_the_mail_is_built(): void
    {
        $rendered = 0;
        $pdf = new PdfDocument(function () use (&$rendered) {
            $rendered++;

            return ['%PDF-1.4', 'test'];
        }, 'lazy.pdf');

        $attachment = $pdf->toMailAttachment();
        $this->assertSame(0, $rendered);
        $this->assertSame('lazy.pdf', $attachment->as);

        (new InvoiceMail($pdf))->assertHasAttachedData('%PDF-1.4', 'lazy.pdf', ['mime' => 'application/pdf']);
        $pdf->content();
        $this->assertSame(1, $rendered);
    }
}

class InvoiceMail extends Mailable
{
    public function __construct(private object $file) {}

    public function content(): Content
    {
        return new Content(htmlString: '<p>Your invoice is attached.</p>');
    }

    public function attachments(): array
    {
        return [$this->file];
    }
}

class ReceiptNotification extends Notification
{
    use Queueable;

    public function __construct(private object $file) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('Your receipt is attached.')->attach($this->file);
    }
}
