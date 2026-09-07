<?php

declare(strict_types=1);

namespace Lynx\Scout\Services;

use Lynx\Scout\Analyzers\ApplicationHealthAnalyzer;
use Lynx\Scout\Analyzers\PerformanceCorrelator;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\QueueCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Detectors\CacheCandidateDetector;
use Lynx\Scout\Detectors\DuplicateQueryDetector;
use Lynx\Scout\Detectors\NPlusOneDetector;
use Lynx\Scout\Detectors\QueuePerformanceDetector;
use Lynx\Scout\Detectors\SlowQueryDetector;
use Lynx\Scout\Detectors\SlowRequestDetector;
use Lynx\Scout\Recommendations\RecommendationEngine;
use Lynx\Scout\Scoring\ImpactScorer;

class LynxScanner
{
    public function __construct(
        private readonly QueryCollector $queryCollector,
        private readonly RequestCollector $requestCollector,
        private readonly QueueCollector $queueCollector,
        private readonly SlowQueryDetector $slowQueryDetector,
        private readonly DuplicateQueryDetector $duplicateQueryDetector,
        private readonly NPlusOneDetector $nPlusOneDetector,
        private readonly SlowRequestDetector $slowRequestDetector,
        private readonly CacheCandidateDetector $cacheCandidateDetector,
        private readonly ApplicationHealthAnalyzer $healthAnalyzer,
        private readonly QueuePerformanceDetector $queueDetector,
        private readonly PerformanceCorrelator $correlator,
        private readonly ImpactScorer $scorer,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly FindingRepositoryContract $repository,
    ) {}

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
        $rawFindings = array_merge(
            $rawFindings,
            $this->slowQueryDetector->detect($queries),
            $this->duplicateQueryDetector->detect($queries),
            $this->nPlusOneDetector->detect($queries),
            $this->cacheCandidateDetector->detect($queries)
        );

        // 2. Request-level detectors
        $rawFindings = array_merge(
            $rawFindings,
            $this->slowRequestDetector->detect($requests)
        );

        // 3. Queue-level detectors
        $rawFindings = array_merge(
            $rawFindings,
            $this->queueDetector->detect($jobs)
        );

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
