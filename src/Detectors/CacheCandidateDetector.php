<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;

class CacheCandidateDetector implements DetectorContract
{
    public function __construct(
        private readonly ?int $frequencyThreshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Detect queries that are strong candidates for application-level caching.
     *
     * @param array<int, mixed> $records
     * @return list<Finding>
     */
    public function detect(array $records): array
    {
        $isEnabled = $this->enabled ?? (bool) config('lynx.cache.enabled', true);
        if (! $isEnabled) {
            return [];
        }

        $minFrequency = $this->frequencyThreshold ?? (int) config('lynx.cache.candidate_frequency_threshold', 5);
        $groups = [];

        foreach ($records as $record) {
            if (! $record instanceof QueryRecord) {
                continue;
            }

            $sql = $record->getSql();
            if (! str_starts_with(strtoupper(ltrim($sql)), 'SELECT')) {
                continue;
            }

            $key = $sql;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'count' => 0,
                    'total_time' => 0.0,
                    'sql' => $sql,
                    'connection' => $record->getConnectionName(),
                    'caller' => $record->getCaller(),
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time'] += $record->getTimeMs();
        }

        $findings = [];

        foreach ($groups as $data) {
            $count = $data['count'];
            if ($count < $minFrequency) {
                continue;
            }

            $totalTime = round($data['total_time'], 2);
            $avgDuration = round($totalTime / max(1, $count), 2);

            $severity = match (true) {
                $totalTime >= 1000.0 => Severity::High,
                $totalTime >= 250.0 => Severity::Medium,
                default => Severity::Low,
            };

            $confidence = min(0.92, 0.80 + ($count * 0.01));

            $findings[] = Finding::create(
                type: FindingType::CacheCandidate,
                severity: $severity,
                title: 'Potential cache candidate detected',
                description: sprintf('Query executed %d times with average duration %.2fms (total %.2fms).', $count, $avgDuration, $totalTime),
                evidence: [
                    'sql' => $data['sql'],
                    'occurrences' => $count,
                    'average_duration_ms' => $avgDuration,
                    'total_time_ms' => $totalTime,
                    'caller' => $data['caller'],
                    'warning' => 'Verify data freshness and invalidation requirements before caching. Caching behavior is application and business-logic dependent.',
                ],
                impact: $severity->label(),
                recommendation: 'Consider caching this result using Cache::remember(). Note: Verify data freshness requirements before caching.',
                confidence: round($confidence, 2),
                context: [
                    'connection' => $data['connection'],
                    'caller' => $data['caller'],
                    'warning' => 'Caching is business-logic dependent.',
                ],
            );
        }

        return $findings;
    }
}
