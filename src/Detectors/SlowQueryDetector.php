<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\Severity;

class SlowQueryDetector implements DetectorContract
{
    public function __construct(
        private readonly ?float $threshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Detect queries exceeding execution time threshold.
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

        $thresholdMs = $this->threshold ?? (float) config('lynx.query.slow_threshold', 100.0);
        $findings = [];

        foreach ($records as $record) {
            if (! $record instanceof QueryRecord) {
                continue;
            }

            $duration = $record->getTimeMs();
            if ($duration < $thresholdMs) {
                continue;
            }

            $severity = match (true) {
                $duration >= ($thresholdMs * 5) => Severity::Critical,
                $duration >= ($thresholdMs * 2) => Severity::High,
                default => Severity::Medium,
            };

            // Calculate confidence (higher margin over threshold yields higher confidence)
            $margin = ($duration - $thresholdMs) / max(1.0, $thresholdMs);
            $confidence = min(0.99, 0.80 + ($margin * 0.1));

            $findings[] = Finding::create(
                type: FindingType::SlowQuery,
                severity: $severity,
                title: 'Slow database query detected',
                description: sprintf('Query executed in %.2fms, exceeding threshold of %.2fms.', $duration, $thresholdMs),
                evidence: [
                    'sql' => $record->getSql(),
                    'normalized_sql' => $record->getNormalizedSql(),
                    'duration_ms' => $duration,
                    'threshold_ms' => $thresholdMs,
                    'connection' => $record->getConnectionName(),
                    'caller' => $record->getCaller(),
                    'request_id' => $record->getContext()['request_id'] ?? null,
                    'uri' => $record->getContext()['uri'] ?? null,
                ],
                impact: $severity->label(),
                recommendation: null,
                confidence: round($confidence, 2),
                context: [
                    'connection' => $record->getConnectionName(),
                    'caller' => $record->getCaller(),
                    'bindings' => $record->getBindings(),
                    'request_id' => $record->getContext()['request_id'] ?? null,
                    'uri' => $record->getContext()['uri'] ?? null,
                ],
            );
        }

        return $findings;
    }
}
