<?php

declare(strict_types=1);

namespace Lynx\Scout\Reports;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\Severity;

class ReportGenerator
{
    private const DIVIDER = '────────────────────────────────';

    /**
     * Generate human-readable performance report.
     *
     * @param list<Finding> $findings
     */
    public function generate(array $findings): string
    {
        $lines = [];
        $lines[] = 'Lynx Scout Performance Report';
        $lines[] = self::DIVIDER;
        $lines[] = '';

        if (empty($findings)) {
            $lines[] = 'Healthy';
            $lines[] = self::DIVIDER;
            $lines[] = 'No performance issues detected. Application runtime is healthy.';
            $lines[] = '';
            $lines[] = self::DIVIDER;
            return implode("\n", $lines);
        }

        // Group by severity
        $grouped = [
            Severity::Critical->value => [],
            Severity::High->value => [],
            Severity::Medium->value => [],
            Severity::Low->value => [],
            Severity::Info->value => [],
        ];

        foreach ($findings as $finding) {
            $sev = $finding->getSeverity()->value;
            $grouped[$sev][] = $finding;
        }

        foreach ($grouped as $severityKey => $groupFindings) {
            if (empty($groupFindings)) {
                continue;
            }

            $severityLabel = ucfirst($severityKey);
            $lines[] = $severityLabel;
            $lines[] = self::DIVIDER;

            foreach ($groupFindings as $f) {
                $lines[] = $f->getTitle();
                $lines[] = '';

                $evidence = $f->getEvidence();
                $route = null;
                $occurrences = null;
                $duration = null;

                if (is_array($evidence)) {
                    $route = $evidence['route'] ?? ($evidence['uri'] ?? null);
                    $occurrences = $evidence['occurrences'] ?? null;
                    $duration = $evidence['duration_ms'] ?? ($evidence['total_time_ms'] ?? null);
                }

                if ($route !== null) {
                    $lines[] = "Route: {$route}";
                }

                if ($occurrences !== null) {
                    $lines[] = "Occurrences: {$occurrences}";
                }

                if ($duration !== null) {
                    $lines[] = sprintf("Duration: %.2fms", (float) $duration);
                }

                $lines[] = sprintf("Estimated Impact: %s (Score: %.1f)", $f->getImpact(), $f->getScore());
                $lines[] = sprintf("Confidence: %s (%d%%)", $f->getConfidenceLabel(), (int) ($f->getConfidence() * 100));

                if ($f->getRecommendation() !== null) {
                    $lines[] = "Recommendation: {$f->getRecommendation()}";
                }

                $lines[] = self::DIVIDER;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}
