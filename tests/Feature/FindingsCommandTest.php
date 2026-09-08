<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Tests\TestCase;

class FindingsCommandTest extends TestCase
{
    public function test_findings_command_displays_stored_findings(): void
    {
        /** @var FindingRepositoryContract $repo */
        $repo = $this->app->make(FindingRepositoryContract::class);
        $repo->clear();

        $repo->save(Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::High,
            title: 'Slow user query',
            description: '450ms',
            evidence: 'duration 450ms',
            id: 'find-slow-1',
        ));

        $this->artisan('lynx:findings')
            ->expectsTable(
                ['ID', 'Severity', 'Type', 'Title', 'Occurrences', 'Score', 'Detected At'],
                [
                    ['find-slow-1', 'HIGH', 'slow_query', 'Slow user query', '1', '0.0 (Medium)', date('Y-m-d H:i:s')],
                ],
                'box'
            )
            ->assertSuccessful();
    }

    public function test_findings_command_filters_by_severity(): void
    {
        /** @var FindingRepositoryContract $repo */
        $repo = $this->app->make(FindingRepositoryContract::class);
        $repo->clear();

        $repo->saveMany([
            Finding::create(FindingType::SlowQuery, Severity::Low, 'Minor query', 'D', 'E', id: 'f-low'),
            Finding::create(FindingType::NPlusOne, Severity::Critical, 'Critical N+1', 'D', 'E', id: 'f-crit'),
        ]);

        $this->artisan('lynx:findings --severity=critical')
            ->expectsOutputToContain('f-crit')
            ->doesntExpectOutputToContain('f-low')
            ->assertSuccessful();
    }

    public function test_findings_command_filters_by_type(): void
    {
        /** @var FindingRepositoryContract $repo */
        $repo = $this->app->make(FindingRepositoryContract::class);
        $repo->clear();

        $repo->saveMany([
            Finding::create(FindingType::SlowQuery, Severity::High, 'Slow query', 'D', 'E', id: 'f-slow'),
            Finding::create(FindingType::NPlusOne, Severity::High, 'N+1 issue', 'D', 'E', id: 'f-n1'),
        ]);

        $this->artisan('lynx:findings --type=n-plus-one')
            ->expectsOutputToContain('f-n1')
            ->doesntExpectOutputToContain('f-slow')
            ->assertSuccessful();
    }
}
