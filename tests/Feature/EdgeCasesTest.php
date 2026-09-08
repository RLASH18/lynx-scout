<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use DateTimeImmutable;
use Lynx\Scout\Analyzers\PerformanceCorrelator;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Detectors\SlowQueryDetector;
use Lynx\Scout\Repositories\SnapshotRepository;
use Lynx\Scout\Scoring\ImpactScorer;
use Lynx\Scout\Services\LynxScanner;
use Lynx\Scout\Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    /**
     * Test zero queries handled safely without division by zero.
     */
    public function test_zero_queries_edge_case(): void
    {
        /** @var LynxScanner $scanner */
        $scanner = $this->app->make(LynxScanner::class);
        $this->app->make(QueryCollector::class)->reset();
        $this->app->make(RequestCollector::class)->reset();

        $findings = $scanner->scan();
        $this->assertIsArray($findings);
    }

    /**
     * Test extremely slow query (e.g. 60,000ms) handles scoring and log scaling cleanly.
     */
    public function test_extremely_slow_query_handled_safely(): void
    {
        $detector = new SlowQueryDetector();
        $scorer = new ImpactScorer();

        $hugeQuery = new QueryRecord(
            sql: 'SELECT SLEEP(60)',
            bindings: [],
            timeMs: 60000.0,
            connectionName: 'testing',
            executedAt: new DateTimeImmutable(),
            normalizedSql: 'select sleep(?)',
        );

        $findings = $detector->detect([$hugeQuery]);
        $this->assertCount(1, $findings);

        $scored = $scorer->score($findings[0]);
        $this->assertEquals(Severity::Critical, $scored->getSeverity());
        $this->assertLessThanOrEqual(100.0, $scored->getScore());
        $this->assertGreaterThanOrEqual(70.0, $scored->getScore());
    }

    /**
     * Test query collector bounds memory when thousands of queries are fired.
     */
    public function test_thousands_of_queries_memory_bounded(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();

        for ($i = 0; $i < 1500; $i++) {
            $collector->addRecord(new QueryRecord(
                sql: "SELECT {$i}",
                bindings: [],
                timeMs: 1.0,
                connectionName: 'testing',
                executedAt: new DateTimeImmutable(),
                normalizedSql: 'select ?',
            ));
        }

        // Must not exceed internal cap of 1000 items
        $this->assertLessThanOrEqual(1000, count($collector->getQueries()));
    }

    /**
     * Test requests without named routes or action names.
     */
    public function test_missing_route_context(): void
    {
        $correlator = new PerformanceCorrelator();

        $anonRequest = new RequestRecord(
            id: 'anon-1',
            method: 'POST',
            uri: '/anonymous-endpoint',
            routeName: null,
            action: null,
            durationMs: 800.0,
            statusCode: 200,
            queryCount: 30,
            queryTimeMs: 700.0,
            memoryBytes: 1024 * 1024,
            requestedAt: new DateTimeImmutable(),
        );

        $finding = Finding::create(
            FindingType::NPlusOne,
            Severity::High,
            'N+1',
            'Desc',
            ['uri' => '/anonymous-endpoint'],
            context: ['uri' => '/anonymous-endpoint']
        );

        $results = $correlator->correlate([$anonRequest], [], [$finding]);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('/anonymous-endpoint', $results[0]->getTitle());
    }

    /**
     * Test corrupted snapshot files are gracefully handled by repository.
     */
    public function test_corrupted_snapshot_file_handled_gracefully(): void
    {
        /** @var SnapshotRepository $repo */
        $repo = $this->app->make(SnapshotRepository::class);

        $dir = storage_path('lynx/snapshots');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir . '/corrupted_snap.json', '{ INVALID JSON SYNTAX');

        $loaded = $repo->find('corrupted_snap');
        $this->assertNull($loaded);

        @unlink($dir . '/corrupted_snap.json');
    }

    /**
     * Test disabled monitoring respects master config switch.
     */
    public function test_disabled_monitoring(): void
    {
        config(['lynx.enabled' => false]);

        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();
        $this->assertCount(0, $collector->getQueries());
    }

    /**
     * Test custom detector registration via tagged container binding.
     */
    public function test_custom_detector_via_tagged_container(): void
    {
        $customDetector = new class implements \Lynx\Scout\Contracts\DetectorContract {
            public function detect(array $records): array
            {
                return [
                    Finding::create(
                        FindingType::SlowQuery,
                        Severity::Critical,
                        'Custom Plugin Finding',
                        'Detected by tagged detector',
                        'custom-evidence'
                    ),
                ];
            }
        };

        $this->app->instance('custom.test.detector', $customDetector);
        $this->app->tag(['custom.test.detector'], 'lynx.detectors.query');

        /** @var LynxScanner $scanner */
        $scanner = $this->app->make(LynxScanner::class);
        $findings = $scanner->scan(false);

        $customFindings = array_filter($findings, fn ($f) => $f->getTitle() === 'Custom Plugin Finding');
        $this->assertNotEmpty($customFindings);
    }
}
