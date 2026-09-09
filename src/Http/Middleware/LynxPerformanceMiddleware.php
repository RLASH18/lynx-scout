<?php

declare(strict_types=1);

namespace Lynx\Scout\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Data\RequestRecord;
use Lynx\Scout\Services\LynxScanner;
use Symfony\Component\HttpFoundation\Response;

class LynxPerformanceMiddleware
{
    public function __construct(
        private readonly RequestCollector $requestCollector,
        private readonly QueryCollector $queryCollector,
        private readonly LynxScanner $scanner,
    ) {}

    /**
     * Handle incoming request and capture execution profile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('lynx.enabled', true) || ! config('lynx.request.enabled', true)) {
            return $next($request);
        }

        $samplingRate = (float) config('lynx.sampling.rate', 1.0);
        if ($samplingRate < 1.0 && (mt_rand(1, 10000) / 10000.0) > $samplingRate) {
            return $next($request);
        }

        $requestId = bin2hex(random_bytes(8));
        $startTime = microtime(true);
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $startMemory = memory_get_usage(false);

        $this->queryCollector->beginScope([
            'request_id' => $requestId,
            'uri' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
        ]);

        $response = null;
        try {
            $response = $next($request);
        } finally {
            $queryStats = $this->queryCollector->endScope();
            $route = $request->route();
            $routeName = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;
            $action = is_object($route) && method_exists($route, 'getActionName') ? $route->getActionName() : null;

            $peakMemory = memory_get_peak_usage(false);
            $memoryBytes = max(0, $peakMemory - $startMemory);
            if ($memoryBytes === 0) {
                $memoryBytes = memory_get_peak_usage(true);
            }

            $this->requestCollector->record(new RequestRecord(
                id: $requestId,
                method: $request->method(),
                uri: '/' . ltrim($request->path(), '/'),
                routeName: $routeName,
                action: $action,
                durationMs: round((microtime(true) - $startTime) * 1000, 2),
                statusCode: $response?->getStatusCode() ?? 500,
                queryCount: $queryStats['query_count'],
                queryTimeMs: $queryStats['query_time_ms'],
                memoryBytes: $memoryBytes,
                requestedAt: new DateTimeImmutable(),
                context: ['request_id' => $requestId],
            ));
        }

        if ($response === null) {
            throw new \LogicException('The HTTP middleware pipeline did not return a response.');
        }

        return $response;
    }

    /**
     * Perform post-response telemetry analysis and persist findings.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! config('lynx.enabled', true)) {
            return;
        }

        try {
            $this->scanner->scan(persist: true);
        } catch (\Throwable $exception) {
            try {
                report($exception);
            } catch (\Throwable) {
                // Telemetry must never change the application response.
            }
        }
    }
}
