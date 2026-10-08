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
}
