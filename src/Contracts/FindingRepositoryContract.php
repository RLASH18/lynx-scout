<?php

declare(strict_types=1);

namespace Lynx\Scout\Contracts;

use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\Severity;

interface FindingRepositoryContract
{
    /**
     * Store a finding, aggregating occurrences if already detected.
     */
    public function save(Finding $finding): void;

    /**
     * Store multiple findings.
     *
     * @param list<Finding> $findings
     */
    public function saveMany(array $findings): void;

    /**
     * Retrieve all stored findings.
     *
     * @return list<Finding>
     */
    public function all(): array;

    /**
     * Find a specific finding by ID.
     */
    public function find(string $id): ?Finding;

    /**
     * Filter findings by type.
     *
     * @return list<Finding>
     */
    public function whereType(string $type): array;

    /**
     * Filter findings by severity.
     *
     * @return list<Finding>
     */
    public function whereSeverity(Severity|string $severity): array;

    /**
     * Clear all stored findings.
     */
    public function clear(): void;

    /**
     * Prune findings older than given retention days.
     */
    public function prune(int $retentionDays): int;
}
