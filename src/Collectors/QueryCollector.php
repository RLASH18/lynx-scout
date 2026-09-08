<?php

declare(strict_types=1);

namespace Lynx\Scout\Collectors;

use Countable;
use DateTimeImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Support\BindingSanitizer;
use Lynx\Scout\Support\CallerDetector;
use Lynx\Scout\Support\SqlNormalizer;

class QueryCollector implements Countable
{
    /**
     * Captured query records.
     *
     * @var list<QueryRecord>
     */
    private array $queries = [];

    /**
     * Whether collector is actively listening.
     */
    private bool $isCollecting = false;

    /**
     * Contextual attributes attached to captured queries (e.g., route).
     *
     * @var array<string, mixed>
     */
    private array $currentContext = [];

    /**
     * Maximum queries stored in memory to prevent memory bloat.
     */
    private int $maxStoredQueries;

    public function __construct(?int $maxStoredQueries = null)
    {
        $this->maxStoredQueries = $maxStoredQueries ?? (int) config('lynx.collectors.max_queries', 1000);
    }

    /**
     * Start observing database queries.
     */
    public function start(): void
    {
        if ($this->isCollecting) {
            return;
        }

        $this->isCollecting = true;

        DB::listen(function (QueryExecuted $event): void {
            if (! $this->isCollecting) {
                return;
            }

            $this->record($event);
        });
    }

    /**
     * Stop observing database queries.
     */
    public function stop(): void
    {
        $this->isCollecting = false;
    }

    /**
     * Check if currently listening.
     */
    public function isCollecting(): bool
    {
        return $this->isCollecting;
    }

    /**
     * Reset collected queries.
     */
    public function reset(): void
    {
        $this->queries = [];
    }

    /**
     * Set contextual information for subsequent queries.
     *
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): void
    {
        $this->currentContext = $context;
    }

    /**
     * Record a query executed event.
     */
    public function record(QueryExecuted $event): void
    {
        if (count($this->queries) >= $this->maxStoredQueries) {
            return;
        }

        $recordBindings = (bool) config('lynx.query.record_bindings', true);
        $sanitizeBindings = (bool) config('lynx.query.sanitize_bindings', true);

        $bindings = $recordBindings ? $event->bindings : [];
        if ($recordBindings && $sanitizeBindings) {
            $bindings = BindingSanitizer::sanitize($bindings);
        }

        $normalizedSql = SqlNormalizer::normalize($event->sql);
        $caller = CallerDetector::detect();

        $this->queries[] = new QueryRecord(
            sql: $event->sql,
            bindings: $bindings,
            timeMs: (float) $event->time,
            connectionName: $event->connectionName,
            executedAt: new DateTimeImmutable(),
            normalizedSql: $normalizedSql,
            caller: $caller,
            context: $this->currentContext,
        );
    }

    /**
     * Directly add a query record (useful for tests or custom collectors).
     */
    public function addRecord(QueryRecord $record): void
    {
        if (count($this->queries) >= $this->maxStoredQueries) {
            return;
        }

        $this->queries[] = $record;
    }

    /**
     * Get all recorded queries.
     *
     * @return list<QueryRecord>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Total number of collected queries.
     */
    public function count(): int
    {
        return count($this->queries);
    }

    /**
     * Total execution duration across all collected queries.
     */
    public function totalTimeMs(): float
    {
        $total = 0.0;
        foreach ($this->queries as $query) {
            $total += $query->getTimeMs();
        }

        return $total;
    }
}
