<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\PerformanceSnapshot;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Tests\TestCase;

class SnapshotRepositoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lynx_snap_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'snapshots' . DIRECTORY_SEPARATOR . '*.*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir . DIRECTORY_SEPARATOR . 'snapshots');
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_saves_and_retrieves_snapshot(): void
    {
        $repo = new SnapshotRepository(storagePath: $this->tempDir);

        $snapshot = new PerformanceSnapshot(
            id: 'snap-100',
            createdAt: new DateTimeImmutable(),
            metrics: [
                'total_queries' => 15,
                'total_query_time_ms' => 120.5,
                'total_requests' => 4,
                'avg_response_time_ms' => 45.2,
                'findings_count' => 2,
            ],
            routePerformance: [],
            findings: [],
        );

        $repo->save($snapshot);

        $found = $repo->find('snap-100');
        $this->assertNotNull($found);
        $this->assertEquals('snap-100', $found->getId());
        $this->assertEquals(15, $found->getMetrics()['total_queries']);
        $this->assertEquals(120.5, $found->getMetrics()['total_query_time_ms']);

        $latest = $repo->latest();
        $this->assertNotNull($latest);
        $this->assertEquals('snap-100', $latest->getId());
    }

    public function test_find_returns_null_for_nonexistent_snapshot(): void
    {
        $repo = new SnapshotRepository(storagePath: $this->tempDir);
        $this->assertNull($repo->find('nonexistent-snap'));
    }

    public function test_all_returns_all_snapshots_sorted_by_date(): void
    {
        $repo = new SnapshotRepository(storagePath: $this->tempDir);

        $snap1 = new PerformanceSnapshot(
            id: 'snap-1',
            createdAt: new DateTimeImmutable('-1 hour'),
            metrics: [
                'total_queries' => 5,
                'total_query_time_ms' => 50.0,
            ],
            routePerformance: [],
            findings: [],
        );

        $snap2 = new PerformanceSnapshot(
            id: 'snap-2',
            createdAt: new DateTimeImmutable('now'),
            metrics: [
                'total_queries' => 10,
                'total_query_time_ms' => 100.0,
            ],
            routePerformance: [],
            findings: [],
        );

        $repo->save($snap1);
        $repo->save($snap2);

        $all = $repo->all();
        $this->assertCount(2, $all);
        $this->assertEquals('snap-2', $all[0]->getId());
        $this->assertEquals('snap-1', $all[1]->getId());
    }

    public function test_corrupted_snapshot_file_skipped_in_all(): void
    {
        $repo = new SnapshotRepository(storagePath: $this->tempDir);
        $snapDir = $this->tempDir . DIRECTORY_SEPARATOR . 'snapshots';
        if (! is_dir($snapDir)) {
            mkdir($snapDir, 0755, true);
        }

        file_put_contents($snapDir . DIRECTORY_SEPARATOR . 'corrupted.json', '{ MALFORMED JSON');

        $this->assertEmpty($repo->all());
        $this->assertNull($repo->find('corrupted'));
    }
}
