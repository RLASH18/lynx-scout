<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;

class NPlusOneDetector implements DetectorContract
{
    public function __construct(
        private readonly ?int $threshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Detect N+1 query patterns from query records.
     *
     * @param array<int, mixed> $records
     * @return list<Finding>
     */
    public function detect(array $records): array
    {
        $isEnabled = $this->enabled ?? (bool) config('lynx.query.enabled', true);
        if (! $isEnabled) {
            return [];
        }

        $minExecutions = $this->threshold ?? (int) config('lynx.query.n_plus_one_threshold', 3);

        $patterns = [];

        foreach ($records as $record) {
            if (! $record instanceof QueryRecord) {
                continue;
            }

            $sql = $record->getSql();
            if (! str_starts_with(strtoupper(ltrim($sql)), 'SELECT')) {
                continue;
            }

            $context = $record->getContext();
            $requestId = (string) ($context['request_id'] ?? 'global');
            $pattern = $record->getNormalizedSql();
            $groupKey = $requestId . '|' . $record->getConnectionName() . '|' . $pattern;

            // Look for typical relational query indicators:
            // e.g. "where `id` = ?" or "where `user_id` = ?" or "where `..._id` in (?)"
            $isRelationalPattern = (bool) preg_match(
                '/where\s+[`"]?(\w+)?_?id[`"]?\s*(=|in)\s*(\?|\(\?\))/i',
                $pattern
            );

            if (! isset($patterns[$groupKey])) {
                $patterns[$groupKey] = [
                    'count' => 0,
                    'total_time' => 0.0,
                    'sample_sql' => $sql,
                    'normalized_sql' => $pattern,
                    'is_relational' => $isRelationalPattern,
                    'caller' => $record->getCaller(),
                    'context' => $context,
                    'connection' => $record->getConnectionName(),
                ];
            }

            $patterns[$groupKey]['count']++;
            $patterns[$groupKey]['total_time'] += $record->getTimeMs();
            if ($record->getCaller() && ! $patterns[$groupKey]['caller']) {
                $patterns[$groupKey]['caller'] = $record->getCaller();
            }
        }

        $findings = [];

        foreach ($patterns as $pattern => $data) {
            $count = $data['count'];
            if ($count < $minExecutions) {
                continue;
            }

            // High probability when relational pattern match or high frequency
            $confidence = $data['is_relational']
                ? min(0.98, 0.90 + ($count * 0.005))
                : min(0.85, 0.70 + ($count * 0.005));

            // Must have decent probability to be classified as N+1
            if ($confidence < 0.75 && ! $data['is_relational']) {
                continue;
            }

            $severity = match (true) {
                $count >= 50 => Severity::Critical,
                $count >= 15 => Severity::High,
                default => Severity::Medium,
            };

            $totalTime = round($data['total_time'], 2);
            $route = $data['context']['route'] ?? ($data['context']['uri'] ?? null);

            $findings[] = Finding::create(
                type: FindingType::NPlusOne,
                severity: $severity,
                title: 'N+1 query pattern detected',
                description: sprintf('Repeated relationship query executed %d times totaling %.2fms.', $count, $totalTime),
                evidence: [
                    'query_pattern' => $data['normalized_sql'],
                    'sample_sql' => $data['sample_sql'],
                    'occurrences' => $count,
                    'total_time_ms' => $totalTime,
                    'route' => $route,
                    'caller' => $data['caller'],
                    'threshold' => $minExecutions,
                    'request_id' => $data['context']['request_id'] ?? null,
                ],
                impact: $severity->label(),
                recommendation: 'Review relationship loading and consider eager loading.',
                confidence: round($confidence, 2),
                context: array_merge($data['context'], [
                    'caller' => $data['caller'],
                    'request_id' => $data['context']['request_id'] ?? null,
                    'connection' => $data['connection'],
                ]),
            );
        }

        return $findings;
    }
}
