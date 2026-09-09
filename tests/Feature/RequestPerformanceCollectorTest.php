<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Detectors\SlowRequestDetector;
use Lynx\Scout\Tests\TestCase;

class RequestPerformanceCollectorTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/test-quick', function () {
            return response()->json(['status' => 'ok']);
        })->name('test.quick');

        $router->get('/test-with-queries', function () {
            DB::select('SELECT 1');
            DB::select('SELECT 2');
            return response()->json(['status' => 'done']);
        })->name('test.queries');

        $router->get('/test-slow', function () {
            usleep(15000); // 15ms
            return response()->json(['status' => 'slow']);
        })->name('test.slow');
    }

    public function test_captures_http_request_profile(): void
    {
        /** @var RequestCollector $collector */
        $collector = $this->app->make(RequestCollector::class);
        $collector->reset();

        $response = $this->get('/test-with-queries');
        $response->assertStatus(200);

        $requests = $collector->getRequests();
        $this->assertCount(1, $requests);

        $record = $requests[0];
        $this->assertEquals('GET', $record->getMethod());
        $this->assertEquals('/test-with-queries', $record->getUri());
        $this->assertEquals('test.queries', $record->getRouteName());
        $this->assertEquals(200, $record->getStatusCode());
        $this->assertGreaterThanOrEqual(2, $record->getQueryCount());
        $this->assertGreaterThan(0, $record->getMemoryBytes());
    }

    public function test_slow_request_detector(): void
    {
        /** @var RequestCollector $collector */
        $collector = $this->app->make(RequestCollector::class);
        $collector->reset();

        $this->get('/test-slow');

        $requests = $collector->getRequests();
        $this->assertCount(1, $requests);

        // Run detector with low threshold to simulate slow request
        $detector = new SlowRequestDetector(threshold: 5.0);
        $findings = $detector->detect($requests);

        $this->assertCount(1, $findings);
        $this->assertEquals('slow_request', $findings[0]->getType());
        $this->assertEquals('Slow HTTP request detected', $findings[0]->getTitle());
        $this->assertEquals('/test-slow', $findings[0]->getEvidence()['uri']);
    }

    public function test_single_request_creates_single_request_profile_without_duplicate_middleware_execution(): void
    {
        /** @var RequestCollector $collector */
        $collector = $this->app->make(RequestCollector::class);
        $collector->reset();

        $response = $this->get('/test-quick');
        $response->assertStatus(200);

        $this->assertCount(1, $collector->getRequests());
    }
}
