<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Detectors\SlowQueryDetector;
use Lynx\Scout\Tests\TestCase;

class SlowQueryDetectorTest extends TestCase
{
    private function makeRecord(string $sql, float $timeMs): QueryRecord
    {
        return new QueryRecord(
            sql: $sql,
            bindings: [],
            timeMs: $timeMs,
            connectionName: 'testing',
            executedAt: new DateTimeImmutable(),
            normalizedSql: strtolower($sql),
        );
    }

    /**
     * Test queries below threshold are ignored.
     */
    public function test_query_below_threshold_produces_no_findings(): void
    {
        $detector = new SlowQueryDetector(threshold: 100.0);
        $records = [
            $this->makeRecord('SELECT * FROM users', 45.0),
            $this->makeRecord('SELECT count(*) FROM posts', 99.9),
        ];

        $findings = $detector->detect($records);
        $this->assertEmpty($findings);
    }

    /**
     * Test query above threshold generates finding with evidence.
     */
    public function test_query_above_threshold_generates_finding(): void
    {
        $detector = new SlowQueryDetector(threshold: 100.0);
        $records = [
            $this->makeRecord('SELECT * FROM users WHERE active = 1', 180.0),
        ];

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::SlowQuery, $finding->getTypeEnum());
        $this->assertEquals(Severity::Medium, $finding->getSeverity());
        $this->assertStringContainsString('180.00ms', $finding->getDescription());

        $evidence = $finding->getEvidence();
        $this->assertIsArray($evidence);
        $this->assertEquals(180.0, $evidence['duration_ms']);
        $this->assertEquals(100.0, $evidence['threshold_ms']);
    }

    /**
     * Test severity escalates with execution time.
     */
    public function test_query_severity_escalation(): void
    {
        $detector = new SlowQueryDetector(threshold: 100.0);
        $records = [
            $this->makeRecord('SELECT 1', 250.0), // 2.5x -> High
            $this->makeRecord('SELECT 2', 600.0), // 6x -> Critical
        ];

        $findings = $detector->detect($records);
        $this->assertCount(2, $findings);
        $this->assertEquals(Severity::High, $findings[0]->getSeverity());
        $this->assertEquals(Severity::Critical, $findings[1]->getSeverity());
    }

    /**
     * Test custom threshold option.
     */
    public function test_custom_threshold(): void
    {
        $detector = new SlowQueryDetector(threshold: 50.0);
        $records = [
            $this->makeRecord('SELECT 1', 65.0),
        ];

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);
        $this->assertEquals(50.0, $findings[0]->getEvidence()['threshold_ms']);
    }

    /**
     * Test detector respects disabled flag.
     */
    public function test_disabled_monitoring_produces_no_findings(): void
    {
        $detector = new SlowQueryDetector(threshold: 100.0, enabled: false);
        $records = [
            $this->makeRecord('SELECT * FROM big_table', 5000.0),
        ];

        $findings = $detector->detect($records);
        $this->assertEmpty($findings);
    }
}
