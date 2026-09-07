<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Services\SnapshotManager;

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

        $this->newLine();
        $this->info("Performance snapshot captured: [{$snapshot->getId()}]");
        $this->newLine();

        $this->line(sprintf('  <info>✓</info> Average Request Duration: %.2fms', (float) ($metrics['average_request_duration_ms'] ?? 0.0)));
        $this->line(sprintf('  <info>✓</info> Total Queries: %d', (int) ($metrics['total_queries'] ?? 0)));
        $this->line(sprintf('  <info>✓</info> Slow Queries: %d', (int) ($metrics['slow_queries'] ?? 0)));
        $this->line(sprintf('  <info>✓</info> Total Findings: %d', (int) ($metrics['total_findings'] ?? 0)));
        $this->line(sprintf('  <info>✓</info> Max Impact Score: %.1f', (float) ($metrics['max_impact_score'] ?? 0.0)));

        $this->newLine();
        $this->comment("Snapshot saved. Use `php artisan lynx:compare` to inspect performance regressions against this baseline.");
        $this->newLine();

        return Command::SUCCESS;
    }
}
