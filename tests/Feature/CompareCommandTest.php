<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use DateTimeImmutable;
use Lynx\Scout\Data\PerformanceSnapshot;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Tests\TestCase;

class CompareCommandTest extends TestCase
{
    public function test_compare_command_detects_regression(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $before = new PerformanceSnapshot(
            id: 'snap-before',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: ['average_request_duration_ms' => 184.0, 'total_queries' => 18],
            routePerformance: [
                '/api/orders' => [
                    'requests' => 10,
                    'average_duration_ms' => 184.0,
                    'average_queries' => 18.0,
                ],
            ],
        );

        $after = new PerformanceSnapshot(
            id: 'snap-after',
            createdAt: new DateTimeImmutable(),
            metrics: ['average_request_duration_ms' => 327.0, 'total_queries' => 46],
            routePerformance: [
                '/api/orders' => [
                    'requests' => 10,
                    'average_duration_ms' => 327.0,
                    'average_queries' => 46.0,
                ],
            ],
        );

        $repo->save($before);
        $repo->save($after);

        $this->artisan('lynx:compare snap-before snap-after')
            ->expectsOutputToContain('Performance Regression')
            ->expectsOutputToContain('/api/orders')
            ->expectsOutputToContain('Before:')
            ->expectsOutputToContain('184ms')
            ->expectsOutputToContain('After:')
            ->expectsOutputToContain('327ms')
            ->expectsOutputToContain('Regression:')
            ->expectsOutputToContain('+78%')
            ->expectsOutputToContain('18 → 46')
            ->expectsOutputToContain('Regression detected')
            ->assertSuccessful();
    }
}
