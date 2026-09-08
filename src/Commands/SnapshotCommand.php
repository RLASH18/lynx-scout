<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
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
    protected $signature = 'lynx:snapshot {--name= : Custom identifier for the snapshot}';

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
        $customName = $this->option('name');
        $snapshot = $manager->capture($customName ? (string) $customName : null);
        $metrics = $snapshot->getMetrics();

        renderUsing($this->output);

        LynxCli::header('SNAPSHOT', 'Performance Benchmark Baseline');

        render(<<<HTML
            <div class="mt-1">
                <span class="px-1 bg-amber-500 text-black font-bold">LOCKED</span>
                <span class="ml-1 text-white font-bold">Performance snapshot captured: [{$snapshot->getId()}]</span>
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

        render(<<<'HTML'
            <div class="mt-2 text-gray-400">
                Snapshot saved. Use <span class="text-amber-400 font-bold">php artisan lynx:compare</span> to inspect performance regressions against this baseline.
            </div>
        HTML);

        return Command::SUCCESS;
    }
}
