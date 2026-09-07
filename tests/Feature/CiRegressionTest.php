<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use DateTimeImmutable;
use Lynx\Scout\Data\PerformanceSnapshot;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Tests\TestCase;

class CiRegressionTest extends TestCase
{
    public function test_ci_fails_on_regression(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $before = new PerformanceSnapshot(
            id: 'ci-base',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: ['average_request_duration_ms' => 100.0, 'total_queries' => 5],
            routePerformance: [
                '/api/checkout' => [
                    'requests' => 5,
                    'average_duration_ms' => 100.0,
                    'average_queries' => 5.0,
                ],
            ],
        );

        $after = new PerformanceSnapshot(
            id: 'ci-slow',
            createdAt: new DateTimeImmutable(),
            metrics: ['average_request_duration_ms' => 250.0, 'total_queries' => 15],
            routePerformance: [
                '/api/checkout' => [
                    'requests' => 5,
                    'average_duration_ms' => 250.0, // +150%
                    'average_queries' => 15.0,
                ],
            ],
        );

        $repo->save($before);
        $repo->save($after);

        $this->artisan('lynx:compare ci-base ci-slow --fail-on-regression')
            ->expectsOutputToContain('CI Check Failed')
            ->assertFailed();
    }

    public function test_ci_passes_when_within_custom_threshold(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $before = new PerformanceSnapshot(
            id: 'ci-base-2',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: ['average_request_duration_ms' => 100.0, 'total_queries' => 5],
            routePerformance: [
                '/api/checkout' => [
                    'requests' => 5,
                    'average_duration_ms' => 100.0,
                    'average_queries' => 5.0,
                ],
            ],
        );

        $after = new PerformanceSnapshot(
            id: 'ci-slight-slow',
            createdAt: new DateTimeImmutable(),
            metrics: ['average_request_duration_ms' => 110.0, 'total_queries' => 5],
            routePerformance: [
                '/api/checkout' => [
                    'requests' => 5,
                    'average_duration_ms' => 110.0, // +10%
                    'average_queries' => 5.0,
                ],
            ],
        );

        $repo->save($before);
        $repo->save($after);

        $this->artisan('lynx:compare ci-base-2 ci-slight-slow --fail-on-regression')
            ->assertSuccessful();
    }
}
