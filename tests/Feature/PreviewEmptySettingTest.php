<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Tests\TestCase;

class PreviewEmptySettingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // DOC_PREVIEW= in .env: the same as leaving it out.
        $app['env'] = 'local';
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('easy-pdf-word.preview.enabled', '');
    }

    public function test_an_empty_setting_keeps_the_preview_page_on_in_local(): void
    {
        $this->get('/doc-preview')->assertOk();
    }
}
