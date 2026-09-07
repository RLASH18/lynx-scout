<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

use DateTimeImmutable;
use JsonSerializable;

class QueryRecord implements JsonSerializable
{
    /**
     * @param array<int|string, mixed> $bindings
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $bindings,
        private readonly float $timeMs,
        private readonly string $connectionName,
        private readonly DateTimeImmutable $executedAt,
        private readonly ?string $normalizedSql = null,
        private readonly ?string $caller = null,
        private readonly array $context = [],
    ) {}

    /**
     * Get raw SQL statement.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get parameterized query bindings.
     *
     * @return array<int|string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get execution duration in milliseconds.
     */
    public function getTimeMs(): float
    {
        return $this->timeMs;
    }

    /**
     * Get database connection name.
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Get execution timestamp.
     */
    public function getExecutedAt(): DateTimeImmutable
    {
        return $this->executedAt;
    }

    /**
     * Get normalized SQL statement without literal parameters.
     */
    public function getNormalizedSql(): string
    {
        return $this->normalizedSql ?? $this->sql;
    }

    /**
     * Get detected application source caller.
     */
    public function getCaller(): ?string
    {
        return $this->caller;
    }

    /**
     * Get contextual request or job information.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert record to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'normalized_sql' => $this->getNormalizedSql(),
            'bindings' => $this->bindings,
            'time_ms' => $this->timeMs,
            'connection' => $this->connectionName,
            'caller' => $this->caller,
            'executed_at' => $this->executedAt->format(DateTimeImmutable::ATOM),
            'context' => $this->context,
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
