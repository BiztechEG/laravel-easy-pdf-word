<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\PdfDocument;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use InvalidArgumentException;
use ZipArchive;

class ZipTest extends TestCase
{
    public function test_files_keep_their_names_in_the_zip(): void
    {
        $invoice = Doc::template('invoice', Doc::templates()->get('invoice')->sample())->locale('ar');
        $zip = Doc::zip([$invoice->pdf('فاتورة-1024.pdf'), $invoice->word('فاتورة-1024')]);

        $entries = $this->entries($zip->content());

        $this->assertSame(['فاتورة-1024.pdf', 'فاتورة-1024.docx'], array_keys($entries));
        $this->assertStringStartsWith('%PDF', $entries['فاتورة-1024.pdf']);
        $this->assertStringStartsWith('PK', $entries['فاتورة-1024.docx']);
        $this->assertSame('documents.zip', $zip->filename());
    }

    public function test_keys_name_the_files_safely_and_uniquely(): void
    {
        $file = fn () => $this->file();
        $zip = Doc::zip([
            '../../etc/cron.d/x' => $file(),
            'C:\\Windows\\a.pdf' => $file(),
            'report' => $file(),
            'Report.pdf' => $file(),
            '..' => $file(),
            '' => $file(),
        ], 'تقارير');

        $this->assertSame(
            ['-..-etc-cron.d-x.pdf', 'C:-Windows-a.pdf', 'report.pdf', 'Report (2).pdf', 'document.pdf', 'document (2).pdf'],
            array_keys($this->entries($zip->content())),
        );
        $this->assertSame('تقارير.zip', $zip->filename());
    }

    public function test_download_response(): void
    {
        $response = Doc::zip([$this->file()])->download('invoices');

        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('invoices.zip', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_only_rendered_files_are_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Doc::zip(['a.pdf' => '%PDF raw bytes']);
    }

    public function test_an_empty_zip_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Doc::zip([]);
    }

    public function test_files_render_only_when_the_zip_is_built(): void
    {
        $rendered = false;
        $zip = Doc::zip([new PdfDocument(function () use (&$rendered) {
            $rendered = true;

            return ['%PDF-1.4', 'test'];
        }, 'lazy.pdf')]);

        $this->assertFalse($rendered);
        $zip->content();
        $this->assertTrue($rendered);
    }

    private function file(): PdfDocument
    {
        return new PdfDocument(fn () => ['%PDF-1.4 test', 'test'], 'file.pdf');
    }

    /** @return array<string, string> */
    private function entries(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $zip->open($path);
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();
        unlink($path);

        return $entries;
    }
}
