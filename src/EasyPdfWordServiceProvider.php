<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Arabic\Tafqeet;
use BiztechEG\EasyPdfWord\Console\CopyTemplateCommand;
use BiztechEG\EasyPdfWord\Console\ListTemplatesCommand;
use BiztechEG\EasyPdfWord\Console\MakeTemplateCommand;
use BiztechEG\EasyPdfWord\Console\SampleCommand;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EasyPdfWordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/easy-pdf-word.php', 'easy-pdf-word');

        $this->app->singleton(FontRegistry::class, fn ($app) => new FontRegistry(
            (array) $app['config']->get('easy-pdf-word.fonts.custom', [])
        ));

        $this->app->singleton(TemplateRegistry::class, fn ($app) => new TemplateRegistry(
            (array) $app['config']->get('easy-pdf-word.templates.paths', [])
        ));

        $this->app->singleton(PdfManager::class, fn ($app) => new PdfManager($app));

        $this->app->singleton(DocFactory::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'easy-pdf-word');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'doc');

        foreach ((array) config('easy-pdf-word.currencies', []) as $code => $definition) {
            Tafqeet::registerCurrency($code, $definition);
        }

        Blade::directive('tafqeet', fn ($expression) => "<?php echo e(\\BiztechEG\\EasyPdfWord\\Arabic\\Arabic::tafqeet({$expression})); ?>");
        Blade::directive('hijri', fn ($expression) => "<?php echo e(\\BiztechEG\\EasyPdfWord\\Arabic\\Arabic::hijri({$expression})); ?>");

        if ($this->previewEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/preview.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/easy-pdf-word.php' => config_path('easy-pdf-word.php'),
            ], 'easy-pdf-word-config');

            $this->commands([
                ListTemplatesCommand::class,
                CopyTemplateCommand::class,
                MakeTemplateCommand::class,
                SampleCommand::class,
            ]);
        }
    }

    private function previewEnabled(): bool
    {
        $enabled = config('easy-pdf-word.preview.enabled');

        // Unset or empty (DOC_PREVIEW=): only in the local environment.
        return $enabled === null || $enabled === '' ? $this->app->environment('local') : filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }
}
