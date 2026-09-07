<?php

declare(strict_types=1);

namespace Lynx\Scout\Support;

class BindingSanitizer
{
    /**
     * Common sensitive keys and patterns to redact.
     *
     * @var list<string>
     */
    private const SENSITIVE_PATTERNS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'auth',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /**
     * Sanitize query bindings to protect sensitive credentials and data.
     *
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    public static function sanitize(array $bindings): array
    {
        $sanitized = [];

        foreach ($bindings as $key => $value) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach (self::SENSITIVE_PATTERNS as $pattern) {
                    if (str_contains($lowerKey, $pattern)) {
                        $sanitized[$key] = '********';
                        continue 2;
                    }
                }
            }

            // If value is exceptionally large (over 1024 chars), truncate for memory efficiency
            if (is_string($value) && strlen($value) > 1024) {
                $sanitized[$key] = substr($value, 0, 128) . '... [TRUNCATED]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
