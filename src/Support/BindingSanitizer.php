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
            // Check string keys (named bindings)
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($patterns as $pattern) {
                    if (str_contains($lowerKey, strtolower($pattern))) {
                        $sanitized[$key] = '********';
                        continue 2;
                    }
                }
            }

            // Check sensitive value shapes (JWTs, hashes, private keys)
            if (is_string($value)) {
                // Private keys / PEM blocks
                if (str_contains($value, '-----BEGIN ') && str_contains($value, 'PRIVATE KEY-----')) {
                    $sanitized[$key] = '******** [REDACTED PRIVATE KEY]';
                    continue;
                }

                // JWT Token shape (starts with eyJ... and has 2 dots)
                if (str_starts_with($value, 'eyJ') && substr_count($value, '.') === 2) {
                    $sanitized[$key] = '******** [REDACTED JWT]';
                    continue;
                }

                // Hashes: Bcrypt ($2y$, $2a$, $2b$) and Argon2 ($argon2i$, $argon2id$)
                if (
                    str_starts_with($value, '$2y$')
                    || str_starts_with($value, '$2a$')
                    || str_starts_with($value, '$2b$')
                    || str_starts_with($value, '$argon2i$')
                    || str_starts_with($value, '$argon2id$')
                ) {
                    $sanitized[$key] = '******** [REDACTED HASH]';
                    continue;
                }

                // Truncate excessively long strings to preserve memory and prevent huge leaks
                if (strlen($value) > 512) {
                    $sanitized[$key] = substr($value, 0, 64) . '... [TRUNCATED]';
                    continue;
                }
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
