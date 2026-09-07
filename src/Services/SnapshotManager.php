<?php

declare(strict_types=1);

namespace Lynx\Scout\Services;

use DateTimeImmutable;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\PerformanceSnapshot;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Repositories\SnapshotRepository;

class SnapshotManager
{
    public function __construct(
        private readonly RequestCollector $requestCollector,
        private readonly QueryCollector $queryCollector,
        private readonly LynxScanner $scanner,
        private readonly SnapshotRepository $repository,
    ) {}

    /**
     * Capture current application performance state into a snapshot.
     */
    public function capture(?string $customName = null): PerformanceSnapshot
    {
        $requests = $this->requestCollector->getRequests();
        $queries = $this->queryCollector->getQueries();
        $findings = $this->scanner->scan();

        $totalRequests = count($requests);
        $totalQueries = count($queries);

        $totalDuration = 0.0;
        $routeMap = [];

        foreach ($requests as $req) {
            $duration = $req->getDurationMs();
            $totalDuration += $duration;
            $routeKey = $req->getRouteName() ?? $req->getUri();

            if (! isset($routeMap[$routeKey])) {
                $routeMap[$routeKey] = [
                    'requests' => 0,
                    'total_duration_ms' => 0.0,
                    'total_queries' => 0,
                ];
            }

            $routeMap[$routeKey]['requests']++;
            $routeMap[$routeKey]['total_duration_ms'] += $duration;
            $routeMap[$routeKey]['total_queries'] += $req->getQueryCount();
        }

        $routes = [];
        foreach ($routeMap as $routeKey => $data) {
            $routes[$routeKey] = [
                'requests' => $data['requests'],
                'average_duration_ms' => round($data['total_duration_ms'] / max(1, $data['requests']), 2),
                'average_queries' => round($data['total_queries'] / max(1, $data['requests']), 1),
            ];
        }

        $slowThreshold = (float) config('lynx.query.slow_threshold', 100.0);
        $slowQueries = count(array_filter($queries, fn (QueryRecord $q): bool => $q->getTimeMs() >= $slowThreshold));

        $scores = array_map(fn (Finding $f): float => $f->getScore(), $findings);
        $avgScore = ! empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0.0;
        $maxScore = ! empty($scores) ? max($scores) : 0.0;

        $id = $customName ?: 'snapshot_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

        $snapshot = new PerformanceSnapshot(
            id: $id,
            createdAt: new DateTimeImmutable(),
            metrics: [
                'total_requests' => $totalRequests,
                'average_request_duration_ms' => $totalRequests > 0 ? round($totalDuration / $totalRequests, 2) : 0.0,
                'total_queries' => $totalQueries,
                'slow_queries' => $slowQueries,
                'total_findings' => count($findings),
                'average_impact_score' => $avgScore,
                'max_impact_score' => $maxScore,
            ],
            routePerformance: $routes,
            findings: array_map(fn (Finding $f): array => $f->toArray(), $findings),
        );

        $this->repository->save($snapshot);

        return $snapshot;
    }
}
