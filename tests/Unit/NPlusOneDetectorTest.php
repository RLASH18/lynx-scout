<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Detectors\NPlusOneDetector;
use Lynx\Scout\Support\SqlNormalizer;
use Lynx\Scout\Tests\TestCase;

class NPlusOneDetectorTest extends TestCase
{
    private function makeRecord(string $sql, array $context = [], float $timeMs = 4.0): QueryRecord
    {
        return new QueryRecord(
            sql: $sql,
            bindings: [],
            timeMs: $timeMs,
            connectionName: 'testing',
            executedAt: new DateTimeImmutable(),
            normalizedSql: SqlNormalizer::normalize($sql),
            caller: 'PostController@index',
            context: $context,
        );
    }

    /**
     * Test detecting N+1 query patterns.
     */
    public function test_detects_n_plus_one_relationship_queries(): void
    {
        $detector = new NPlusOneDetector(threshold: 3);
        $context = ['route' => 'GET /posts', 'uri' => '/posts'];

        $records = [
            $this->makeRecord('SELECT * FROM posts', $context, 15.0),
        ];

        // 10 child relationship queries
        for ($i = 1; $i <= 10; $i++) {
            $records[] = $this->makeRecord("SELECT * FROM users WHERE id = {$i}", $context, 3.0);
        }

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::NPlusOne, $finding->getTypeEnum());
        $this->assertEquals('N+1 query pattern detected', $finding->getTitle());
        $this->assertGreaterThanOrEqual(0.90, $finding->getConfidence());

        $evidence = $finding->getEvidence();
        $this->assertEquals(10, $evidence['occurrences']);
        $this->assertEquals(30.0, $evidence['total_time_ms']);
        $this->assertEquals('GET /posts', $evidence['route']);
        $this->assertEquals('PostController@index', $evidence['caller']);
    }

    /**
     * Test severity escalates on massive N+1 issues.
     */
    public function test_n_plus_one_severity_escalation(): void
    {
        $detector = new NPlusOneDetector(threshold: 3);
        $records = [];

        for ($i = 1; $i <= 60; $i++) {
            $records[] = $this->makeRecord("SELECT * FROM comments WHERE post_id = {$i}");
        }

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);
        $this->assertEquals(Severity::Critical, $findings[0]->getSeverity());
    }

    /**
     * Test queries below execution count threshold produce no findings.
     */
    public function test_below_threshold_produces_no_findings(): void
    {
        $detector = new NPlusOneDetector(threshold: 5);
        $records = [
            $this->makeRecord('SELECT * FROM users WHERE id = 1'),
            $this->makeRecord('SELECT * FROM users WHERE id = 2'),
        ];

        $this->assertEmpty($detector->detect($records));
    }
}
