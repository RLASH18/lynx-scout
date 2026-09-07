<?php

declare(strict_types=1);

namespace Lynx\Scout\Analyzers;

use Illuminate\Contracts\Foundation\Application;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\Severity;

class ApplicationHealthAnalyzer
{
    public function __construct(
        private readonly ?Application $app = null,
    ) {}

    /**
     * Inspect application runtime configuration and return health findings.
     *
     * @return list<Finding>
     */
    public function analyze(): array
    {
        if (! config('lynx.health.enabled', true)) {
            return [];
        }

        $app = $this->app ?? app();
        $isProduction = $app->environment('production');
        $findings = [];

        // 1. APP_DEBUG in Production
        if ($isProduction && (bool) config('app.debug', false)) {
            $findings[] = Finding::create(
                type: FindingType::HealthIssue,
                severity: Severity::Critical,
                title: 'Debug mode enabled in production',
                description: 'APP_DEBUG is enabled in a production environment, which exposes sensitive application state and significantly increases response memory overhead.',
                evidence: [
                    'app_debug' => true,
                    'environment' => $app->environment(),
                ],
                impact: 'Critical',
                recommendation: 'Disable debug mode in production: set APP_DEBUG=false in your .env.',
                confidence: 0.99,
            );
        }

        // 2. Config caching in Production
        if ($isProduction && ! $app->configurationIsCached()) {
            $findings[] = Finding::create(
                type: FindingType::HealthIssue,
                severity: Severity::Medium,
                title: 'Configuration is not cached in production',
                description: 'Configuration files are loaded and parsed on every request. Caching configuration eliminates filesystem reads and boosts boot performance.',
                evidence: [
                    'configuration_cached' => false,
                    'environment' => $app->environment(),
                ],
                impact: 'Medium',
                recommendation: 'Cache configuration in deployment pipelines: php artisan config:cache',
                confidence: 0.98,
            );
        }

        // 3. Route caching in Production
        if ($isProduction && ! $app->routesAreCached()) {
            $findings[] = Finding::create(
                type: FindingType::HealthIssue,
                severity: Severity::Medium,
                title: 'Routes are not cached in production',
                description: 'Application routes are parsed and registered per-request rather than loaded from the compiled route cache.',
                evidence: [
                    'routes_cached' => false,
                    'environment' => $app->environment(),
                ],
                impact: 'Medium',
                recommendation: 'Cache application routes during deployment: php artisan route:cache',
                confidence: 0.98,
            );
        }

        // 4. OPcache check
        if ($isProduction && function_exists('opcache_get_status')) {
            $opcacheEnabled = (bool) ini_get('opcache.enable');
            $opcacheStatus = @opcache_get_status(false);

            if (! $opcacheEnabled || $opcacheStatus === false) {
                $findings[] = Finding::create(
                    type: FindingType::HealthIssue,
                    severity: Severity::High,
                    title: 'PHP OPcache is disabled or inactive',
                    description: 'OPcache opcode caching is disabled, forcing PHP to recompile scripts on every request.',
                    evidence: [
                        'opcache_enabled' => $opcacheEnabled,
                        'environment' => $app->environment(),
                    ],
                    impact: 'High',
                    recommendation: 'Enable OPcache in your php.ini: opcache.enable=1 and opcache.validate_timestamps=0 in production.',
                    confidence: 0.95,
                );
            }
        }

        return $findings;
    }
}
