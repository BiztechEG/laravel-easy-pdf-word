<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Testing\DocFake;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;

class FakeTest extends TestCase
{
    private function invoice(): array
    {
        return Doc::templates()->get('invoice')->sample();
    }

    public function test_fake_records_documents_without_rendering(): void
    {
        $fake = Doc::fake();
        Doc::extend('mpdf', fn () => throw new \RuntimeException('The engine must not run.'));
        app(PdfManager::class)->forgetDrivers();

        $pdf = Doc::template('invoice', $this->invoice())->locale('ar')->numerals('arabic')->pdf('فاتورة.pdf');

        $this->assertInstanceOf(DocFake::class, $fake);
        $this->assertTrue(Doc::isFake());
        $this->assertStringStartsWith('%PDF', $pdf->content());
        $this->assertSame('fake', $pdf->engine());

        Doc::assertGeneratedCount(1);
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->isPdf()
            && $doc->template === 'invoice'
            && $doc->locale === 'ar'
            && $doc->direction === 'rtl'
            && $doc->numerals === 'arabic'
            && $doc->filename() === 'فاتورة.pdf');
        Doc::assertNotGenerated(fn (GeneratedDocument $doc) => $doc->isWord());
    }

    public function test_template_data_is_prepared_and_checked(): void
    {
        Doc::fake();

        Doc::template('invoice', $this->invoice())->pdf()->content();

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->data('invoice.number') === 'INV-2026-1024'
            && $doc->data('totals.total') > 0);

        $this->expectException(ValidationException::class);
        Doc::template('invoice', ['items' => 'nope'])->pdf()->content();
    }

    public function test_html_is_built_for_text_assertions(): void
    {
        Doc::fake();

        Doc::template('invoice', $this->invoice())->locale('ar')->pdf();

        $doc = Doc::generated()[0];
        $this->assertTrue($doc->contains('فاتورة ضريبية'));
        $this->assertFalse($doc->contains('<script>'));
        $this->assertStringContainsString('dir="rtl"', $doc->html());
    }

    public function test_saves_are_recorded_and_not_written(): void
    {
        Storage::fake('s3');
        Doc::fake();

        Doc::template('receipt', Doc::templates()->get('receipt')->sample())->pdf()->save('receipts/7.pdf', disk: 's3');
        Doc::make()->heading('تقرير')->locale('ar')->word()->save(sys_get_temp_dir().'/easy-pdf-word-tests/never-written.docx');

        Storage::disk('s3')->assertMissing('receipts/7.pdf');
        $this->assertFileDoesNotExist(sys_get_temp_dir().'/easy-pdf-word-tests/never-written.docx');

        Doc::assertSaved('receipts/7.pdf', disk: 's3');
        Doc::assertSaved('receipts/7.pdf');
        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->isWord());

        $this->expectException(AssertionFailedError::class);
        Doc::assertSaved('receipts/7.pdf', disk: 'local');
    }

    public function test_downloads_and_streams_are_recorded(): void
    {
        Doc::fake();
        Route::get('/invoice', fn () => Doc::html('<p>x</p>')->pdf('inline.pdf'));

        $response = Doc::template('letter', Doc::templates()->get('letter')->sample())->word()->download('خطاب');
        $this->get('/invoice')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        Doc::assertDownloaded('خطاب.docx');
        Doc::assertDownloaded(fn (GeneratedDocument $doc) => $doc->template === 'letter');
        Doc::assertStreamed('inline.pdf');

        $this->expectException(AssertionFailedError::class);
        Doc::assertDownloaded('inline.pdf');
    }

    public function test_nothing_generated(): void
    {
        Doc::fake();

        Doc::assertNothingGenerated();
        Doc::template('invoice', $this->invoice());
        Doc::assertNothingGenerated();

        $this->expectException(AssertionFailedError::class);
        Doc::assertGenerated();
    }

    public function test_watermark_and_password_are_recorded(): void
    {
        Doc::fake();

        Doc::make()->paragraph('عرض')->locale('ar')->watermark('مسودة')->password('1234')->pdf();
        Doc::make()->paragraph('عرض')->locale('ar')->watermark('مسودة')->word();

        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->isPdf() && $doc->watermark === 'مسودة' && $doc->protected);
        Doc::assertGenerated(fn (GeneratedDocument $doc) => $doc->isWord() && $doc->watermark === null && ! $doc->protected);
    }

    public function test_word_files_have_no_html(): void
    {
        Doc::fake();

        $word = Doc::make()->paragraph('نص')->locale('ar')->word();

        $this->assertStringStartsWith('PK', $word->content());
        $this->expectException(\LogicException::class);
        Doc::generated()[0]->html();
    }
}
