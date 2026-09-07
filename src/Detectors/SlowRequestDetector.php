<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Data\Severity;

class SlowRequestDetector implements DetectorContract
{
    public function __construct(
        private readonly ?float $threshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Detect slow HTTP requests.
     *
     * @param array<int, mixed> $records
     * @return list<Finding>
     */
    public function detect(array $records): array
    {
        $isEnabled = $this->enabled ?? (bool) config('lynx.request.enabled', true);
        if (! $isEnabled) {
            return [];
        }

        $thresholdMs = $this->threshold ?? (float) config('lynx.request.slow_threshold', 500.0);
        $findings = [];

        foreach ($records as $record) {
            if (! $record instanceof RequestRecord) {
                continue;
            }

            $duration = $record->getDurationMs();
            if ($duration < $thresholdMs) {
                continue;
            }

            $severity = match (true) {
                $duration >= ($thresholdMs * 4) => Severity::Critical,
                $duration >= ($thresholdMs * 2) => Severity::High,
                default => Severity::Medium,
            };

            $margin = ($duration - $thresholdMs) / max(1.0, $thresholdMs);
            $confidence = min(0.99, 0.85 + ($margin * 0.1));

            $findings[] = Finding::create(
                type: FindingType::SlowRequest,
                severity: $severity,
                title: 'Slow HTTP request detected',
                description: sprintf('%s %s took %.2fms (threshold: %.2fms).', $record->getMethod(), $record->getUri(), $duration, $thresholdMs),
                evidence: [
                    'uri' => $record->getUri(),
                    'method' => $record->getMethod(),
                    'route_name' => $record->getRouteName(),
                    'duration_ms' => $duration,
                    'threshold_ms' => $thresholdMs,
                    'query_count' => $record->getQueryCount(),
                    'query_time_ms' => $record->getQueryTimeMs(),
                    'memory_bytes' => $record->getMemoryBytes(),
                ],
                impact: $severity->label(),
                recommendation: 'Inspect request controller, database queries, and external service calls.',
                confidence: round($confidence, 2),
                context: [
                    'uri' => $record->getUri(),
                    'method' => $record->getMethod(),
                    'status_code' => $record->getStatusCode(),
                ],
            );
        }

        return $findings;
    }
}
