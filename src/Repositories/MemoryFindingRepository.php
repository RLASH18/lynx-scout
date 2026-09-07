<?php

declare(strict_types=1);

namespace Lynx\Scout\Repositories;

use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\Severity;

class MemoryFindingRepository implements FindingRepositoryContract
{
    /**
     * In-memory findings list.
     *
     * @var array<string, Finding>
     */
    private array $findings = [];

    public function save(Finding $finding): void
    {
        $this->findings[$finding->getId()] = $finding;
    }

    public function saveMany(array $findings): void
    {
        foreach ($findings as $finding) {
            $this->save($finding);
        }
    }

    public function all(): array
    {
        return array_values($this->findings);
    }

    public function find(string $id): ?Finding
    {
        return $this->findings[$id] ?? null;
    }

    public function whereType(string $type): array
    {
        return array_values(array_filter($this->findings, fn (Finding $f): bool => $f->getType() === $type));
    }

    public function whereSeverity(Severity|string $severity): array
    {
        $val = $severity instanceof Severity ? $severity->value : (string) $severity;

        return array_values(array_filter($this->findings, fn (Finding $f): bool => $f->getSeverity()->value === $val));
    }

    public function clear(): void
    {
        $this->findings = [];
    }

    public function prune(int $retentionDays): int
    {
        return 0;
    }
}
