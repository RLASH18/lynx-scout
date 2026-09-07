<?php

declare(strict_types=1);

namespace Lynx\Scout;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Http\Middleware\LynxPerformanceMiddleware;

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

        $this->app->singleton(QueryCollector::class, function (): QueryCollector {
            return new QueryCollector();
        });

        $this->app->singleton(RequestCollector::class, function (): RequestCollector {
            return new RequestCollector();
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

        if ($this->isMonitoringAllowed()) {
            if (config('lynx.query.enabled', true)) {
                $this->app->make(QueryCollector::class)->start();
            }

            if (config('lynx.request.enabled', true)) {
                if ($this->app->bound(Kernel::class)) {
                    $kernel = $this->app->make(Kernel::class);
                    $kernel->prependMiddleware(LynxPerformanceMiddleware::class);
                }

                if ($this->app->bound('router')) {
                    $router = $this->app->make('router');
                    $router->aliasMiddleware('lynx.performance', LynxPerformanceMiddleware::class);
                    $router->pushMiddlewareToGroup('web', LynxPerformanceMiddleware::class);
                    $router->pushMiddlewareToGroup('api', LynxPerformanceMiddleware::class);
                }
            }
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
