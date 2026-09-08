<?php

declare(strict_types=1);

namespace Lynx\Scout\Scoring;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\Severity;

class ImpactScorer
{
    /** @var array<string, float> */
    private readonly array $weights;

    /**
     * @param array<string, float>|null $weights
     */
    public function __construct(?array $weights = null)
    {
        $this->weights = $weights ?? (array) config('lynx.scoring.weights', []);
    }

    /**
     * Calculate impact score for a finding and return a scored copy.
     * Note: The score is an estimation for developer prioritization, not a physical constant.
     */
    public function score(Finding $finding): Finding
    {
        $severity = $finding->getSeverity();
        $confidence = $finding->getConfidence();
        $evidence = $finding->getEvidence();

        // 1. Base cost determined by baseline severity weight
        $baseWeight = match ($severity) {
            Severity::Critical => (float) ($this->weights['critical'] ?? 45.0),
            Severity::High => (float) ($this->weights['high'] ?? 35.0),
            Severity::Medium => (float) ($this->weights['medium'] ?? 25.0),
            Severity::Low => (float) ($this->weights['low'] ?? 15.0),
            Severity::Info => (float) ($this->weights['info'] ?? 5.0),
        };

        // 2. Duration / time penalty
        $timeMs = 0.0;
        if (is_array($evidence)) {
            $timeMs = (float) ($evidence['total_time_ms'] ?? ($evidence['duration_ms'] ?? 0.0));
        }
        $timeBonus = $timeMs > 0 ? min(30.0, log10(max(10.0, $timeMs)) * 10.0) : 0.0;

        // 3. Frequency penalty
        $occurrences = 1;
        if (is_array($evidence)) {
            $occurrences = (int) ($evidence['occurrences'] ?? ($evidence['query_count'] ?? 1));
        }
        $freqBonus = $occurrences > 1 ? min(25.0, log10(max(1.0, (float) $occurrences)) * 12.0) : 0.0;

        $rawScore = ($baseWeight + $timeBonus + $freqBonus);
        $finalScore = round(min(100.0, max(1.0, $rawScore * $confidence)), 1);

        $impactLabel = match (true) {
            $finalScore >= 80.0 => 'Critical',
            $finalScore >= 60.0 => 'High',
            $finalScore >= 40.0 => 'Medium',
            $finalScore >= 20.0 => 'Low',
            default => 'Info',
        };

        return $finding->withScore($finalScore, $impactLabel);
    }

    /**
     * Score a list of findings and return them sorted by impact score descending.
     *
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public function scoreAll(array $findings): array
    {
        $scored = array_map(fn (Finding $f): Finding => $this->score($f), $findings);

        usort($scored, fn (Finding $a, Finding $b): int => $b->getScore() <=> $a->getScore());

        return $scored;
    }
}
