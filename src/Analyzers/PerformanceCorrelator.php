<?php

declare(strict_types=1);

namespace Lynx\Scout\Analyzers;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Data\Severity;

class PerformanceCorrelator
{
    /**
     * Correlate requests, queries, and existing findings to identify likely root causes.
     *
     * @param list<RequestRecord> $requests
     * @param list<QueryRecord> $queries
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public function correlate(array $requests, array $queries, array $findings): array
    {
        $correlatedFindings = [];

        foreach ($requests as $request) {
            $duration = $request->getDurationMs();
            $queryTime = $request->getQueryTimeMs();
            $queryCount = $request->getQueryCount();
            $appTime = max(0.0, $duration - $queryTime);
            $dbPercentage = $duration > 0 ? round(($queryTime / $duration) * 100, 1) : 0.0;

            // Only correlate requests that show performance friction or high query counts
            $isSlow = $duration >= (float) config('lynx.request.slow_threshold', 500.0);
            $hasHighQueries = $queryCount >= 10;

            if (! $isSlow && ! $hasHighQueries) {
                continue;
            }

            // Find related findings that match this request's route or URI
            $routeUri = $request->getUri();
            $requestId = $request->getId();
            $matchingFindings = array_values(array_filter(
                $findings,
                function (Finding $f) use ($routeUri, $requestId): bool {
                    $evidence = $f->getEvidence();
                    if (is_array($evidence) && isset($evidence['request_id'])) {
                        return $evidence['request_id'] === $requestId;
                    }

                    $context = $f->getContext();
                    if (isset($context['request_id'])) {
                        return $context['request_id'] === $requestId;
                    }

                    if (is_array($evidence) && isset($evidence['route']) && $evidence['route'] === $routeUri) {
                        return true;
                    }
                    return isset($context['uri']) && $context['uri'] === $routeUri;
                }
            ));

            $hasNPlusOne = false;
            $hasSlowQuery = false;
            $relatedFindingIds = [];

            foreach ($matchingFindings as $mf) {
                $relatedFindingIds[] = $mf->getId();
                if ($mf->getType() === FindingType::NPlusOne->value) {
                    $hasNPlusOne = true;
                }
                if ($mf->getType() === FindingType::SlowQuery->value) {
                    $hasSlowQuery = true;
                }
            }

            // Case 1: High Database time + N+1
            if ($dbPercentage >= 50.0 && $hasNPlusOne) {
                $diagnosis = 'Strong indication: N+1 relational query loops are the primary bottleneck.';
                $description = sprintf(
                    'Route %s %s spent %.1f%% (%.2fms) of its %.2fms execution time running %d database queries driven by N+1 patterns.',
                    $request->getMethod(),
                    $routeUri,
                    $dbPercentage,
                    $queryTime,
                    $duration,
                    $queryCount
                );
                $severity = Severity::Critical;
                $confidence = 0.94;
                $recommendation = 'Eager load relationships before rendering or processing this endpoint.';
            }
            // Case 2: High Database time + Slow Query
            elseif ($dbPercentage >= 50.0 && $hasSlowQuery) {
                $diagnosis = 'Likely cause: Heavy database query execution is blocking request completion.';
                $description = sprintf(
                    'Route %s %s spent %.1f%% (%.2fms) of its %.2fms runtime on database operations with isolated slow queries.',
                    $request->getMethod(),
                    $routeUri,
                    $dbPercentage,
                    $queryTime,
                    $duration
                );
                $severity = Severity::High;
                $confidence = 0.90;
                $recommendation = 'Inspect execution plan and missing indexes on the detected slow queries.';
            }
            // Case 3: High App Time (Low DB Time)
            elseif ($dbPercentage <= 30.0 && $isSlow) {
                $diagnosis = 'Likely cause: Application CPU processing or external service latency.';
                $description = sprintf(
                    'Route %s %s took %.2fms, but database queries only accounted for %.1f%% (%.2fms). High application processing time (%.2fms) observed.',
                    $request->getMethod(),
                    $routeUri,
                    $duration,
                    $dbPercentage,
                    $queryTime,
                    $appTime
                );
                $severity = Severity::High;
                $confidence = 0.85;
                $recommendation = 'Profile application business logic, heavy loops, file I/O, or external HTTP calls.';
            }
            // Case 4: High Query Count general overhead
            elseif ($queryCount >= 20) {
                $diagnosis = 'Possible cause: Excessive database roundtrips.';
                $description = sprintf(
                    'Route %s %s executed %d queries in a single request, creating noticeable connection and serialization overhead.',
                    $request->getMethod(),
                    $routeUri,
                    $queryCount
                );
                $severity = Severity::Medium;
                $confidence = 0.80;
                $recommendation = 'Batch database operations, implement caching, or consolidate queries.';
            } else {
                continue;
            }

            $correlatedFindings[] = Finding::create(
                type: FindingType::CorrelatedIssue,
                severity: $severity,
                title: sprintf('Correlated Bottleneck: %s %s', $request->getMethod(), $routeUri),
                description: $description,
                evidence: [
                    'uri' => $routeUri,
                    'method' => $request->getMethod(),
                    'duration_ms' => $duration,
                    'database_time_ms' => $queryTime,
                    'application_time_ms' => $appTime,
                    'database_percentage' => $dbPercentage,
                    'query_count' => $queryCount,
                    'diagnosis' => $diagnosis,
                    'related_finding_ids' => $relatedFindingIds,
                ],
                impact: $severity->label(),
                recommendation: $recommendation,
                confidence: $confidence,
                context: [
                    'uri' => $routeUri,
                    'route_name' => $request->getRouteName(),
                    'diagnosis' => $diagnosis,
                ],
            );
        }

        return $correlatedFindings;
    }
}
