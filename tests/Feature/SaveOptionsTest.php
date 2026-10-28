<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Jobs\SaveDocument;
use BiztechEG\EasyPdfWord\Testing\GeneratedDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * save() and queue() pass their options to Storage::put(), such as the
 * file's visibility.
 */
class SaveOptionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    public function test_save_passes_the_visibility_to_the_disk(): void
    {
        Storage::fake('s3');

        Doc::make()->paragraph('عرض سعر')->locale('ar')->word()->save('offers/1.docx', 's3', ['visibility' => 'private']);
        Doc::make()->paragraph('عرض سعر')->locale('ar')->word()->save('offers/2.docx', 's3', ['visibility' => 'public']);

        $this->assertSame('private', Storage::disk('s3')->getVisibility('offers/1.docx'));
        $this->assertSame('public', Storage::disk('s3')->getVisibility('offers/2.docx'));
    }

    public function test_options_are_ignored_for_an_absolute_local_path(): void
    {
        $path = sys_get_temp_dir().'/easy-pdf-word-tests/save-options.docx';
        @unlink($path);

        Doc::make()->paragraph('x')->word()->save($path, null, ['visibility' => 'private']);

        $this->assertFileExists($path);
    }

    public function test_queue_carries_the_options_to_the_job(): void
    {
        Queue::fake();

        Doc::make()->paragraph('x')->queue('offers/1.pdf', 's3', ['visibility' => 'private']);

        Queue::assertPushed(SaveDocument::class, fn (SaveDocument $job) => $job->options === ['visibility' => 'private'] && $job->disk === 's3');
    }

    public function test_the_worker_saves_with_the_options(): void
    {
        Storage::fake('s3');

        Doc::make()->paragraph('عرض سعر')->locale('ar')->queue('offers/1.docx', 's3', ['visibility' => 'private']);

        $this->assertSame('private', Storage::disk('s3')->getVisibility('offers/1.docx'));
    }

    public function test_the_fake_records_the_options(): void
    {
        Doc::fake();

        Doc::make()->paragraph('x')->pdf()->save('a.pdf', 's3', ['visibility' => 'private']);
        Doc::make()->paragraph('y')->queue('b.pdf');

        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->saves() === [['path' => 'a.pdf', 'disk' => 's3', 'options' => ['visibility' => 'private']]]);
        Doc::assertSaved(fn (GeneratedDocument $doc) => $doc->saves() === [['path' => 'b.pdf', 'disk' => null, 'options' => []]]);
    }
}
