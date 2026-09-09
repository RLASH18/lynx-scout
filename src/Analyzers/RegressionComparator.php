<?php

declare(strict_types=1);

namespace Lynx\Scout\Analyzers;

use Lynx\Scout\Data\PerformanceSnapshot;

class RegressionComparator
{
    /**
     * Compare two performance snapshots and return detected regressions.
     *
     * @return array<string, mixed>
     */
    public function compare(PerformanceSnapshot $before, PerformanceSnapshot $after): array
    {
        $regThreshold = (float) config('lynx.ci.regression_threshold', 20.0);
        $queryThreshold = (float) config('lynx.ci.query_count_threshold', 30.0);

        $beforeMetrics = $before->getMetrics();
        $afterMetrics = $after->getMetrics();

        $beforeRoutes = $before->getRoutePerformance();
        $afterRoutes = $after->getRoutePerformance();

        $routeComparisons = [];
        $newRoutes = [];
        $removedRoutes = [];
        $hasRegression = false;

        $allRouteKeys = array_unique(array_merge(array_keys($beforeRoutes), array_keys($afterRoutes)));

        foreach ($allRouteKeys as $route) {
            $bData = $beforeRoutes[$route] ?? null;
            $aData = $afterRoutes[$route] ?? null;

            if ($bData === null) {
                $newRoutes[] = $route;
                continue;
            }

            if ($aData === null) {
                $removedRoutes[] = $route;
                continue;
            }

            $bDuration = (float) ($bData['average_duration_ms'] ?? 0.0);
            $aDuration = (float) ($aData['average_duration_ms'] ?? 0.0);
            $bQueries = (float) ($bData['average_queries'] ?? 0.0);
            $aQueries = (float) ($aData['average_queries'] ?? 0.0);

            $durationDeltaPct = $bDuration > 0
                ? round((($aDuration - $bDuration) / $bDuration) * 100, 1)
                : ($aDuration > 0 ? 100.0 : 0.0);

            $queryDeltaPct = $bQueries > 0
                ? round((($aQueries - $bQueries) / $bQueries) * 100, 1)
                : ($aQueries > 0 ? 100.0 : 0.0);

            $isRouteRegression = ($durationDeltaPct >= $regThreshold) || ($queryDeltaPct >= $queryThreshold);

            if ($isRouteRegression) {
                $hasRegression = true;
            }

            $routeComparisons[$route] = [
                'route' => $route,
                'before_duration_ms' => $bDuration,
                'after_duration_ms' => $aDuration,
                'duration_delta_pct' => $durationDeltaPct,
                'before_queries' => $bQueries,
                'after_queries' => $aQueries,
                'query_delta_pct' => $queryDeltaPct,
                'is_regression' => $isRouteRegression,
            ];
        }

        // Overall metrics comparison
        $bAvgReq = (float) ($beforeMetrics['average_request_duration_ms'] ?? 0.0);
        $aAvgReq = (float) ($afterMetrics['average_request_duration_ms'] ?? 0.0);
        $avgDurationDeltaPct = $bAvgReq > 0
            ? round((($aAvgReq - $bAvgReq) / $bAvgReq) * 100, 1)
            : ($aAvgReq > 0 ? 100.0 : 0.0);
        $beforeQueries = (float) ($beforeMetrics['total_queries'] ?? 0.0);
        $afterQueries = (float) ($afterMetrics['total_queries'] ?? 0.0);
        $queryDeltaPct = $beforeQueries > 0
            ? round((($afterQueries - $beforeQueries) / $beforeQueries) * 100, 1)
            : ($afterQueries > 0 ? 100.0 : 0.0);

        if ($avgDurationDeltaPct >= $regThreshold || $queryDeltaPct >= $queryThreshold) {
            $hasRegression = true;
        }

        return [
            'before_id' => $before->getId(),
            'after_id' => $after->getId(),
            'has_regression' => $hasRegression,
            'overall' => [
                'before_avg_duration_ms' => $bAvgReq,
                'after_avg_duration_ms' => $aAvgReq,
                'duration_delta_pct' => $avgDurationDeltaPct,
                'before_queries' => (int) $beforeQueries,
                'after_queries' => (int) $afterQueries,
                'query_delta_pct' => $queryDeltaPct,
                'before_findings' => (int) ($beforeMetrics['total_findings'] ?? 0),
                'after_findings' => (int) ($afterMetrics['total_findings'] ?? 0),
            ],
            'routes' => $routeComparisons,
            'new_routes' => $newRoutes,
            'removed_routes' => $removedRoutes,
        ];
    }
}
