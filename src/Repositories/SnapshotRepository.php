<?php

declare(strict_types=1);

namespace Lynx\Scout\Repositories;

use Lynx\Scout\Data\PerformanceSnapshot;

class SnapshotRepository
{
    private readonly string $directory;

    public function __construct(?string $storagePath = null)
    {
        $base = $storagePath ?? (string) config('lynx.storage.path', storage_path('lynx'));
        $this->directory = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'snapshots';

        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Save a snapshot and link it as latest.
     */
    public function save(PerformanceSnapshot $snapshot): string
    {
        $filename = $snapshot->getId() . '.json';
        $fullPath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        @file_put_contents($fullPath, json_encode($snapshot->toArray(), JSON_PRETTY_PRINT), LOCK_EX);

        // Also save/update latest pointer
        $latestPath = $this->directory . DIRECTORY_SEPARATOR . 'latest.json';
        @file_put_contents($latestPath, json_encode($snapshot->toArray(), JSON_PRETTY_PRINT), LOCK_EX);

        return $fullPath;
    }

    /**
     * Find snapshot by identifier or name.
     */
    public function find(string $id): ?PerformanceSnapshot
    {
        $filename = str_ends_with($id, '.json') ? $id : "{$id}.json";
        $fullPath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (! file_exists($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        return PerformanceSnapshot::fromArray($data);
    }

    /**
     * Retrieve the most recently recorded snapshot.
     */
    public function latest(): ?PerformanceSnapshot
    {
        return $this->find('latest');
    }

    /**
     * Get all saved snapshots.
     *
     * @return list<PerformanceSnapshot>
     */
    public function all(): array
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $snapshots = [];

        foreach ($files as $file) {
            if (basename($file) === 'latest.json') {
                continue;
            }

            $content = @file_get_contents($file);
            if ($content !== false && ($data = json_decode($content, true)) && is_array($data)) {
                $snapshots[] = PerformanceSnapshot::fromArray($data);
            }
        }

        usort($snapshots, fn (PerformanceSnapshot $a, PerformanceSnapshot $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $snapshots;
    }
}
