<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use RuntimeException;

/**
 * The temp_dir setting: Word files, .docx templates and Doc::zip() write
 * their temp files there instead of the system's temp folder.
 */
class TempDirTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/easy-pdf-word-laravel-temp-'.bin2hex(random_bytes(4));
    }

    public function test_word_files_and_zips_are_made_in_the_folder(): void
    {
        config(['easy-pdf-word.temp_dir' => $this->dir]);

        $word = Doc::make()->locale('ar')->paragraph('مرحبا')->word();
        $zip = Doc::zip(['عرض.docx' => $word])->content();

        $this->assertStringStartsWith('PK', $zip);
        $this->assertDirectoryExists($this->dir);
        $this->assertSame(['.', '..'], scandir($this->dir));
    }

    public function test_an_unusable_folder_is_reported_for_word_files(): void
    {
        $file = $this->dir.'-file';
        file_put_contents($file, '');
        config(['easy-pdf-word.temp_dir' => $file]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('temp_dir');

        Doc::make()->paragraph('x')->word()->content();
    }

    public function test_an_unusable_folder_is_reported_for_zips(): void
    {
        $word = Doc::make()->paragraph('x')->word();
        // Made before the setting changes, so only the archive meets the folder.
        $word->content();
        $file = $this->dir.'-file';
        file_put_contents($file, '');
        config(['easy-pdf-word.temp_dir' => $file]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('temp_dir');

        Doc::zip([$word])->content();
    }
}
