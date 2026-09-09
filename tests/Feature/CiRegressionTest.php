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

    public function test_ci_fails_on_overall_query_regression_without_common_routes(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $repo->save(new PerformanceSnapshot(
            id: 'overall-base',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: ['average_request_duration_ms' => 100.0, 'total_queries' => 10],
            routePerformance: ['/old' => ['average_duration_ms' => 100.0, 'average_queries' => 1.0]],
        ));
        $repo->save(new PerformanceSnapshot(
            id: 'overall-after',
            createdAt: new DateTimeImmutable(),
            metrics: ['average_request_duration_ms' => 100.0, 'total_queries' => 20],
            routePerformance: ['/new' => ['average_duration_ms' => 100.0, 'average_queries' => 1.0]],
        ));

        $this->artisan('lynx:compare overall-base overall-after --fail-on-regression')
            ->expectsOutputToContain('Overall performance regression')
            ->assertFailed();
    }

    public function test_ci_detects_regression_from_zero_baseline(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $repo->save(new PerformanceSnapshot(
            id: 'zero-base',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: ['average_request_duration_ms' => 0.0, 'total_queries' => 0],
            routePerformance: ['/api' => ['average_duration_ms' => 0.0, 'average_queries' => 0.0]],
        ));
        $repo->save(new PerformanceSnapshot(
            id: 'after-queries',
            createdAt: new DateTimeImmutable(),
            metrics: ['average_request_duration_ms' => 50.0, 'total_queries' => 15],
            routePerformance: ['/api' => ['average_duration_ms' => 50.0, 'average_queries' => 2.0]],
        ));

        $this->artisan('lynx:compare zero-base after-queries --fail-on-regression')
            ->assertFailed();
    }

    public function test_comparator_identifies_new_and_removed_routes(): void
    {
        $comparator = new \Lynx\Scout\Analyzers\RegressionComparator();

        $before = new PerformanceSnapshot(
            id: 'snap-old',
            createdAt: new DateTimeImmutable('-1 day'),
            metrics: [],
            routePerformance: [
                '/common' => ['average_duration_ms' => 10.0, 'average_queries' => 1.0],
                '/removed' => ['average_duration_ms' => 10.0, 'average_queries' => 1.0],
            ],
        );

        $after = new PerformanceSnapshot(
            id: 'snap-new',
            createdAt: new DateTimeImmutable(),
            metrics: [],
            routePerformance: [
                '/common' => ['average_duration_ms' => 10.0, 'average_queries' => 1.0],
                '/added' => ['average_duration_ms' => 10.0, 'average_queries' => 1.0],
            ],
        );

        $result = $comparator->compare($before, $after);

        $this->assertContains('/added', $result['new_routes']);
        $this->assertContains('/removed', $result['removed_routes']);
        $this->assertArrayHasKey('/common', $result['routes']);
    }
}
