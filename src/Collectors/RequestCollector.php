<?php

declare(strict_types=1);

namespace Lynx\Scout\Collectors;

use Countable;
use Lynx\Scout\Data\RequestRecord;

class RequestCollector implements Countable
{
    /**
     * Stored request records.
     *
     * @var list<RequestRecord>
     */
    private array $requests = [];

    /**
     * Maximum stored requests in memory.
     */
    private int $maxStoredRequests;

    public function __construct(?int $maxStoredRequests = null)
    {
        $this->maxStoredRequests = max(1, $maxStoredRequests ?? (int) config('lynx.collectors.max_requests', 500));
    }

    /**
     * Record an observed HTTP request.
     */
    public function record(RequestRecord $record): void
    {
        if (count($this->requests) >= $this->maxStoredRequests) {
            array_shift($this->requests);
        }

        $this->requests[] = $record;
    }

    /**
     * Get all collected request records.
     *
     * @return list<RequestRecord>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Total number of collected requests.
     */
    public function count(): int
    {
        return count($this->requests);
    }

    /**
     * Clear collected requests.
     */
    public function reset(): void
    {
        $this->requests = [];
    }

    /**
     * Find requests exceeding a duration threshold.
     *
     * @return list<RequestRecord>
     */
    public function findSlowRequests(float $thresholdMs): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (RequestRecord $req): bool => $req->getDurationMs() >= $thresholdMs
        ));
    }
}
