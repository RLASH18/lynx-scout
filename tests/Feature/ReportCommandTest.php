<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Tests\TestCase;

class ReportCommandTest extends TestCase
{
    public function test_report_command_outputs_clean_empty_report(): void
    {
        $this->artisan('lynx:report')
            ->expectsOutputToContain('Lynx Scout Performance Report')
            ->expectsOutputToContain('────────────────────────────────')
            ->expectsOutputToContain('No performance issues detected.')
            ->assertSuccessful();
    }

    public function test_report_command_outputs_detailed_findings(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();

        $collector->addRecord(new QueryRecord(
            sql: 'SELECT * FROM users WHERE active = 1',
            bindings: [],
            timeMs: 600.0,
            connectionName: 'testing',
            executedAt: new \DateTimeImmutable(),
            normalizedSql: 'select * from users where active = ?',
            caller: 'UserController@index',
            context: ['uri' => '/api/users', 'route' => 'users.index'],
        ));

        $this->artisan('lynx:report')
            ->expectsOutputToContain('Lynx Scout Performance Report')
            ->expectsOutputToContain('Slow database query detected')
            ->expectsOutputToContain('Estimated Impact:')
            ->expectsOutputToContain('Recommendation:')
            ->assertSuccessful();
    }

    public function test_report_command_json_output(): void
    {
        $this->artisan('lynx:report --json')
            ->expectsOutputToContain('"package": "ryanlester/lynx-scout"')
            ->expectsOutputToContain('"summary"')
            ->expectsOutputToContain('"findings"')
            ->assertSuccessful();
    }
}
