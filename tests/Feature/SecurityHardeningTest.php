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

    public function test_redacts_argon2id_and_argon2i_hashes(): void
    {
        $argon2id = '$argon2id$v=19$m=65536,t=4,p=1$QnJva2VuSGFzaA$dummyHashPayloadValueHere';
        $argon2i = '$argon2i$v=19$m=65536,t=4,p=1$QnJva2VuSGFzaA$dummyHashPayloadValueHere';

        $sanitized = BindingSanitizer::sanitize([0 => $argon2id, 1 => $argon2i]);

        $this->assertStringContainsString('REDACTED HASH', $sanitized[0]);
        $this->assertStringNotContainsString('$argon2id$', $sanitized[0]);

        $this->assertStringContainsString('REDACTED HASH', $sanitized[1]);
        $this->assertStringNotContainsString('$argon2i$', $sanitized[1]);
    }

    public function test_redacts_private_key_pem_blocks(): void
    {
        $pem = "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA0r...\n-----END RSA PRIVATE KEY-----";
        $sanitized = BindingSanitizer::sanitize([0 => $pem]);

        $this->assertEquals('******** [REDACTED PRIVATE KEY]', $sanitized[0]);
    }

    public function test_redacts_additional_sensitive_keywords(): void
    {
        $sanitized = BindingSanitizer::sanitize([
            'access_token' => 'secret-access-token-val',
            'refresh_token' => 'secret-refresh-token-val',
            'passphrase' => 'super-secret-phrase',
            'credential' => 'my-credential',
            'public_name' => 'John Doe',
        ]);

        $this->assertEquals('********', $sanitized['access_token']);
        $this->assertEquals('********', $sanitized['refresh_token']);
        $this->assertEquals('********', $sanitized['passphrase']);
        $this->assertEquals('********', $sanitized['credential']);
        $this->assertEquals('John Doe', $sanitized['public_name']);
    }
}
