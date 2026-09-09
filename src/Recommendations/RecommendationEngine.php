<?php

declare(strict_types=1);

namespace Lynx\Scout\Recommendations;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;

class RecommendationEngine
{
    /**
     * Generate structured advice for a given finding.
     */
    public function advise(Finding $finding): RecommendationAdvice
    {
        $type = $finding->getTypeEnum() ?? FindingType::tryFrom($finding->getType());

        return match ($type) {
            FindingType::NPlusOne => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: 'Review relationship loading and consider eager loading.',
                why: 'The same relationship query is executed repeatedly in loops, adding unnecessary round-trip latency to the database.',
                example: "Post::with('author')->get();",
            ),
            FindingType::SlowQuery => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: 'Inspect the query execution plan and review indexes on filtering and joining columns.',
                why: 'Long-running database queries hold connection threads and often indicate unindexed columns or missing composite keys.',
                example: "\$table->index(['status', 'created_at']);",
            ),
            FindingType::DuplicateQuery => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: 'Review repeated database access and consider reducing redundant queries or caching results.',
                why: 'Executing identical or near-identical queries repeatedly within the same request consumes unnecessary database CPU and I/O.',
                example: "Cache::remember('key', 60, fn () => Model::find(\$id));",
            ),
            FindingType::SlowRequest => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: 'Inspect request controller logic, database queries, and external HTTP integrations.',
                why: 'Slow endpoints directly degrade user experience and consume web worker concurrency pools.',
                example: "ProcessHeavyDataJob::dispatch(\$payload);",
            ),
            FindingType::CacheCandidate => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: 'Consider caching this query result after verifying business freshness requirements.',
                why: 'This query executes with high frequency and represents an ideal caching opportunity.',
                example: "Cache::remember('active_products', 3600, fn () => Product::where('active', 1)->get());",
            ),
            FindingType::HealthIssue => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: $finding->getRecommendation() ?? 'Review production performance configuration.',
                why: 'Misconfigured caching or debug settings create significant memory and response overhead in production.',
                example: "php artisan config:cache && php artisan route:cache",
            ),
            FindingType::CorrelatedIssue => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: $finding->getRecommendation() ?? 'Address the correlated primary bottleneck first.',
                why: 'Targeting the correlated root cause yields the largest measurable reduction in overall request duration.',
                example: null,
            ),
            default => new RecommendationAdvice(
                findingTitle: $finding->getTitle(),
                recommendation: $finding->getRecommendation() ?? 'Review observed performance metrics and trace execution.',
                why: 'Performance anomalies consume system resources and risk compounding under concurrent traffic.',
                example: null,
            ),
        };
    }

    /**
     * Enrich a finding with recommendation advice if not already set.
     */
    public function enrich(Finding $finding): Finding
    {
        if (! (bool) config('lynx.recommendations.enabled', true)) {
            return $finding;
        }

        if ($finding->getRecommendation() !== null) {
            return $finding;
        }

        $advice = $this->advise($finding);

        return $finding->withRecommendation($advice->format());
    }

    /**
     * Enrich a list of findings.
     *
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public function enrichAll(array $findings): array
    {
        return array_map(fn (Finding $f): Finding => $this->enrich($f), $findings);
    }
}
