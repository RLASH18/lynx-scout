<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

use DateTimeImmutable;
use JsonSerializable;

class JobRecord implements JsonSerializable
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $connection,
        private readonly string $queue,
        private readonly float $durationMs,
        private readonly bool $failed = false,
        private readonly ?string $exceptionMessage = null,
        private readonly ?DateTimeImmutable $executedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    public function getExecutedAt(): DateTimeImmutable
    {
        return $this->executedAt ?? new DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'duration_ms' => $this->durationMs,
            'failed' => $this->failed,
            'exception_message' => $this->exceptionMessage,
            'executed_at' => $this->getExecutedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
