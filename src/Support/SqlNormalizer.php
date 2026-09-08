<?php

declare(strict_types=1);

namespace Lynx\Scout\Support;

class SqlNormalizer
{
    /**
     * In-memory cache for normalized queries to prevent repetitive regex passes.
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Normalize SQL query for pattern comparison.
     * Replaces variable values, collapses whitespace, and formats placeholders.
     */
    public static function normalize(string $sql): string
    {
        if (isset(self::$cache[$sql])) {
            return self::$cache[$sql];
        }

        $normalized = trim($sql);

        // Replace string literals '...' with ?
        $normalized = preg_replace("/'([^'\\\\]|\\\\.)*'/", '?', $normalized) ?? $normalized;

        // Replace numbers with ?
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized) ?? $normalized;

        // Replace IN ( ?, ?, ? ... ) with IN (?)
        $normalized = preg_replace('/in\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'in (?)', $normalized) ?? $normalized;

        // Replace multiple whitespace/newlines with single space
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $result = trim($normalized);

        $maxCache = (int) config('lynx.normalizer.cache_size', 500);

        // Evict oldest entry when cache is full (LRU)
        if (count(self::$cache) >= $maxCache) {
            $oldestKey = array_key_first(self::$cache);
            if ($oldestKey !== null) {
                unset(self::$cache[$oldestKey]);
            }
        }

        self::$cache[$sql] = $result;

        return $result;
    }

    /**
     * Flush in-memory normalization cache (useful for Octane worker resets & tests).
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get current cache count.
     */
    public static function cacheCount(): int
    {
        return count(self::$cache);
    }
}
