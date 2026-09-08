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

        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $status = $kernel->call('lynx:compare', ['before' => 'snap-before', 'after' => 'snap-after']);
        $output = $kernel->output();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Performance Regression', $output);
        $this->assertStringContainsString('/api/orders', $output);
        $this->assertStringContainsString('184ms', $output);
        $this->assertStringContainsString('327ms', $output);
        $this->assertStringContainsString('+78%', $output);
        $this->assertStringContainsString('18 → 46', $output);
        $this->assertStringContainsString('Regression detected', $output);
    }
}
