<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Analyzers\RegressionComparator;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Support\LynxCli;

use function Termwind\render;
use function Termwind\renderUsing;

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
        renderUsing($this->output);

        $beforeId = $this->argument('before');
        $afterId = $this->argument('after');

        if ($beforeId === null || $afterId === null) {
            $snapshots = $repo->all();
            if (count($snapshots) < 2) {
                render(<<<'HTML'
                    <div class="my-1">
                        <span class="px-1 bg-red-500 text-black font-bold">ERROR</span>
                        <span class="ml-1 text-red-400">At least two snapshots are required to perform a comparison. Run `php artisan lynx:snapshot` to capture baselines.</span>
                    </div>
                HTML);
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

        LynxCli::header('REGRESSION', 'Snapshot Delta Telemetry');

        render(<<<'HTML'
            <div class="my-1">
                <span class="px-2 bg-amber-500 text-black font-bold">Performance Regression</span>
            </div>
        HTML);

        $routes = $comparison['routes'] ?? [];
        if (empty($routes)) {
            render(<<<'HTML'
                <div class="text-gray-400 my-1">No common route benchmarks found between the two snapshots.</div>
            HTML);
            return Command::SUCCESS;
        }

        foreach ($routes as $route => $data) {
            $routeEscaped = htmlspecialchars((string) $route, ENT_QUOTES, 'UTF-8');
            $beforeDuration = sprintf('%.0fms', $data['before_duration_ms']);
            $afterDuration = sprintf('%.0fms', $data['after_duration_ms']);
            $prefix = $data['duration_delta_pct'] >= 0 ? '+' : '';
            $regressionPct = sprintf('%s%.0f%%', $prefix, $data['duration_delta_pct']);
            $queriesDiff = sprintf('%.0f → %.0f', $data['before_queries'], $data['after_queries']);
            $isRegression = (bool) $data['is_regression'];

            $statusBadge = $isRegression
                ? '<span class="px-1 bg-amber-500 text-black font-bold mr-1">[!]</span><span class="text-amber-400 font-bold">Regression detected</span>'
                : '<span class="px-1 bg-green-500 text-black font-bold mr-1">✓</span><span class="text-green-400 font-bold">Performance stable</span>';

            $regressionColor = $isRegression ? 'text-red-400' : 'text-green-400';

            render(<<<HTML
                <div class="mt-1 font-bold text-white">{$routeEscaped}</div>
            HTML);

            render(<<<'HTML'
                <div class="text-gray-400">Before:</div>
            HTML);

            render(<<<HTML
                <div class="text-white font-bold">{$beforeDuration}</div>
            HTML);

            render(<<<'HTML'
                <div class="text-gray-400">After:</div>
            HTML);

            render(<<<HTML
                <div class="text-white font-bold">{$afterDuration}</div>
            HTML);

            render(<<<'HTML'
                <div class="text-gray-400">Regression:</div>
            HTML);

            render(<<<HTML
                <div class="{$regressionColor} font-bold">{$regressionPct}</div>
            HTML);

            render(<<<'HTML'
                <div class="text-gray-400">Queries:</div>
            HTML);

            render(<<<HTML
                <div class="text-white font-bold">{$queriesDiff}</div>
            HTML);

            render(<<<'HTML'
                <div class="text-gray-400">Status:</div>
            HTML);

            render(<<<HTML
                <div>{$statusBadge}</div>
                <hr class="text-gray-700 my-1" />
            HTML);
        }

        if ($this->option('fail-on-regression') && ($comparison['has_regression'] ?? false)) {
            render(<<<'HTML'
                <div class="my-1">
                    <span class="px-1 bg-red-500 text-black font-bold">FAIL</span>
                    <span class="ml-1 text-red-400">CI Check Failed: One or more performance regression thresholds were exceeded.</span>
                </div>
            HTML);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
