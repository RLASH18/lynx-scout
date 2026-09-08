<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Tests\TestCase;

class SnapshotTest extends TestCase
{
    public function test_snapshot_command_captures_and_saves_state(): void
    {
        $this->artisan('lynx:snapshot --name=test-base')
            ->expectsOutputToContain('Performance snapshot captured: [test-base]')
            ->expectsOutputToContain('Average Request Duration:')
            ->expectsOutputToContain('Total Queries:')
            ->assertSuccessful();

        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);
        $saved = $repo->find('test-base');

        $this->assertNotNull($saved);
        $this->assertEquals('test-base', $saved->getId());
        $this->assertArrayHasKey('total_requests', $saved->getMetrics());
    }

    public function test_snapshot_command_with_route_option(): void
    {
        $this->app['router']->get('/snapshot-target', function () {
            \Illuminate\Support\Facades\DB::select('SELECT 1');
            return response()->json(['status' => 'ok']);
        });

        $this->artisan('lynx:snapshot', ['--name' => 'route-snap', '--route' => '/snapshot-target'])
            ->expectsOutputToContain('Performance snapshot captured: [route-snap]')
            ->expectsOutputToContain('Total Queries:')
            ->assertSuccessful();

        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);
        $saved = $repo->find('route-snap');

        $this->assertNotNull($saved);
        $this->assertArrayHasKey('/snapshot-target', $saved->getRoutePerformance());
    }
}
