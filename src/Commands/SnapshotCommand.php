<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Lynx\Scout\Services\SnapshotManager;
use Lynx\Scout\Support\LynxCli;

use function Termwind\render;
use function Termwind\renderUsing;

class SnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:snapshot
                            {--name= : Custom identifier for the snapshot}
                            {--route= : Profile an internal application route before capturing the snapshot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture application performance state into a persistent snapshot';

    /**
     * Execute the console command.
     */
    public function handle(SnapshotManager $manager): int
    {
        renderUsing($this->output);

        $rawRoute = $this->option('route');
        if ($rawRoute) {
            $route = (string) $rawRoute;
            // Normalize Git Bash POSIX-to-Windows path translation (e.g. C:/Program Files/Git/products -> /products)
            if (preg_match('#^[A-Za-z]:/(?:.*?/)?(?:Git|msys\d*)/(.*)$#i', $route, $matches)) {
                $route = '/' . ltrim($matches[1], '/');
            } else {
                $route = '/' . ltrim($route, '/');
            }

            try {
                $kernel = $this->laravel->make(Kernel::class);
                $request = Request::create($route, 'GET');
                $response = $kernel->handle($request);
                if (method_exists($kernel, 'terminate')) {
                    $kernel->terminate($request, $response);
                }
            } catch (\Throwable $e) {
                $safeMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                render(<<<HTML
                    <div class="text-red-500 font-bold mb-1">Failed to profile route: {$safeMessage}</div>
                HTML);
            }
        }

        $customName = $this->option('name');
        try {
            $snapshot = $manager->capture($customName ? (string) $customName : null);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }
        $metrics = $snapshot->getMetrics();

        LynxCli::header('SNAPSHOT', 'Performance Benchmark Baseline');

        $safeSnapshotId = htmlspecialchars($snapshot->getId(), ENT_QUOTES, 'UTF-8');
        render(<<<HTML
            <div class="mt-1">
                <span class="px-1 bg-amber-500 text-black font-bold">LOCKED</span>
                <span class="ml-1 text-white font-bold">Performance snapshot captured: [{$safeSnapshotId}]</span>
            </div>
            <hr class="text-gray-700 my-1" />
        HTML);

        $avgReq = sprintf('%.2fms', (float) ($metrics['average_request_duration_ms'] ?? 0.0));
        $totQ = (int) ($metrics['total_queries'] ?? 0);
        $slowQ = (int) ($metrics['slow_queries'] ?? 0);
        $totF = (int) ($metrics['total_findings'] ?? 0);
        $maxImp = sprintf('%.1f', (float) ($metrics['max_impact_score'] ?? 0.0));

        render(<<<HTML
            <div>
                <span class="text-green-500 font-bold mr-1">✓</span>
                <span class="text-gray-400">Average Request Duration:</span>
                <span class="text-white font-bold ml-1">{$avgReq}</span>
            </div>
        HTML);

        render(<<<HTML
            <div>
                <div><span class="text-green-500 font-bold mr-1">✓</span><span class="text-gray-400">Total Queries:</span><span class="text-white font-bold ml-1">{$totQ}</span></div>
                <div><span class="text-green-500 font-bold mr-1">✓</span><span class="text-gray-400">Slow Queries:</span><span class="text-white font-bold ml-1">{$slowQ}</span></div>
                <div><span class="text-green-500 font-bold mr-1">✓</span><span class="text-gray-400">Total Findings:</span><span class="text-white font-bold ml-1">{$totF}</span></div>
                <div><span class="text-green-500 font-bold mr-1">✓</span><span class="text-gray-400">Max Impact Score:</span><span class="text-white font-bold ml-1">{$maxImp}</span></div>
            </div>
        HTML);

        if ($totQ === 0 && ! $rawRoute) {
            render(<<<'HTML'
                <div class="mt-2 text-gray-500">
                    Notice: 0 queries were captured in this standalone CLI process.
                    Tip: Pass <span class="text-amber-400 font-bold">--route=/your-route</span> (e.g. php artisan lynx:snapshot --route=/products) to profile and capture an endpoint baseline.
                </div>
            HTML);
        }

        render(<<<'HTML'
            <div class="mt-2 text-gray-400">
                Snapshot saved. Use <span class="text-amber-400 font-bold">php artisan lynx:compare</span> to inspect performance regressions against this baseline.
            </div>
        HTML);

        return Command::SUCCESS;
    }
}
