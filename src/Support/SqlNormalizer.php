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

        if (count(self::$cache) >= 500) {
            self::$cache = [];
        }

        self::$cache[$sql] = $result;

        return $result;
    }
}
