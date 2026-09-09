<?php

declare(strict_types=1);

namespace Lynx\Scout\Support;

class BindingSanitizer
{
    /**
     * Default sensitive keywords to redact.
     *
     * @var list<string>
     */
    private const DEFAULT_SENSITIVE_PATTERNS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'auth',
        'bearer',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'private_key',
        'access_token',
        'refresh_token',
        'passphrase',
        'credential',
    ];

    /**
     * Sanitize query bindings to protect sensitive credentials and data.
     *
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    public static function sanitize(array $bindings): array
    {
        $customPatterns = (array) config('lynx.query.hidden_patterns', []);
        $patterns = array_merge(self::DEFAULT_SENSITIVE_PATTERNS, $customPatterns);

        $sanitized = [];

        foreach ($bindings as $key => $value) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($patterns as $pattern) {
                    if (str_contains($lowerKey, strtolower($pattern))) {
                        $sanitized[$key] = '********';
                        continue 2;
                    }
                }
            }

            $sanitized[$key] = self::sanitizeValue($value, $patterns);
        }

        return $sanitized;
    }

    /**
     * Sanitize scalar values and nested binding arrays.
     *
     * @param list<string> $patterns
     */
    private static function sanitizeValue(mixed $value, array $patterns, int $depth = 0): mixed
    {
        if (is_array($value) && $depth < 4) {
            $nested = [];
            foreach ($value as $key => $nestedValue) {
                if (is_string($key) && self::matchesSensitiveKey($key, $patterns)) {
                    $nested[$key] = '********';
                } else {
                    $nested[$key] = self::sanitizeValue($nestedValue, $patterns, $depth + 1);
                }
            }

            return $nested;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (str_contains($value, '-----BEGIN ') && str_contains($value, 'PRIVATE KEY-----')) {
            return '******** [REDACTED PRIVATE KEY]';
        }

        if (str_starts_with($value, 'eyJ') && substr_count($value, '.') === 2) {
            return '******** [REDACTED JWT]';
        }

        if (
            str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2i$')
            || str_starts_with($value, '$argon2id$')
        ) {
            return '******** [REDACTED HASH]';
        }

        return strlen($value) > 512
            ? substr($value, 0, 64) . '... [TRUNCATED]'
            : $value;
    }

    /**
     * Determine whether a binding key contains a configured sensitive pattern.
     *
     * @param list<string> $patterns
     */
    private static function matchesSensitiveKey(string $key, array $patterns): bool
    {
        $lowerKey = strtolower($key);
        foreach ($patterns as $pattern) {
            if (str_contains($lowerKey, strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }
}
