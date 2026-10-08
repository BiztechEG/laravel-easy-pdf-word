<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Tests\TestCase;

class PreviewDisabledTest extends TestCase
{
    public function test_the_preview_page_is_off_outside_local(): void
    {
        $this->get('/doc-preview')->assertNotFound();
    }
}
