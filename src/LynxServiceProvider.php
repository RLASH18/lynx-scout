<?php

declare(strict_types=1);

namespace Lynx\Scout;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Lynx\Scout\Analyzers\ApplicationHealthAnalyzer;
use Lynx\Scout\Analyzers\PerformanceCorrelator;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\QueueCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Commands\CompareCommand;
use Lynx\Scout\Commands\FindingsCommand;
use Lynx\Scout\Commands\ReportCommand;
use Lynx\Scout\Commands\ScanCommand;
use Lynx\Scout\Commands\SnapshotCommand;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Detectors\CacheCandidateDetector;
use Lynx\Scout\Detectors\DuplicateQueryDetector;
use Lynx\Scout\Detectors\NPlusOneDetector;
use Lynx\Scout\Detectors\QueuePerformanceDetector;
use Lynx\Scout\Detectors\SlowQueryDetector;
use Lynx\Scout\Detectors\SlowRequestDetector;
use Lynx\Scout\Http\Middleware\LynxPerformanceMiddleware;
use Lynx\Scout\Recommendations\RecommendationEngine;
use Lynx\Scout\Repositories\FileFindingRepository;
use Lynx\Scout\Repositories\MemoryFindingRepository;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Scoring\ImpactScorer;
use Lynx\Scout\Services\LynxScanner;

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

        $this->app->singleton(QueueCollector::class, function (): QueueCollector {
            return new QueueCollector();
        });

        $this->app->singleton(FindingRepositoryContract::class, function (): FindingRepositoryContract {
            $driver = config('lynx.storage.driver', 'file');
            if ($driver === 'memory') {
                return new MemoryFindingRepository();
            }

            return new FileFindingRepository(
                storagePath: (string) config('lynx.storage.path', storage_path('lynx')),
                maxFindings: (int) config('lynx.storage.max_findings', 500)
            );
        });

        $this->app->singleton(SnapshotRepository::class, function (): SnapshotRepository {
            return new SnapshotRepository(
                storagePath: (string) config('lynx.storage.path', storage_path('lynx'))
            );
        });

        $this->app->tag([
            SlowQueryDetector::class,
            DuplicateQueryDetector::class,
            NPlusOneDetector::class,
            CacheCandidateDetector::class,
        ], 'lynx.detectors.query');

        $this->app->tag([
            SlowRequestDetector::class,
        ], 'lynx.detectors.request');

        $this->app->tag([
            QueuePerformanceDetector::class,
        ], 'lynx.detectors.queue');

        $this->app->singleton(LynxScanner::class, function ($app): LynxScanner {
            return new LynxScanner(
                queryCollector: $app->make(QueryCollector::class),
                requestCollector: $app->make(RequestCollector::class),
                queueCollector: $app->make(QueueCollector::class),
                healthAnalyzer: $app->make(ApplicationHealthAnalyzer::class),
                correlator: $app->make(PerformanceCorrelator::class),
                scorer: $app->make(ImpactScorer::class),
                recommendationEngine: $app->make(RecommendationEngine::class),
                repository: $app->make(FindingRepositoryContract::class),
                queryDetectors: $app->tagged('lynx.detectors.query'),
                requestDetectors: $app->tagged('lynx.detectors.request'),
                queueDetectors: $app->tagged('lynx.detectors.queue'),
            );
        });
    }

    /**
     * Bootstrap package services and publication.
     */
    public function boot(): void
    {
        $configPath = __DIR__ . '/../config/lynx.php';
        if ($this->app->runningInConsole()) {
            if (file_exists($configPath)) {
                $this->publishes([
                    $configPath => config_path('lynx.php'),
                ], 'lynx-config');
            }

            $this->commands([
                ScanCommand::class,
                ReportCommand::class,
                FindingsCommand::class,
                SnapshotCommand::class,
                CompareCommand::class,
            ]);
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
                }
            }

            if (config('lynx.queue.enabled', true) && $this->app->bound('events')) {
                $this->app->make(QueueCollector::class)->subscribe($this->app->make('events'));
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
