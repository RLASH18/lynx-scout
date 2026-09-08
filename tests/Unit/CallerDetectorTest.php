<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

require_once __DIR__ . '/../Fixtures/DummyUserService.php';

use App\Services\DummyUserService;
use Lynx\Scout\Support\CallerDetector;
use Lynx\Scout\Tests\TestCase;

class CallerDetectorTest extends TestCase
{
    public function test_detects_application_class_and_method(): void
    {
        $service = new DummyUserService();
        $caller = $service->fetch();

        $this->assertNotNull($caller);
        $this->assertStringContainsString('DummyUserService@fetch', $caller);
    }

    public function test_returns_null_when_caller_detection_is_disabled(): void
    {
        config(['lynx.callers.enabled' => false]);

        $this->assertNull(CallerDetector::detect());
    }

    public function test_ignores_custom_configured_namespaces(): void
    {
        config(['lynx.callers.ignored_namespaces' => ['App\\']]);

        $service = new DummyUserService();
        $caller = $service->fetch();

        $this->assertNull($caller);
    }

    public function test_sample_rate_zero_returns_null(): void
    {
        config(['lynx.callers.sample_rate' => 0.0]);

        $this->assertNull(CallerDetector::detect());
    }
}
