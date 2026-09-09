<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Detectors\CacheCandidateDetector;
use Lynx\Scout\Support\SqlNormalizer;
use Lynx\Scout\Tests\TestCase;

class CacheCandidateDetectorTest extends TestCase
{
    private function makeRecord(string $sql, float $timeMs = 15.0, ?string $normalizedSql = null): QueryRecord
    {
        return new QueryRecord(
            sql: $sql,
            bindings: [],
            timeMs: $timeMs,
            connectionName: 'testing',
            executedAt: new DateTimeImmutable(),
            normalizedSql: $normalizedSql ?? SqlNormalizer::normalize($sql),
        );
    }

    /**
     * Test repeated expensive SELECT queries are flagged as cache candidates.
     */
    public function test_detects_cache_candidate_queries(): void
    {
        $detector = new CacheCandidateDetector(frequencyThreshold: 5);
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = $this->makeRecord('SELECT * FROM products WHERE active = 1', 34.0);
        }

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::CacheCandidate, $finding->getTypeEnum());
        $this->assertEquals('Potential cache candidate detected', $finding->getTitle());

        $evidence = $finding->getEvidence();
        $this->assertEquals(10, $evidence['occurrences']);
        $this->assertEquals(34.0, $evidence['average_duration_ms']);
        $this->assertEquals(340.0, $evidence['total_time_ms']);
        $this->assertStringContainsString('data freshness', $evidence['warning']);
    }

    /**
     * Test parameterized queries with varying parameters are grouped by normalized pattern.
     */
    public function test_parameterized_queries_with_different_literals_are_grouped_as_cache_candidates(): void
    {
        $detector = new CacheCandidateDetector(frequencyThreshold: 3);
        $records = [
            $this->makeRecord('SELECT * FROM products WHERE id = 1', 50.0),
            $this->makeRecord('SELECT * FROM products WHERE id = 2', 60.0),
            $this->makeRecord('SELECT * FROM products WHERE id = 3', 70.0),
        ];

        $findings = $detector->detect($records);
        $this->assertCount(1, $findings);
        $this->assertEquals(3, $findings[0]->getEvidence()['occurrences']);
        $this->assertEquals('SELECT * FROM products WHERE id = ?', $findings[0]->getEvidence()['normalized_sql']);
    }

    /**
     * Test write operations are excluded from cache recommendations.
     */
    public function test_ignores_write_queries(): void
    {
        $detector = new CacheCandidateDetector(frequencyThreshold: 3);
        $records = [];

        for ($i = 0; $i < 6; $i++) {
            $records[] = $this->makeRecord('UPDATE users SET last_login_at = NOW() WHERE id = 1');
        }

        $findings = $detector->detect($records);
        $this->assertEmpty($findings);
    }
}
