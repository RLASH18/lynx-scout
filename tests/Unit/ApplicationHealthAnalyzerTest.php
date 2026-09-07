<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Lynx\Scout\Analyzers\ApplicationHealthAnalyzer;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Tests\TestCase;
use Mockery;

class ApplicationHealthAnalyzerTest extends TestCase
{
    /**
     * Test local/testing environment does not trigger production warnings.
     */
    public function test_testing_environment_produces_no_production_warnings(): void
    {
        $analyzer = new ApplicationHealthAnalyzer($this->app);
        $findings = $analyzer->analyze();

        $this->assertEmpty($findings);
    }

    /**
     * Test production environment with APP_DEBUG=true is flagged.
     */
    public function test_flags_app_debug_in_production(): void
    {
        config(['app.debug' => true]);

        $mockApp = Mockery::mock(Application::class);
        $mockApp->shouldReceive('environment')->with('production')->andReturn(true);
        $mockApp->shouldReceive('environment')->withNoArgs()->andReturn('production');
        $mockApp->shouldReceive('configurationIsCached')->andReturn(true);
        $mockApp->shouldReceive('routesAreCached')->andReturn(true);

        $analyzer = new ApplicationHealthAnalyzer($mockApp);
        $findings = $analyzer->analyze();

        $this->assertNotEmpty($findings);
        $debugFinding = null;
        foreach ($findings as $f) {
            if ($f->getTitle() === 'Debug mode enabled in production') {
                $debugFinding = $f;
            }
        }

        $this->assertNotNull($debugFinding);
        $this->assertEquals(Severity::Critical, $debugFinding->getSeverity());
        $this->assertEquals(FindingType::HealthIssue, $debugFinding->getTypeEnum());
    }

    /**
     * Test production environment without config cache is flagged.
     */
    public function test_flags_missing_config_cache_in_production(): void
    {
        config(['app.debug' => false]);

        $mockApp = Mockery::mock(Application::class);
        $mockApp->shouldReceive('environment')->with('production')->andReturn(true);
        $mockApp->shouldReceive('environment')->withNoArgs()->andReturn('production');
        $mockApp->shouldReceive('configurationIsCached')->andReturn(false);
        $mockApp->shouldReceive('routesAreCached')->andReturn(true);

        $analyzer = new ApplicationHealthAnalyzer($mockApp);
        $findings = $analyzer->analyze();

        $configFinding = null;
        foreach ($findings as $f) {
            if ($f->getTitle() === 'Configuration is not cached in production') {
                $configFinding = $f;
            }
        }

        $this->assertNotNull($configFinding);
        $this->assertEquals(Severity::Medium, $configFinding->getSeverity());
    }
}
