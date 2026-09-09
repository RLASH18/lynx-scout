<?php

declare(strict_types=1);

namespace Lynx\Scout\Collectors;

use Countable;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Lynx\Scout\Data\JobRecord;

class QueueCollector implements Countable
{
    /**
     * Track job start timestamps by job ID.
     *
     * @var array<string, float>
     */
    private array $jobStarts = [];

    /**
     * Stored job execution records.
     *
     * @var list<JobRecord>
     */
    private array $jobs = [];

    /**
     * Maximum stored job records in memory.
     */
    private int $maxStoredJobs;

    public function __construct(?int $maxStoredJobs = null)
    {
        $this->maxStoredJobs = max(1, $maxStoredJobs ?? (int) config('lynx.collectors.max_queue_jobs', 500));
    }

    /**
     * Register queue event listeners.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            $id = $event->job->getJobId() ?: spl_object_hash($event->job);
            $this->jobStarts[$id] = microtime(true);
        });

        $events->listen(JobProcessed::class, function (JobProcessed $event): void {
            $id = $event->job->getJobId() ?: spl_object_hash($event->job);
            $durationMs = isset($this->jobStarts[$id])
                ? round((microtime(true) - $this->jobStarts[$id]) * 1000, 2)
                : 0.0;
            unset($this->jobStarts[$id]);

            $this->record(new JobRecord(
                id: (string) $id,
                name: $event->job->resolveName(),
                connection: $event->connectionName,
                queue: $event->job->getQueue(),
                durationMs: $durationMs,
                failed: false,
                executedAt: new DateTimeImmutable(),
            ));
        });

        $events->listen(JobFailed::class, function (JobFailed $event): void {
            $id = $event->job->getJobId() ?: spl_object_hash($event->job);
            $durationMs = isset($this->jobStarts[$id])
                ? round((microtime(true) - $this->jobStarts[$id]) * 1000, 2)
                : 0.0;
            unset($this->jobStarts[$id]);

            $this->record(new JobRecord(
                id: (string) $id,
                name: $event->job->resolveName(),
                connection: $event->connectionName,
                queue: $event->job->getQueue(),
                durationMs: $durationMs,
                failed: true,
                exceptionMessage: $event->exception->getMessage(),
                executedAt: new DateTimeImmutable(),
            ));
        });
    }

    /**
     * Record a job execution.
     */
    public function record(JobRecord $job): void
    {
        if (count($this->jobs) >= $this->maxStoredJobs) {
            array_shift($this->jobs);
        }

        $this->jobs[] = $job;
    }

    /**
     * Get all collected job records.
     *
     * @return list<JobRecord>
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Total number of collected jobs.
     */
    public function count(): int
    {
        return count($this->jobs);
    }

    /**
     * Count total failed jobs.
     */
    public function getFailedCount(): int
    {
        return count(array_filter($this->jobs, fn (JobRecord $j): bool => $j->isFailed()));
    }

    /**
     * Clear recorded jobs.
     */
    public function reset(): void
    {
        $this->jobs = [];
        $this->jobStarts = [];
    }
}
