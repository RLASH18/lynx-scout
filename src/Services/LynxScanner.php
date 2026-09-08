<?php

declare(strict_types=1);

namespace Lynx\Scout\Services;

use Lynx\Scout\Analyzers\ApplicationHealthAnalyzer;
use Lynx\Scout\Analyzers\PerformanceCorrelator;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\QueueCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Recommendations\RecommendationEngine;
use Lynx\Scout\Scoring\ImpactScorer;

class LynxScanner
{
    /** @var list<DetectorContract> */
    private readonly array $queryDetectors;

    /** @var list<DetectorContract> */
    private readonly array $requestDetectors;

    /** @var list<DetectorContract> */
    private readonly array $queueDetectors;

    /**
     * @param iterable<int, DetectorContract> $queryDetectors
     * @param iterable<int, DetectorContract> $requestDetectors
     * @param iterable<int, DetectorContract> $queueDetectors
     */
    public function __construct(
        private readonly QueryCollector $queryCollector,
        private readonly RequestCollector $requestCollector,
        private readonly QueueCollector $queueCollector,
        private readonly ApplicationHealthAnalyzer $healthAnalyzer,
        private readonly PerformanceCorrelator $correlator,
        private readonly ImpactScorer $scorer,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly FindingRepositoryContract $repository,
        iterable $queryDetectors = [],
        iterable $requestDetectors = [],
        iterable $queueDetectors = [],
    ) {
        $this->queryDetectors = is_array($queryDetectors) ? array_values($queryDetectors) : array_values(iterator_to_array($queryDetectors));
        $this->requestDetectors = is_array($requestDetectors) ? array_values($requestDetectors) : array_values(iterator_to_array($requestDetectors));
        $this->queueDetectors = is_array($queueDetectors) ? array_values($queueDetectors) : array_values(iterator_to_array($queueDetectors));
    }

    /**
     * Perform full performance scan and return prioritized findings.
     *
     * @return list<Finding>
     */
    public function scan(bool $persist = true): array
    {
        $queries = $this->queryCollector->getQueries();
        $requests = $this->requestCollector->getRequests();
        $jobs = $this->queueCollector->getJobs();

        $rawFindings = [];

        // 1. Query-level detectors
        foreach ($this->queryDetectors as $detector) {
            $rawFindings = array_merge($rawFindings, $detector->detect($queries));
        }

        // 2. Request-level detectors
        foreach ($this->requestDetectors as $detector) {
            $rawFindings = array_merge($rawFindings, $detector->detect($requests));
        }

        // 3. Queue-level detectors
        foreach ($this->queueDetectors as $detector) {
            $rawFindings = array_merge($rawFindings, $detector->detect($jobs));
        }

        // 4. Application health checks
        $rawFindings = array_merge(
            $rawFindings,
            $this->healthAnalyzer->analyze()
        );

        // 5. Correlate across domains
        $correlated = $this->correlator->correlate($requests, $queries, $rawFindings);
        $allFindings = array_merge($rawFindings, $correlated);

        // 6. Enrich with actionable recommendations
        $enriched = $this->recommendationEngine->enrichAll($allFindings);

        // 7. Score and rank by estimated impact
        $prioritized = $this->scorer->scoreAll($enriched);

        // 8. Persist to storage if requested
        if ($persist && ! empty($prioritized)) {
            $this->repository->saveMany($prioritized);
        }

        return $prioritized;
    }
}
