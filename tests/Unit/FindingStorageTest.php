<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Repositories\FileFindingRepository;
use Lynx\Scout\Repositories\MemoryFindingRepository;
use Lynx\Scout\Tests\TestCase;

class FindingStorageTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lynx_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempPath)) {
            @unlink($this->tempPath . DIRECTORY_SEPARATOR . 'findings.json');
            @rmdir($this->tempPath);
        }
        parent::tearDown();
    }

    public function test_file_repository_persists_and_aggregates_findings(): void
    {
        $repo = new FileFindingRepository(storagePath: $this->tempPath);

        $finding = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::High,
            title: 'Slow query on users',
            description: 'SELECT * FROM users took 500ms',
            evidence: ['sql' => 'SELECT * FROM users'],
            context: ['uri' => '/users'],
            id: 'find-1',
        );

        $repo->save($finding);
        $all = $repo->all();
        $this->assertCount(1, $all);
        $this->assertEquals(1, $all[0]->getContext()['occurrence_count'] ?? 0);

        // Save again: should aggregate rather than duplicating
        $repo->save($finding);
        $allAfter = $repo->all();
        $this->assertCount(1, $allAfter);
        $this->assertEquals(2, $allAfter[0]->getContext()['occurrence_count'] ?? 0);
    }

    public function test_repository_querying_and_filtering(): void
    {
        $repo = new FileFindingRepository(storagePath: $this->tempPath);

        $slow = Finding::create(FindingType::SlowQuery, Severity::High, 'Slow', 'D', 'E', id: 'slow-1');
        $n1 = Finding::create(FindingType::NPlusOne, Severity::Critical, 'N1', 'D', 'E', id: 'n1-1');

        $repo->saveMany([$slow, $n1]);

        $this->assertCount(1, $repo->whereType('n_plus_one'));
        $this->assertCount(1, $repo->whereSeverity(Severity::Critical));
        $this->assertNotNull($repo->find('slow-1'));
        $this->assertNull($repo->find('nonexistent'));

        $repo->clear();
        $this->assertEmpty($repo->all());
    }

    public function test_memory_repository(): void
    {
        $repo = new MemoryFindingRepository();
        $f = Finding::create(FindingType::SlowRequest, Severity::Medium, 'T', 'D', 'E', id: 'req-1');

        $repo->save($f);
        $this->assertCount(1, $repo->all());
        $this->assertEquals($f, $repo->find('req-1'));

        $repo->clear();
        $this->assertEmpty($repo->all());
    }

    public function test_file_repository_handles_corrupted_json_gracefully(): void
    {
        $repo = new FileFindingRepository(storagePath: $this->tempPath);
        $file = $this->tempPath . DIRECTORY_SEPARATOR . 'findings.json';

        file_put_contents($file, '{ INVALID JSON ... NOT CLOSED');

        $this->assertEmpty($repo->all());
        $this->assertNull($repo->find('any-id'));
    }

    public function test_file_repository_skips_partially_corrupted_finding_items(): void
    {
        $repo = new FileFindingRepository(storagePath: $this->tempPath);
        $file = $this->tempPath . DIRECTORY_SEPARATOR . 'findings.json';

        $finding = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::High,
            title: 'Valid Finding',
            description: 'Valid description',
            evidence: [],
            id: 'valid-1',
        );

        $validData = $finding->toArray();
        $invalidData = ['corrupted_item_without_required_fields' => true];

        file_put_contents($file, json_encode([$validData, $invalidData, 'string_instead_of_array']));

        $all = $repo->all();
        $this->assertCount(1, $all);
        $this->assertEquals('valid-1', $all[0]->getId());
    }
}
