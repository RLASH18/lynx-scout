<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Scoring\ImpactScorer;
use Lynx\Scout\Tests\TestCase;

class ImpactScorerTest extends TestCase
{
    /**
     * Test scoring calculation assigns score and impact label.
     */
    public function test_score_assignment(): void
    {
        $scorer = new ImpactScorer();

        $finding = Finding::create(
            type: FindingType::NPlusOne,
            severity: Severity::Critical,
            title: 'N+1 Query',
            description: '142 repeated queries',
            evidence: [
                'occurrences' => 142,
                'total_time_ms' => 850.0,
            ],
            confidence: 0.96,
        );

        $scored = $scorer->score($finding);

        $this->assertGreaterThanOrEqual(80.0, $scored->getScore());
        $this->assertEquals('Critical', $scored->getImpact());
    }

    /**
     * Test score ordering sorts higher impact findings first.
     */
    public function test_score_all_sorts_descending(): void
    {
        $scorer = new ImpactScorer();

        $minor = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::Low,
            title: 'Slightly slow',
            description: '105ms',
            evidence: ['duration_ms' => 105.0],
            confidence: 0.70,
        );

        $major = Finding::create(
            type: FindingType::NPlusOne,
            severity: Severity::Critical,
            title: 'Massive N+1',
            description: '200 queries',
            evidence: ['occurrences' => 200, 'total_time_ms' => 2000.0],
            confidence: 0.98,
        );

        $sorted = $scorer->scoreAll([$minor, $major]);

        $this->assertCount(2, $sorted);
        $this->assertEquals('Massive N+1', $sorted[0]->getTitle());
        $this->assertEquals('Slightly slow', $sorted[1]->getTitle());
        $this->assertGreaterThan($sorted[1]->getScore(), $sorted[0]->getScore());
    }

    /**
     * Test lower confidence yields lower impact score.
     */
    public function test_confidence_affects_score(): void
    {
        $scorer = new ImpactScorer();

        $highConf = Finding::create('test', Severity::High, 'T', 'D', ['duration_ms' => 500.0], confidence: 0.95);
        $lowConf = Finding::create('test', Severity::High, 'T', 'D', ['duration_ms' => 500.0], confidence: 0.40);

        $scoredHigh = $scorer->score($highConf);
        $scoredLow = $scorer->score($lowConf);

        $this->assertGreaterThan($scoredLow->getScore(), $scoredHigh->getScore());
    }
}
