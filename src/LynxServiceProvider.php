<?php

declare(strict_types=1);

namespace Lynx\Scout;

use Illuminate\Support\ServiceProvider;

class LynxServiceProvider extends ServiceProvider
{
    /**
     * Register package services and bindings.
     */
    public function register(): void
    {
        $configPath = __DIR__ . '/../config/lynx.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'lynx');
        }
    }

    /**
     * Bootstrap package services and publication.
     */
    public function boot(): void
    {
        $configPath = __DIR__ . '/../config/lynx.php';
        if (file_exists($configPath) && $this->app->runningInConsole()) {
            $this->publishes([
                $configPath => config_path('lynx.php'),
            ], 'lynx-config');
        }
    }
}
