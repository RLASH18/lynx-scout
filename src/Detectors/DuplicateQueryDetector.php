<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;

class DuplicateQueryDetector implements DetectorContract
{
    public function __construct(
        private readonly ?int $threshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Detect queries executed multiple times in the same window.
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

        $minDuplicates = $this->threshold ?? (int) config('lynx.query.duplicate_threshold', 2);
        $groups = [];

        foreach ($records as $record) {
            if (! $record instanceof QueryRecord) {
                continue;
            }

            $context = $record->getContext();
            $requestId = (string) ($context['request_id'] ?? 'global');
            $key = $requestId . '|' . $record->getConnectionName() . '|' . $record->getNormalizedSql();
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'count' => 0,
                    'total_time' => 0.0,
                    'sample_sql' => $record->getSql(),
                    'normalized_sql' => $record->getNormalizedSql(),
                    'caller' => $record->getCaller(),
                    'connection' => $record->getConnectionName(),
                    'context' => $context,
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time'] += $record->getTimeMs();
            if ($record->getCaller() && ! $groups[$key]['caller']) {
                $groups[$key]['caller'] = $record->getCaller();
            }
        }

        $findings = [];

        foreach ($groups as $pattern => $data) {
            $count = $data['count'];
            if ($count < $minDuplicates) {
                continue;
            }

            $severity = match (true) {
                $count >= 20 => Severity::Critical,
                $count >= 10 => Severity::High,
                $count >= 5 => Severity::Medium,
                default => Severity::Low,
            };

            $totalTime = round($data['total_time'], 2);

            $findings[] = Finding::create(
                type: FindingType::DuplicateQuery,
                severity: $severity,
                title: 'Duplicate database queries detected',
                description: sprintf('Query pattern executed %d times totaling %.2fms.', $count, $totalTime),
                evidence: [
                    'query_pattern' => $data['normalized_sql'],
                    'sample_sql' => $data['sample_sql'],
                    'occurrences' => $count,
                    'total_time_ms' => $totalTime,
                    'caller' => $data['caller'],
                    'threshold' => $minDuplicates,
                    'request_id' => $data['context']['request_id'] ?? null,
                ],
                impact: $severity->label(),
                recommendation: null,
                confidence: 0.98,
                context: array_merge($data['context'], [
                    'caller' => $data['caller'],
                    'connection' => $data['connection'],
                    'request_id' => $data['context']['request_id'] ?? null,
                ]),
            );
        }

        return $findings;
    }
}
