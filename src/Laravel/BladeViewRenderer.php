<?php

namespace BiztechEG\EasyPdfWord\Laravel;

use BiztechEG\EasyPdfWord\Contracts\ViewRenderer;
use Closure;
use Illuminate\Contracts\View\Factory;

/**
 * Laravel's view factory: Blade views and plain PHP views (.html.php)
 * alike, with the app's shared data and view composers.
 */
class BladeViewRenderer implements ViewRenderer
{
    /** @param  Closure(): Factory  $views  resolved per render */
    public function __construct(private Closure $views) {}

    public function file(string $path, array $data = []): string
    {
        return ($this->views)()->file($path, $data)->render();
    }

    public function view(string $name, array $data = []): string
    {
        return ($this->views)()->make($name, $data)->render();
    }
}
