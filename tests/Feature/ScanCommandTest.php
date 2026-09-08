<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Tests\TestCase;

class ScanCommandTest extends TestCase
{
    public function test_scan_command_runs_with_no_findings(): void
    {
        $this->artisan('lynx:scan')
            ->expectsOutputToContain('Lynx Scout')
            ->expectsOutputToContain('Scanning application...')
            ->expectsOutputToContain('Requests analyzed')
            ->expectsOutputToContain('No performance concerns detected. Application is healthy!')
            ->assertSuccessful();
    }

    public function test_scan_command_outputs_detected_findings(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();

        // Inject slow query record
        $collector->addRecord(new QueryRecord(
            sql: 'SELECT * FROM big_table WHERE active = 1',
            bindings: [],
            timeMs: 350.0,
            connectionName: 'testing',
            executedAt: new \DateTimeImmutable(),
            normalizedSql: 'select * from big_table where active = ?',
        ));

        $this->artisan('lynx:scan')
            ->expectsOutputToContain('Lynx Scout')
            ->expectsOutputToContain('1 finding detected.')
            ->expectsOutputToContain('Slow database query detected')
            ->assertSuccessful();
    }

    public function test_scan_command_profiles_specified_route(): void
    {
        $this->app['router']->get('/bench-target', function () {
            DB::select('SELECT 1');
            return response()->json(['status' => 'ok']);
        });

        $this->artisan('lynx:scan', ['--route' => '/bench-target'])
            ->expectsOutputToContain('bench-target')
            ->assertSuccessful();
    }

    public function test_scan_command_falls_back_to_repository_when_live_collector_is_empty(): void
    {
        $repo = $this->app->make(\Lynx\Scout\Contracts\FindingRepositoryContract::class);
        $repo->save(\Lynx\Scout\Data\Finding::create(
            type: \Lynx\Scout\Data\FindingType::SlowQuery,
            severity: \Lynx\Scout\Data\Severity::Critical,
            title: 'Stored historical slow query',
            description: 'A query took too long to execute.',
            evidence: ['query' => 'SELECT 1'],
        ));

        $this->artisan('lynx:scan')
            ->expectsOutputToContain('1 finding detected.')
            ->expectsOutputToContain('Stored historical slow query')
            ->assertSuccessful();
    }
}
