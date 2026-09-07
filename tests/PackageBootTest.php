<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests;

use Lynx\Scout\LynxServiceProvider;

class PackageBootTest extends TestCase
{
    /**
     * Test that package service provider is registered and loaded.
     */
    public function test_package_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(LynxServiceProvider::class));
    }
}
