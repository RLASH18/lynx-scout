<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Lynx\Scout\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * Test configuration default values are loaded.
     */
    public function test_default_configuration_is_loaded(): void
    {
        $this->assertTrue(config('lynx.enabled'));
        $this->assertTrue(config('lynx.query.enabled'));
        $this->assertEquals(100.0, config('lynx.query.slow_threshold'));
        $this->assertEquals(2, config('lynx.query.duplicate_threshold'));
        $this->assertEquals(3, config('lynx.query.n_plus_one_threshold'));
        $this->assertTrue(config('lynx.query.record_bindings'));
        $this->assertTrue(config('lynx.query.sanitize_bindings'));
        $this->assertTrue(config('lynx.request.enabled'));
        $this->assertEquals(500.0, config('lynx.request.slow_threshold'));
        $this->assertTrue(config('lynx.recommendations.enabled'));
        $this->assertEquals(1.0, config('lynx.sampling.rate'));
        $this->assertIsArray(config('lynx.environments'));
        $this->assertContains('local', config('lynx.environments'));
    }

    /**
     * Test configuration can be modified at runtime.
     */
    public function test_configuration_can_be_customized(): void
    {
        config(['lynx.query.slow_threshold' => 250.0]);
        $this->assertEquals(250.0, config('lynx.query.slow_threshold'));

        config(['lynx.enabled' => false]);
        $this->assertFalse(config('lynx.enabled'));
    }
}
