<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Analyzers\PerformanceCorrelator;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Tests\TestCase;

class PerformanceCorrelatorTest extends TestCase
{
    private function makeRequest(
        string $uri,
        float $durationMs,
        int $queryCount,
        float $queryTimeMs
    ): RequestRecord {
        return new RequestRecord(
            id: 'req-test',
            method: 'GET',
            uri: $uri,
            routeName: 'test.route',
            action: 'TestController@index',
            durationMs: $durationMs,
            statusCode: 200,
            queryCount: $queryCount,
            queryTimeMs: $queryTimeMs,
            memoryBytes: 1024 * 1024,
            requestedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Test Slow Route + High Query Count + N+1 finding -> N+1 root cause correlation.
     */
    public function test_correlates_n_plus_one_root_cause(): void
    {
        $correlator = new PerformanceCorrelator();
        $request = $this->makeRequest('/posts', 800.0, 45, 650.0); // 81% DB time

        $nPlusOneFinding = Finding::create(
            type: FindingType::NPlusOne,
            severity: Severity::High,
            title: 'N+1 query pattern detected',
            description: '45 queries',
            evidence: ['route' => '/posts'],
            context: ['uri' => '/posts'],
        );

        $correlated = $correlator->correlate([$request], [], [$nPlusOneFinding]);

        $this->assertCount(1, $correlated);
        $finding = $correlated[0];
        $this->assertEquals(FindingType::CorrelatedIssue, $finding->getTypeEnum());
        $this->assertEquals(Severity::Critical, $finding->getSeverity());
        $this->assertStringContainsString('N+1', $finding->getEvidence()['diagnosis']);
        $this->assertGreaterThanOrEqual(0.90, $finding->getConfidence());
    }

    /**
     * Test Slow Route + Low DB Time -> Application processing root cause.
     */
    public function test_correlates_application_processing_bottleneck(): void
    {
        $correlator = new PerformanceCorrelator();
        $request = $this->makeRequest('/export', 1200.0, 2, 20.0); // only 1.6% DB time

        $correlated = $correlator->correlate([$request], [], []);

        $this->assertCount(1, $correlated);
        $finding = $correlated[0];
        $this->assertEquals(FindingType::CorrelatedIssue, $finding->getTypeEnum());
        $this->assertStringContainsString('Application CPU processing', $finding->getEvidence()['diagnosis']);
    }

    /**
     * Test fast requests produce no unnecessary correlation findings.
     */
    public function test_fast_requests_produce_no_correlations(): void
    {
        $correlator = new PerformanceCorrelator();
        $request = $this->makeRequest('/fast', 45.0, 2, 5.0);

        $correlated = $correlator->correlate([$request], [], []);
        $this->assertEmpty($correlated);
    }
}
