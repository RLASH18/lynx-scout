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
}
