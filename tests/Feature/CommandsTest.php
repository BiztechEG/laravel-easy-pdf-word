<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Facades\Doc;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

class CommandsTest extends TestCase
{
    private string $projectTemplates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectTemplates = config('easy-pdf-word.templates.paths')[0];
        (new Filesystem)->deleteDirectory($this->projectTemplates);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->projectTemplates);

        parent::tearDown();
    }

    public function test_list_templates(): void
    {
        $this->artisan('doc:templates')
            ->expectsOutputToContain('invoice')
            ->expectsOutputToContain('Formats')
            ->expectsOutputToContain('package')
            ->assertSuccessful();
    }

    public function test_copy_a_template_under_a_new_name(): void
    {
        $this->artisan('doc:template', ['name' => 'invoice', '--as' => 'my-invoice'])->assertSuccessful();

        $this->assertFileExists($this->projectTemplates.'/my-invoice/pdf.blade.php');
        $this->assertFileExists($this->projectTemplates.'/my-invoice/word.php');
        $this->assertFalse(Doc::templates()->isBundled('my-invoice'));

        $html = Doc::template('my-invoice', Doc::templates()->get('invoice')->sample())->locale('ar')->toHtml();
        $this->assertStringContainsString('فاتورة ضريبية', $html);
    }

    public function test_a_project_copy_overrides_the_original(): void
    {
        $this->artisan('doc:template', ['name' => 'letter'])->assertSuccessful();
        file_put_contents($this->projectTemplates.'/letter/lang/ar.php', "<?php return ['subject' => 'بخصوص'];");

        $html = Doc::template('letter', Doc::templates()->get('letter')->sample())->locale('ar')->toHtml();

        $this->assertStringContainsString('بخصوص:', $html);
    }

    public function test_make_a_new_template(): void
    {
        $this->artisan('doc:make-template', ['name' => 'delivery-note'])->assertSuccessful();

        $pdf = Doc::template('delivery-note', ['title' => 'إذن تسليم'])->locale('ar')->pdf();

        $this->assertStringStartsWith('%PDF', $pdf->content());
        $this->assertStringStartsWith('PK', Doc::template('delivery-note', ['title' => 'إذن تسليم'])->locale('ar')->word()->content());
        $this->artisan('doc:make-template', ['name' => 'delivery-note'])->assertFailed();
    }

    public function test_template_names_cannot_leave_the_templates_folder(): void
    {
        $this->artisan('doc:template', ['name' => 'invoice', '--as' => '../escaped'])->assertFailed();
        $this->artisan('doc:make-template', ['name' => '..'])->assertFailed();
        $this->assertFalse(Doc::templates()->exists('..'));
    }

    public function test_render_a_sample_file(): void
    {
        $dir = sys_get_temp_dir().'/easy-pdf-word-tests/samples';

        foreach (['pdf' => '%PDF', 'docx' => 'PK', 'html' => '<!DOCTYPE'] as $format => $start) {
            $this->artisan('doc:sample', ['name' => 'receipt', '--format' => $format, '--output' => "{$dir}/receipt.{$format}"])
                ->assertSuccessful();

            $this->assertStringStartsWith($start, file_get_contents("{$dir}/receipt.{$format}"));
        }

        $this->artisan('doc:sample', ['name' => 'nope'])->assertFailed();
        $this->artisan('doc:sample', ['name' => 'receipt', '--format' => 'xls'])->assertFailed();
        $this->artisan('doc:sample', ['name' => 'receipt', '--output' => "{$dir}/receipt.xls"])->assertSuccessful();
        $this->assertStringStartsWith('%PDF', file_get_contents("{$dir}/receipt.xls"));
        $this->artisan('doc:sample', ['name' => 'receipt', '--locale' => '../x'])->assertFailed();
    }

    public function test_the_sample_format_follows_the_output_extension(): void
    {
        $dir = sys_get_temp_dir().'/easy-pdf-word-tests/samples';

        foreach (['pdf' => '%PDF', 'docx' => 'PK', 'html' => '<!DOCTYPE'] as $format => $start) {
            $this->artisan('doc:sample', ['name' => 'receipt', '--output' => "{$dir}/by-extension.{$format}"])->assertSuccessful();
            $this->assertStringStartsWith($start, file_get_contents("{$dir}/by-extension.{$format}"));
        }

        // An explicit --format wins over the extension.
        $this->artisan('doc:sample', ['name' => 'receipt', '--format' => 'html', '--output' => "{$dir}/forced.pdf"])->assertSuccessful();
        $this->assertStringStartsWith('<!DOCTYPE', file_get_contents("{$dir}/forced.pdf"));
    }

    public function test_sample_output_paths_are_relative_to_the_current_folder(): void
    {
        $dir = sys_get_temp_dir().'/easy-pdf-word-tests/cwd';
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        mkdir($dir, 0775, true);
        $cwd = getcwd();
        chdir($dir);

        try {
            foreach (['pdf', 'html'] as $format) {
                $this->artisan('doc:sample', ['name' => 'receipt', '--format' => $format, '--output' => "out/receipt.{$format}"])
                    ->expectsOutputToContain("{$dir}/out/receipt.{$format}")
                    ->assertSuccessful();

                $this->assertFileExists("{$dir}/out/receipt.{$format}");
            }
        } finally {
            chdir($cwd);
        }
    }
}
