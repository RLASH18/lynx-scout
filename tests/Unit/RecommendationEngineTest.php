<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Recommendations\RecommendationEngine;
use Lynx\Scout\Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    /**
     * Test recommendation generation for N+1 finding.
     */
    public function test_recommendation_for_n_plus_one(): void
    {
        $engine = new RecommendationEngine();

        $finding = Finding::create(
            type: FindingType::NPlusOne,
            severity: Severity::High,
            title: 'N+1 Query',
            description: '142 queries',
            evidence: 'Repeated query',
        );

        $advice = $engine->advise($finding);

        $this->assertStringContainsString('eager loading', $advice->getRecommendation());
        $this->assertStringContainsString('round-trip latency', $advice->getWhy());
        $this->assertStringContainsString('Post::with', (string) $advice->getExample());

        $enriched = $engine->enrich($finding);
        $this->assertNotNull($enriched->getRecommendation());
        $this->assertStringContainsString("Recommendation:\nReview relationship loading", $enriched->getRecommendation());
        $this->assertStringContainsString("Why:", $enriched->getRecommendation());
        $this->assertStringContainsString("Example:\nPost::with", $enriched->getRecommendation());
    }

    /**
     * Test recommendation generation for slow query.
     */
    public function test_recommendation_for_slow_query(): void
    {
        $engine = new RecommendationEngine();

        $finding = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::Medium,
            title: 'Slow query',
            description: '450ms',
            evidence: 'duration 450ms',
        );

        $advice = $engine->advise($finding);

        $this->assertStringContainsString('execution plan', $advice->getRecommendation());
        $this->assertStringContainsString('indexes', $advice->getRecommendation());
        $this->assertStringContainsString('$table->index', (string) $advice->getExample());
    }

    /**
     * Test enrichAll enriches multiple findings.
     */
    public function test_enrich_all(): void
    {
        $engine = new RecommendationEngine();

        $f1 = Finding::create(FindingType::SlowQuery, Severity::Low, 'F1', 'D1', 'E1');
        $f2 = Finding::create(FindingType::DuplicateQuery, Severity::Low, 'F2', 'D2', 'E2');

        $enriched = $engine->enrichAll([$f1, $f2]);

        $this->assertCount(2, $enriched);
        $this->assertNotNull($enriched[0]->getRecommendation());
        $this->assertNotNull($enriched[1]->getRecommendation());
    }
}
