<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Lynx\Scout\Support\SqlNormalizer;
use Lynx\Scout\Tests\TestCase;

class SqlNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SqlNormalizer::flushCache();
    }

    public function test_normalizes_string_literals(): void
    {
        $sql = "SELECT * FROM users WHERE email = 'test@example.com' AND name = 'John Doe'";
        $normalized = SqlNormalizer::normalize($sql);

        $this->assertEquals("SELECT * FROM users WHERE email = ? AND name = ?", $normalized);
    }

    public function test_normalizes_numeric_literals(): void
    {
        $sql = "SELECT * FROM orders WHERE user_id = 42 AND status_id = 7";
        $normalized = SqlNormalizer::normalize($sql);

        $this->assertEquals("SELECT * FROM orders WHERE user_id = ? AND status_id = ?", $normalized);
    }

    public function test_normalizes_in_clauses(): void
    {
        $sql = "SELECT * FROM products WHERE id IN (?, ?, ?, ?)";
        $normalized = SqlNormalizer::normalize($sql);

        $this->assertEquals("SELECT * FROM products WHERE id in (?)", $normalized);
    }

    public function test_collapses_whitespace_and_newlines(): void
    {
        $sql = "SELECT   *\nFROM    users\n  WHERE   id = 1";
        $normalized = SqlNormalizer::normalize($sql);

        $this->assertEquals("SELECT * FROM users WHERE id = ?", $normalized);
    }

    public function test_caches_repeated_queries(): void
    {
        $sql = "SELECT * FROM posts WHERE id = 1";
        $this->assertEquals(0, SqlNormalizer::cacheCount());

        SqlNormalizer::normalize($sql);
        $this->assertEquals(1, SqlNormalizer::cacheCount());

        SqlNormalizer::normalize($sql);
        $this->assertEquals(1, SqlNormalizer::cacheCount());
    }

    public function test_lru_cache_eviction(): void
    {
        config(['lynx.normalizer.cache_size' => 3]);

        SqlNormalizer::normalize("SELECT 1");
        SqlNormalizer::normalize("SELECT 2");
        SqlNormalizer::normalize("SELECT 3");
        $this->assertEquals(3, SqlNormalizer::cacheCount());

        SqlNormalizer::normalize("SELECT 4");
        $this->assertEquals(3, SqlNormalizer::cacheCount());
    }
}
