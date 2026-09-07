<?php

declare(strict_types=1);

namespace Lynx\Scout\Detectors;

use Lynx\Scout\Contracts\DetectorContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\JobRecord;
use Lynx\Scout\Data\Severity;

class QueuePerformanceDetector implements DetectorContract
{
    public function __construct(
        private readonly ?float $slowThreshold = null,
        private readonly ?bool $enabled = null,
    ) {}

    /**
     * Analyze job execution records and detect queue performance anomalies.
     *
     * @param array<int, mixed> $records
     * @return list<Finding>
     */
    public function detect(array $records): array
    {
        $isEnabled = $this->enabled ?? (bool) config('lynx.queue.enabled', true);
        if (! $isEnabled) {
            return [];
        }

        $thresholdMs = $this->slowThreshold ?? (float) config('lynx.queue.slow_job_threshold', 2000.0);
        $findings = [];
        $failuresByJob = [];

        foreach ($records as $record) {
            if (! $record instanceof JobRecord) {
                continue;
            }

            // 1. Slow job detection
            if (! $record->isFailed() && $record->getDurationMs() >= $thresholdMs) {
                $duration = $record->getDurationMs();
                $severity = match (true) {
                    $duration >= ($thresholdMs * 5) => Severity::Critical,
                    $duration >= ($thresholdMs * 2) => Severity::High,
                    default => Severity::Medium,
                };

                $findings[] = Finding::create(
                    type: FindingType::SlowJob,
                    severity: $severity,
                    title: 'Slow queue job detected',
                    description: sprintf('Job %s ran for %.2fms on queue "%s" (threshold: %.2fms).', $record->getName(), $duration, $record->getQueue(), $thresholdMs),
                    evidence: [
                        'job_name' => $record->getName(),
                        'queue' => $record->getQueue(),
                        'connection' => $record->getConnection(),
                        'duration_ms' => $duration,
                        'threshold_ms' => $thresholdMs,
                    ],
                    impact: $severity->label(),
                    recommendation: 'Break down job workload, batch operations, or optimize internal queries.',
                    confidence: 0.95,
                    context: [
                        'queue' => $record->getQueue(),
                        'connection' => $record->getConnection(),
                    ],
                );
            }

            // 2. Failed jobs tracking
            if ($record->isFailed()) {
                $name = $record->getName();
                $failuresByJob[$name] = ($failuresByJob[$name] ?? 0) + 1;
            }
        }

        // Generate findings for repeated job failures
        foreach ($failuresByJob as $jobName => $failCount) {
            if ($failCount >= 2) {
                $findings[] = Finding::create(
                    type: FindingType::HealthIssue,
                    severity: Severity::Critical,
                    title: 'Repeated queue job failures detected',
                    description: sprintf('Job %s failed %d times during execution window.', $jobName, $failCount),
                    evidence: [
                        'job_name' => $jobName,
                        'failures_count' => $failCount,
                    ],
                    impact: 'Critical',
                    recommendation: 'Inspect exception traces and ensure dead-letter/retry configuration is appropriate.',
                    confidence: 0.98,
                );
            }
        }

        return $findings;
    }
}
