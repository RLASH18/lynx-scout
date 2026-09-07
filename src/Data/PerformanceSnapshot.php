<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

use DateTimeImmutable;
use JsonSerializable;

class PerformanceSnapshot implements JsonSerializable
{
    /**
     * @param array<string, mixed> $metrics
     * @param array<string, array<string, mixed>> $routePerformance
     * @param list<array<string, mixed>> $findings
     */
    public function __construct(
        private readonly string $id,
        private readonly DateTimeImmutable $createdAt,
        private readonly array $metrics,
        private readonly array $routePerformance = [],
        private readonly array $findings = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRoutePerformance(): array
    {
        return $this->routePerformance;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'metrics' => $this->metrics,
            'routes' => $this->routePerformance,
            'findings' => $this->findings,
        ];
    }

    /**
     * Reconstruct snapshot from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? uniqid('snap_')),
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable((string) $data['created_at'])
                : new DateTimeImmutable(),
            metrics: (array) ($data['metrics'] ?? []),
            routePerformance: (array) ($data['routes'] ?? []),
            findings: (array) ($data['findings'] ?? []),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
