<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

use DateTimeImmutable;
use JsonSerializable;

class RequestRecord implements JsonSerializable
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $id,
        private readonly string $method,
        private readonly string $uri,
        private readonly ?string $routeName,
        private readonly ?string $action,
        private readonly float $durationMs,
        private readonly int $statusCode,
        private readonly int $queryCount,
        private readonly float $queryTimeMs,
        private readonly int $memoryBytes,
        private readonly DateTimeImmutable $requestedAt,
        private readonly array $context = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getQueryTimeMs(): float
    {
        return $this->queryTimeMs;
    }

    public function getMemoryBytes(): int
    {
        return $this->memoryBytes;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'uri' => $this->uri,
            'route_name' => $this->routeName,
            'action' => $this->action,
            'duration_ms' => $this->durationMs,
            'status_code' => $this->statusCode,
            'query_count' => $this->queryCount,
            'query_time_ms' => $this->queryTimeMs,
            'memory_bytes' => $this->memoryBytes,
            'requested_at' => $this->requestedAt->format(DateTimeImmutable::ATOM),
            'context' => $this->context,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
