<?php

declare(strict_types=1);

namespace Lynx\Scout\Repositories;

use DateTimeImmutable;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;

class FileFindingRepository implements FindingRepositoryContract
{
    private readonly string $filePath;

    private readonly string $lockPath;

    private readonly int $maxFindings;

    public function __construct(
        ?string $storagePath = null,
        int $maxFindings = 500,
    ) {
        $path = $storagePath ?? (string) config('lynx.storage.path', storage_path('lynx'));
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $this->filePath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'findings.json';
        $this->lockPath = $this->filePath . '.lock';
        $this->maxFindings = max(1, $maxFindings);
    }

    /**
     * Store a finding, aggregating with existing finding if matched by fingerprint.
     */
    public function save(Finding $finding): void
    {
        $this->saveMany([$finding]);
    }

    /**
     * Store multiple findings with aggregation.
     *
     * @param list<Finding> $findings
     */
    public function saveMany(array $findings): void
    {
        if (empty($findings)) {
            return;
        }

        $lock = fopen($this->lockPath, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new \RuntimeException('Unable to lock Lynx Scout finding storage.');
        }

        try {
            $stored = $this->loadRaw();
        $fingerprintMap = [];

        foreach ($stored as $idx => $item) {
            $fp = $item['fingerprint'] ?? null;
            if ($fp !== null) {
                $fingerprintMap[$fp] = $idx;
            }
        }

        foreach ($findings as $finding) {
            $fp = $this->fingerprint($finding);
            $now = new DateTimeImmutable();

            if (isset($fingerprintMap[$fp])) {
                $idx = $fingerprintMap[$fp];
                $occurrenceCount = ($stored[$idx]['occurrence_count'] ?? 1) + 1;
                $firstDetectedAt = $stored[$idx]['first_detected_at'] ?? ($stored[$idx]['detected_at'] ?? $now->format(DateTimeImmutable::ATOM));
                $historicalId = $stored[$idx]['id'] ?? $finding->getId();
                $stored[$idx] = array_replace($stored[$idx], $finding->toArray());
                $stored[$idx]['id'] = $historicalId;
                $stored[$idx]['first_detected_at'] = $firstDetectedAt;
                $stored[$idx]['occurrence_count'] = $occurrenceCount;
                $stored[$idx]['last_detected_at'] = $now->format(DateTimeImmutable::ATOM);
            } else {
                $data = $finding->toArray();
                $data['fingerprint'] = $fp;
                $data['occurrence_count'] = 1;
                $data['first_detected_at'] = $finding->getDetectedAt()->format(DateTimeImmutable::ATOM);
                $data['last_detected_at'] = $now->format(DateTimeImmutable::ATOM);
                $stored[] = $data;
                $fingerprintMap[$fp] = count($stored) - 1;
            }
        }

            $retentionDays = (int) config('lynx.storage.retention_days', 0);
            if ($retentionDays > 0) {
                $cutoff = (new DateTimeImmutable())->modify("-{$retentionDays} days");
                $stored = array_values(array_filter($stored, function (mixed $item) use ($cutoff): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    try {
                        $lastDetectedAt = new DateTimeImmutable((string) ($item['last_detected_at'] ?? $item['detected_at'] ?? 'now'));
                    } catch (\Throwable) {
                        return true;
                    }

                    return $lastDetectedAt >= $cutoff;
                }));
            }

            // Limit size to maxFindings.
            if (count($stored) > $this->maxFindings) {
                $stored = array_slice($stored, -$this->maxFindings);
            }

            $this->writeRaw($stored);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Retrieve all stored findings reconstituted as Finding objects.
     *
     * @return list<Finding>
     */
    public function all(): array
    {
        $raw = $this->loadRaw();
        $findings = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            try {
                $findings[] = $this->hydrate($item);
            } catch (\Throwable) {
                continue;
            }
        }

        return $findings;
    }

