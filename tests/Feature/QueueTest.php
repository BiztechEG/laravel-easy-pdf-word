<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Exceptions\WordNotSupported;
use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\WithMigration;
use ZipArchive;

class QueueTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // As in a Laravel app's phpunit.xml: jobs run as soon as they are dispatched.
        $app['config']->set('queue.default', 'sync');
        // The job is encrypted, and every Laravel app has a key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    private function invoice(): array
    {
        return Doc::templates()->get('invoice')->sample();
    }

    public function test_the_worker_saves_a_pdf_to_the_disk(): void
    {
        Storage::fake('s3');

        Doc::template('invoice', $this->invoice())->locale('ar')->queue('invoices/1024.pdf', 's3');

        Storage::disk('s3')->assertExists('invoices/1024.pdf');
        $this->assertStringStartsWith('%PDF', Storage::disk('s3')->get('invoices/1024.pdf'));
    }

    public function test_the_worker_saves_a_word_file_built_in_code(): void
    {
        Storage::fake('local');

        Doc::make()->heading('تقرير المبيعات')->table([['الفرع', 'المبيعات'], ['القاهرة', '1,000']])->locale('ar')->queue('reports/sales.docx');

        $path = Storage::disk('local')->path('reports/sales.docx');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('تقرير المبيعات', $xml);
        $this->assertStringContainsString('القاهرة', $xml);
    }

    #[WithMigration('queue')]
    public function test_a_worker_renders_the_encrypted_job(): void
    {
        config([
            'database.connections.queue_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'queue_test',
            'queue.default' => 'database',
        ]);
        $this->artisan('migrate')->run();
        Storage::fake('local');

        Doc::template('invoice', $this->invoice())->locale('ar')->queue('invoices/1024.pdf');

        Storage::disk('local')->assertMissing('invoices/1024.pdf');
        $command = json_decode(DB::table('jobs')->value('payload'), true)['data']['command'];
        $this->assertStringNotContainsString('INV-2026-1024', $command);
        $this->assertStringContainsString('INV-2026-1024', Crypt::decryptString($command));

        $this->artisan('queue:work', ['--once' => true])->assertExitCode(0);

        Storage::disk('local')->assertExists('invoices/1024.pdf');
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_the_job_is_pushed_with_its_queue_options(): void
    {
        Queue::fake()->serializeAndRestore();

        Doc::template('invoice', $this->invoice())->queue('invoices/1024.pdf', 's3')->onQueue('documents')->delay(60);

        Queue::assertPushedOn('documents', SaveDocument::class, fn (SaveDocument $job) => $job->path === 'invoices/1024.pdf'
            && $job->disk === 's3'
            && $job->format === 'pdf'
            && $job->document['template'] === 'invoice'
            && $job->delay === 60);
    }

    public function test_settings_reach_the_worker(): void
    {
        Doc::fake();

        Doc::template('receipt', Doc::templates()->get('receipt')->sample())
            ->locale('ar')
            ->numerals('arabic')
            ->driver('chromium')
            ->with('number', 'R-77')
            ->queue('receipts/77.pdf');

        Doc::assertSaved('receipts/77.pdf');
        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->template === 'receipt'
            && $doc->locale === 'ar'
            && $doc->direction === 'rtl'
            && $doc->numerals === 'arabic'
            && $doc->driver === 'chromium'
            && $doc->filename() === '77.pdf'
            && $doc->data('number') === 'R-77');
    }

    public function test_html_and_blocks_reach_the_worker(): void
    {
        Doc::fake();

        Doc::html('<p>مرحبا بالعالم</p>')->locale('ar')->queue('notes/hello.pdf');
        Doc::make()->paragraph('سطر من التقرير')->queue('notes/report.docx');

        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->isPdf() && $doc->contains('مرحبا بالعالم'));
        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->isWord() && $doc->filename() === 'report.docx');
    }

    public function test_changes_after_queueing_do_not_reach_the_job(): void
    {
        Queue::fake();

        $document = Doc::make()->heading('قبل')->locale('ar');
        $pending = $document->queue('a.docx');
        $document->locale('en')->heading('بعد');
        unset($pending);

        Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->document['settings']['locale'] === 'ar'
            && count($job->document['builder']->blocks()) === 1);
    }

    public function test_invalid_data_fails_before_anything_is_queued(): void
    {
        Queue::fake();

        try {
            Doc::template('invoice', ['items' => 'nope'])->queue('invoices/bad.pdf');
            $this->fail('The data should not pass validation.');
        } catch (ValidationException) {
        }

        try {
            Doc::html('<p>x</p>')->queue('x.docx');
            $this->fail('HTML documents have no Word output.');
        } catch (WordNotSupported) {
        }

        Queue::assertNothingPushed();
    }

    public function test_the_extension_picks_the_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('.pdf" or ".docx');

        Doc::html('<p>x</p>')->queue('exports/file.txt');
    }
}
