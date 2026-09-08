<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Feature;

use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Support\CallerDetector;
use Lynx\Scout\Support\SqlNormalizer;
use Lynx\Scout\Tests\TestCase;

class PerformanceOverheadTest extends TestCase
{
    /**
     * Benchmark query normalizer speed.
     */
    public function test_normalizer_overhead_is_minimal(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            SqlNormalizer::normalize("SELECT * FROM users WHERE id = {$i} AND email = 'user_{$i}@example.com'");
        }

        $elapsed = microtime(true) - $start;

        // 1,000 normalizations should take well under 0.25 seconds (less than 0.25ms per query)
        $this->assertLessThan(0.25, $elapsed);
    }

    /**
     * Test sampling skips profiling when configured below 1.0.
     */
    public function test_sampling_rate_skips_recording(): void
    {
        config(['lynx.sampling.rate' => 0.0]); // 0% sampling

        /** @var RequestCollector $collector */
        $collector = $this->app->make(RequestCollector::class);
        $collector->reset();

        $this->get('/test-quick');

        $this->assertCount(0, $collector->getRequests());
    }

    /**
     * Test SQL normalizer LRU eviction and cache flush.
     */
    public function test_sql_normalizer_lru_cache_eviction_and_flush(): void
    {
        SqlNormalizer::flushCache();
        $this->assertEquals(0, SqlNormalizer::cacheCount());

        config(['lynx.normalizer.cache_size' => 10]);

        for ($i = 0; $i < 20; $i++) {
            SqlNormalizer::normalize("SELECT * FROM table_{$i} WHERE col = 1");
        }

        $this->assertEquals(10, SqlNormalizer::cacheCount());

        SqlNormalizer::flushCache();
        $this->assertEquals(0, SqlNormalizer::cacheCount());
    }

    /**
     * Test caller detector respects disabled configuration.
     */
    public function test_caller_detector_disabled_returns_null(): void
    {
        config(['lynx.callers.enabled' => false]);
        $this->assertNull(CallerDetector::detect());
    }
}
