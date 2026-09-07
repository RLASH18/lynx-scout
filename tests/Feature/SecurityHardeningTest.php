<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Lynx\Scout\Support\BindingSanitizer;
use Lynx\Scout\Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_redacts_jwt_tokens_in_positional_bindings(): void
    {
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozG4m1e_pQ2m1e_pQ2m1e_pQ2m1e_pQ2m1e';
        $sanitized = BindingSanitizer::sanitize([0 => $jwt]);

        $this->assertStringContainsString('REDACTED JWT', $sanitized[0]);
        $this->assertStringNotContainsString('eyJ', $sanitized[0]);
    }

    public function test_redacts_bcrypt_hashes_in_positional_bindings(): void
    {
        $hash = '$2y$10$e8Za35W8m2G6m2G6m2G6m.8eZa35W8m2G6m2G6m2G6m';
        $sanitized = BindingSanitizer::sanitize([0 => $hash]);

        $this->assertStringContainsString('REDACTED HASH', $sanitized[0]);
        $this->assertStringNotContainsString('$2y$', $sanitized[0]);
    }

    public function test_custom_hidden_patterns(): void
    {
        config(['lynx.query.hidden_patterns' => ['ssn', 'tax_id']]);

        $sanitized = BindingSanitizer::sanitize([
            'tax_id' => '12-3456789',
            'normal' => 'public_info',
        ]);

        $this->assertEquals('********', $sanitized['tax_id']);
        $this->assertEquals('public_info', $sanitized['normal']);
    }

    public function test_oversized_string_truncation(): void
    {
        $huge = str_repeat('A', 1000);
        $sanitized = BindingSanitizer::sanitize([0 => $huge]);

        $this->assertLessThan(200, strlen($sanitized[0]));
        $this->assertStringContainsString('... [TRUNCATED]', $sanitized[0]);
    }
}
