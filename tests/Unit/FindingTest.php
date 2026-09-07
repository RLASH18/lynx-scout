<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Contracts\FindingContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\QueryRecord;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Data\Severity;
use PHPUnit\Framework\TestCase;

class FindingTest extends TestCase
{
    /**
     * Test finding initialization and contract implementation.
     */
    public function test_finding_initialization_and_accessors(): void
    {
        $finding = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::High,
            title: 'Slow database query detected',
            description: 'SELECT * FROM users WHERE active = 1 took 842ms',
            evidence: 'Query executed in 842ms',
            impact: 'High',
            recommendation: 'Add index on active column',
            confidence: 0.91,
            context: ['connection' => 'mysql'],
        );

        $this->assertInstanceOf(FindingContract::class, $finding);
        $this->assertEquals('slow_query', $finding->getType());
        $this->assertEquals(FindingType::SlowQuery, $finding->getTypeEnum());
        $this->assertEquals(Severity::High, $finding->getSeverity());
        $this->assertEquals('High', $finding->getSeverity()->label());
        $this->assertEquals('Slow database query detected', $finding->getTitle());
        $this->assertEquals('Query executed in 842ms', $finding->getEvidence());
        $this->assertEquals('High', $finding->getImpact());
        $this->assertEquals('Add index on active column', $finding->getRecommendation());
        $this->assertEquals(0.91, $finding->getConfidence());
        $this->assertEquals('High confidence', $finding->getConfidenceLabel());
        $this->assertEquals(['connection' => 'mysql'], $finding->getContext());
        $this->assertInstanceOf(DateTimeImmutable::class, $finding->getDetectedAt());
    }

    /**
     * Test confidence label mapping across confidence ranges.
     */
    public function test_confidence_label_mapping(): void
    {
        $high = Finding::create('custom', Severity::Low, 'T', 'D', 'E', confidence: 0.9);
        $med = Finding::create('custom', Severity::Low, 'T', 'D', 'E', confidence: 0.7);
        $low = Finding::create('custom', Severity::Low, 'T', 'D', 'E', confidence: 0.4);

        $this->assertEquals('High confidence', $high->getConfidenceLabel());
        $this->assertEquals('Medium confidence', $med->getConfidenceLabel());
        $this->assertEquals('Low confidence', $low->getConfidenceLabel());
    }

    /**
     * Test immutable methods for recommendation and scoring.
     */
    public function test_immutable_modifiers(): void
    {
        $finding = Finding::create(
            type: FindingType::NPlusOne,
            severity: Severity::Critical,
            title: 'N+1 detected',
            description: 'Repeated query',
            evidence: '42 occurrences',
        );

        $updated = $finding->withRecommendation('Use eager loading Post::with()');
        $scored = $updated->withScore(95.0, 'Critical');

        $this->assertNull($finding->getRecommendation());
        $this->assertEquals('Use eager loading Post::with()', $updated->getRecommendation());
        $this->assertEquals(95.0, $scored->getScore());
        $this->assertEquals('Critical', $scored->getImpact());
    }

    /**
     * Test array serialization.
     */
    public function test_array_and_json_serialization(): void
    {
        $finding = Finding::create(
            type: FindingType::SlowQuery,
            severity: Severity::Medium,
            title: 'Title',
            description: 'Desc',
            evidence: ['query' => 'SELECT 1'],
            id: 'fixed-id-123',
        );

        $array = $finding->toArray();
        $this->assertEquals('fixed-id-123', $array['id']);
        $this->assertEquals('slow_query', $array['type']);
        $this->assertEquals('medium', $array['severity']);
        $this->assertJson((string) json_encode($finding));
    }

    /**
     * Test QueryRecord data structure.
     */
    public function test_query_record(): void
    {
        $now = new DateTimeImmutable();
        $record = new QueryRecord(
            sql: 'SELECT * FROM users WHERE id = ?',
            bindings: [1],
            timeMs: 45.2,
            connectionName: 'sqlite',
            executedAt: $now,
            normalizedSql: 'select * from users where id = ?',
            caller: 'App\\Http\\Controllers\\UserController@index',
        );

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $record->getSql());
        $this->assertEquals([1], $record->getBindings());
        $this->assertEquals(45.2, $record->getTimeMs());
        $this->assertEquals('sqlite', $record->getConnectionName());
        $this->assertEquals('App\\Http\\Controllers\\UserController@index', $record->getCaller());
        $this->assertEquals('select * from users where id = ?', $record->getNormalizedSql());
    }

    /**
     * Test RequestRecord data structure.
     */
    public function test_request_record(): void
    {
        $now = new DateTimeImmutable();
        $record = new RequestRecord(
            id: 'req-1',
            method: 'GET',
            uri: '/api/users',
            routeName: 'users.index',
            action: 'UserController@index',
            durationMs: 120.5,
            statusCode: 200,
            queryCount: 4,
            queryTimeMs: 15.2,
            memoryBytes: 204800,
            requestedAt: $now,
        );

        $this->assertEquals('req-1', $record->getId());
        $this->assertEquals('GET', $record->getMethod());
        $this->assertEquals('/api/users', $record->getUri());
        $this->assertEquals('users.index', $record->getRouteName());
        $this->assertEquals(120.5, $record->getDurationMs());
        $this->assertEquals(200, $record->getStatusCode());
        $this->assertEquals(4, $record->getQueryCount());
    }
}
