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
}
