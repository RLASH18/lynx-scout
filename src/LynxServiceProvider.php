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

        $this->app->singleton(\Lynx\Scout\Collectors\QueryCollector::class, function (): \Lynx\Scout\Collectors\QueryCollector {
            return new \Lynx\Scout\Collectors\QueryCollector();
        });
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

        if ($this->isMonitoringAllowed() && config('lynx.query.enabled', true)) {
            $this->app->make(\Lynx\Scout\Collectors\QueryCollector::class)->start();
        }
    }

    /**
     * Determine whether Lynx Scout is permitted to run in current environment.
     */
    protected function isMonitoringAllowed(): bool
    {
        if (! config('lynx.enabled', true)) {
            return false;
        }

        $allowedEnvironments = config('lynx.environments', []);
        if (! empty($allowedEnvironments) && ! in_array($this->app->environment(), (array) $allowedEnvironments, true)) {
            return false;
        }

        return true;
    }
}
