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
        if (! is_dir($this->directory)) {
            if (! @mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
                // directory fallback
            }
        }

        $filename = $this->filenameForId($snapshot->getId());
        $fullPath = $this->directory . DIRECTORY_SEPARATOR . $filename;
        $encoded = json_encode(
            $snapshot->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        $this->atomicWrite($fullPath, $encoded);
        $this->atomicWrite($this->directory . DIRECTORY_SEPARATOR . 'latest.json', $encoded);

        return $fullPath;
    }

    /**
     * Find snapshot by identifier or name.
     */
    public function find(string $id): ?PerformanceSnapshot
    {
        try {
            $filename = $this->filenameForId($id);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $fullPath = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (! file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        if ($content === false || trim($content) === '') {
            return null;
        }

        if (function_exists('json_validate') && ! json_validate($content)) {
            return null;
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        try {
            return PerformanceSnapshot::fromArray($data);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert an external snapshot identifier into a safe filename.
     */
    private function filenameForId(string $id): string
    {
        $name = str_ends_with($id, '.json') ? substr($id, 0, -5) : $id;
        if ($name === '' || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z/D', $name)) {
            throw new \InvalidArgumentException('Snapshot identifiers must be safe slugs.');
        }

        return $name . '.json';
    }

    /**
     * Write a JSON document without exposing a partially-written target.
     */
    private function atomicWrite(string $path, string $contents): void
    {
        $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $written = file_put_contents($temporaryPath, $contents, LOCK_EX) !== false;
        $renamed = $written && @rename($temporaryPath, $path);
        if (! $renamed && $written && file_exists($path)) {
            @unlink($path);
            $renamed = @rename($temporaryPath, $path);
        }

        if (! $renamed) {
            @unlink($temporaryPath);
            throw new \RuntimeException("Unable to write snapshot file: {$path}");
        }
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

            $content = file_get_contents($file);
            if ($content === false || trim($content) === '') {
                continue;
            }

            if (function_exists('json_validate') && ! json_validate($content)) {
                continue;
            }

            $data = json_decode($content, true);
            if (is_array($data)) {
                try {
                    $snapshots[] = PerformanceSnapshot::fromArray($data);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($snapshots, fn (PerformanceSnapshot $a, PerformanceSnapshot $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $snapshots;
    }
}
