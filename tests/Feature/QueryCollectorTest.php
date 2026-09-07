<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Tests\TestCase;

class QueryCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary users table for query tests
        DB::statement('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT)');
    }

    /**
     * Test collector records queries executed through Laravel DB facade.
     */
    public function test_captures_executed_queries(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();
        $collector->start();

        DB::insert('INSERT INTO users (name, email, password) VALUES (?, ?, ?)', [
            'Alice',
            'alice@example.com',
            'secret123',
        ]);

        $results = DB::select('SELECT * FROM users WHERE email = ?', ['alice@example.com']);
        $this->assertNotEmpty($results);

        $queries = $collector->getQueries();
        $this->assertCount(2, $queries);

        $insertQuery = $queries[0];
        $this->assertStringContainsString('INSERT INTO users', $insertQuery->getSql());
        $this->assertEquals('testing', $insertQuery->getConnectionName());
        $this->assertGreaterThanOrEqual(0.0, $insertQuery->getTimeMs());

        $selectQuery = $queries[1];
        $this->assertStringContainsString('SELECT * FROM users', $selectQuery->getSql());
        $this->assertEquals(['alice@example.com'], $selectQuery->getBindings());
    }

    /**
     * Test sensitive bindings are sanitized.
     */
    public function test_sanitizes_sensitive_named_bindings(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();
        $collector->start();

        DB::statement('SELECT * FROM users WHERE password = :password', [
            'password' => 'super-secret-password-123',
        ]);

        $queries = $collector->getQueries();
        $this->assertCount(1, $queries);
        $this->assertEquals('********', $queries[0]->getBindings()['password']);
    }

    /**
     * Test query normalization.
     */
    public function test_sql_normalization(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();
        $collector->start();

        DB::select("SELECT * FROM users WHERE id = 42 AND name = 'Bob'");

        $queries = $collector->getQueries();
        $this->assertCount(1, $queries);

        $normalized = $queries[0]->getNormalizedSql();
        $this->assertStringNotContainsString('42', $normalized);
        $this->assertStringNotContainsString('Bob', $normalized);
        $this->assertStringContainsString('?', $normalized);
    }

    /**
     * Test reset clears collected records.
     */
    public function test_reset_clears_queries(): void
    {
        /** @var QueryCollector $collector */
        $collector = $this->app->make(QueryCollector::class);
        $collector->reset();
        $collector->start();

        DB::select('SELECT 1');
        $this->assertCount(1, $collector->getQueries());

        $collector->reset();
        $this->assertCount(0, $collector->getQueries());
        $this->assertEquals(0, $collector->count());
        $this->assertEquals(0.0, $collector->totalTimeMs());
    }
}