    /**
     * Find a finding by its ID.
     */
    public function find(string $id): ?Finding
    {
        foreach ($this->loadRaw() as $item) {
            if (is_array($item) && ($item['id'] ?? '') === $id) {
                try {
                    return $this->hydrate($item);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Filter findings by category type.
     *
     * @return list<Finding>
     */
    public function whereType(string $type): array
    {
        return array_values(array_filter($this->all(), fn (Finding $f): bool => $f->getType() === $type));
    }

    /**
     * Filter findings by severity level.
     *
     * @return list<Finding>
     */
    public function whereSeverity(Severity|string $severity): array
    {
        $val = $severity instanceof Severity ? $severity->value : (string) $severity;

        return array_values(array_filter($this->all(), fn (Finding $f): bool => $f->getSeverity()->value === $val));
    }

    /**
     * Clear all stored findings.
     */
    public function clear(): void
    {
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    /**
     * Prune records older than the given retention days.
     */
    public function prune(int $retentionDays): int
    {
        $raw = $this->loadRaw();
        $cutoff = (new DateTimeImmutable())->modify("-{$retentionDays} days");
        $filtered = [];
        $prunedCount = 0;

        foreach ($raw as $item) {
            $lastDetected = isset($item['last_detected_at'])
                ? new DateTimeImmutable($item['last_detected_at'])
                : new DateTimeImmutable();

            if ($lastDetected < $cutoff) {
                $prunedCount++;
            } else {
                $filtered[] = $item;
            }
        }

        $this->writeRaw($filtered);

        return $prunedCount;
    }

    /**
     * Compute a deduplicating fingerprint for a finding.
     */
    private function fingerprint(Finding $finding): string
    {
        $context = $finding->getContext();
        $uri = $context['uri'] ?? ($context['route'] ?? '');
        $caller = $context['caller'] ?? '';

        $evidence = $finding->getEvidence();
        $identity = [
            'type' => $finding->getType(),
            'title' => $finding->getTitle(),
            'uri' => $uri,
            'caller' => $caller,
            'connection' => is_array($evidence) ? ($evidence['connection'] ?? ($context['connection'] ?? '')) : ($context['connection'] ?? ''),
            'query_pattern' => is_array($evidence) ? ($evidence['query_pattern'] ?? ($evidence['normalized_sql'] ?? '')) : '',
            'job_name' => is_array($evidence) ? ($evidence['job_name'] ?? '') : '',
        ];

        return hash('sha256', json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRaw(): array
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        if (function_exists('json_validate') && ! json_validate($content)) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function writeRaw(array $data): void
    {
        $dir = dirname($this->filePath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return;
            }
        }

        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $temporaryPath = $this->filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $written = file_put_contents($temporaryPath, $encoded, LOCK_EX) !== false;
        $renamed = $written && @rename($temporaryPath, $this->filePath);
        if (! $renamed && $written && file_exists($this->filePath)) {
            @unlink($this->filePath);
            $renamed = @rename($temporaryPath, $this->filePath);
        }

        if (! $renamed) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to persist Lynx Scout findings.');
        }
    }

    /**
     * Hydrate a raw array item into a Finding instance.
     *
     * @param array<string, mixed> $item
     */
    private function hydrate(array $item): Finding
    {
        if (empty($item['title']) && empty($item['id'])) {
            throw new \InvalidArgumentException('Malformed finding record: missing title and id.');
        }

        $severityVal = $item['severity'] ?? 'medium';
        $severity = Severity::tryFrom($severityVal) ?? Severity::Medium;
        $typeVal = $item['type'] ?? 'slow_query';
        $type = FindingType::tryFrom($typeVal) ?? $typeVal;

        $detectedAt = isset($item['detected_at'])
            ? new DateTimeImmutable($item['detected_at'])
            : new DateTimeImmutable();

        $context = is_array($item['context'] ?? null) ? $item['context'] : [];
        if (isset($item['occurrence_count'])) {
            $context['occurrence_count'] = $item['occurrence_count'];
        }
        if (isset($item['first_detected_at'])) {
            $context['first_detected_at'] = $item['first_detected_at'];
        }
        if (isset($item['last_detected_at'])) {
            $context['last_detected_at'] = $item['last_detected_at'];
        }

        return new Finding(
            id: (string) ($item['id'] ?? bin2hex(random_bytes(8))),
            type: $type,
            severity: $severity,
            title: (string) ($item['title'] ?? ''),
            description: (string) ($item['description'] ?? ''),
            evidence: $item['evidence'] ?? [],
            impact: (string) ($item['impact'] ?? 'Medium'),
            recommendation: $item['recommendation'] ?? null,
            confidence: (float) ($item['confidence'] ?? 0.85),
            context: $context,
            detectedAt: $detectedAt,
            score: (float) ($item['score'] ?? 0.0),
        );
    }
}
