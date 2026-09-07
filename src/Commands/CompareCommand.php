<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Analyzers\RegressionComparator;
use Lynx\Scout\Repositories\SnapshotRepository;

class CompareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:compare
                            {before? : Identifier of baseline snapshot}
                            {after? : Identifier of comparison snapshot}
                            {--fail-on-regression : Exit with failure status if performance regressed}
                            {--threshold= : Custom regression percentage threshold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare performance metrics between two snapshots to detect regressions';

    /**
     * Execute the console command.
     */
    public function handle(SnapshotRepository $repo, RegressionComparator $comparator): int
    {
        $beforeId = $this->argument('before');
        $afterId = $this->argument('after');

        if ($beforeId === null || $afterId === null) {
            $snapshots = $repo->all();
            if (count($snapshots) < 2) {
                $this->error('At least two snapshots are required to perform a comparison. Run `php artisan lynx:snapshot` to capture baselines.');
                return Command::FAILURE;
            }

            $after = $snapshots[0];
            $before = $snapshots[1];
        } else {
            $before = $repo->find((string) $beforeId);
            $after = $repo->find((string) $afterId);

            if ($before === null) {
                $this->error("Baseline snapshot '{$beforeId}' not found.");
                return Command::FAILURE;
            }

            if ($after === null) {
                $this->error("Comparison snapshot '{$afterId}' not found.");
                return Command::FAILURE;
            }
        }

        if ($threshold = $this->option('threshold')) {
            config(['lynx.ci.regression_threshold' => (float) $threshold]);
        }

        $comparison = $comparator->compare($before, $after);

        $this->newLine();
        $this->info('Performance Regression');
        $this->newLine();

        $routes = $comparison['routes'] ?? [];
        if (empty($routes)) {
            $this->line('No common route benchmarks found between the two snapshots.');
            $this->newLine();
            return Command::SUCCESS;
        }

        foreach ($routes as $route => $data) {
            $this->line($route);
            $this->newLine();

            $this->line('Before:');
            $this->line(sprintf('%.0fms', $data['before_duration_ms']));
            $this->newLine();

            $this->line('After:');
            $this->line(sprintf('%.0fms', $data['after_duration_ms']));
            $this->newLine();

            $prefix = $data['duration_delta_pct'] >= 0 ? '+' : '';
            $this->line('Regression:');
            $this->line(sprintf('%s%.0f%%', $prefix, $data['duration_delta_pct']));
            $this->newLine();

            $this->line('Queries:');
            $this->line(sprintf('%.0f → %.0f', $data['before_queries'], $data['after_queries']));
            $this->newLine();

            $this->line('Status:');
            if ($data['is_regression']) {
                $this->line('<fg=yellow;options=bold>⚠ Regression detected</>');
            } else {
                $this->line('<info>✓ Performance stable</info>');
            }

            $this->newLine();
            $this->line('────────────────────────────────');
            $this->newLine();
        }

        if ($this->option('fail-on-regression') && ($comparison['has_regression'] ?? false)) {
            $this->error('CI Check Failed: One or more performance regression thresholds were exceeded.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
