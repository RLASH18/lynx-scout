<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Detectors\DuplicateQueryDetector;
use Lynx\Scout\Support\SqlNormalizer;
use Lynx\Scout\Tests\TestCase;

class DuplicateQueryDetectorTest extends TestCase
{
    private function makeRecord(string $sql, float $timeMs = 5.0, ?string $caller = 'PostController@show'): QueryRecord
    {
        return new QueryRecord(
            sql: $sql,
            bindings: [],
            timeMs: $timeMs,
            connectionName: 'testing',
            executedAt: new DateTimeImmutable(),
            normalizedSql: SqlNormalizer::normalize($sql),
            caller: $caller,
        );
    }

    /**
     * Test single queries produce no duplicate findings.
     */
    public function test_unique_queries_produce_no_duplicate_findings(): void
    {
        $detector = new DuplicateQueryDetector(threshold: 2);
        $records = [
            $this->makeRecord('SELECT * FROM users WHERE id = 1'),
            $this->makeRecord('SELECT * FROM posts WHERE id = 1'),
            $this->makeRecord('SELECT * FROM comments WHERE id = 1'),
        ];

        $findings = $detector->detect($records);
        $this->assertEmpty($findings);
    }

    /**
     * Test repeated queries with variable parameters are detected via normalization.
     */
    public function test_duplicate_queries_detected_via_normalization(): void
    {
        $detector = new DuplicateQueryDetector(threshold: 3);
        $records = [
            $this->makeRecord('SELECT * FROM users WHERE id = 1', 10.0),
            $this->makeRecord('SELECT * FROM users WHERE id = 2', 12.0),
            $this->makeRecord('SELECT * FROM users WHERE id = 3', 8.0),
            $this->makeRecord('SELECT * FROM settings WHERE key = ?', 2.0),
        ];

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::DuplicateQuery, $finding->getTypeEnum());
        $this->assertStringContainsString('3 times', $finding->getDescription());

        $evidence = $finding->getEvidence();
        $this->assertEquals(3, $evidence['occurrences']);
        $this->assertEquals(30.0, $evidence['total_time_ms']);
        $this->assertEquals('PostController@show', $evidence['caller']);
    }

    /**
     * Test severity escalates with high duplication count.
     */
    public function test_severity_escalation_on_high_duplicates(): void
    {
        $detector = new DuplicateQueryDetector(threshold: 2);
        $records = [];
        for ($i = 1; $i <= 25; $i++) {
            $records[] = $this->makeRecord("SELECT * FROM items WHERE id = {$i}", 2.0);
        }

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);
        $this->assertEquals(Severity::Critical, $findings[0]->getSeverity());
    }

    /**
     * Test disabled detector returns empty findings.
     */
    public function test_disabled_detector(): void
    {
        $detector = new DuplicateQueryDetector(threshold: 2, enabled: false);
        $records = [
            $this->makeRecord('SELECT 1'),
            $this->makeRecord('SELECT 1'),
        ];

        $this->assertEmpty($detector->detect($records));
    }
}
