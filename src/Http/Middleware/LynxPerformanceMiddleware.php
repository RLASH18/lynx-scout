<?php

declare(strict_types=1);

namespace Lynx\Scout\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Lynx\Scout\Collectors\QueryCollector;
use Lynx\Scout\Collectors\RequestCollector;
use Lynx\Scout\Data\RequestRecord;
use Symfony\Component\HttpFoundation\Response;

class LynxPerformanceMiddleware
{
    public function __construct(
        private readonly RequestCollector $requestCollector,
        private readonly QueryCollector $queryCollector,
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

        $startTime = microtime(true);
        $startQueryCount = $this->queryCollector->count();
        $startQueryTime = $this->queryCollector->totalTimeMs();

        $this->queryCollector->setContext([
            'uri' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
        ]);

        $response = $next($request);

        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;
        $action = is_object($route) && method_exists($route, 'getActionName') ? $route->getActionName() : null;

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $queryCount = max(0, $this->queryCollector->count() - $startQueryCount);
        $queryTimeMs = round(max(0.0, $this->queryCollector->totalTimeMs() - $startQueryTime), 2);
        $memoryBytes = memory_get_peak_usage(true);

        $record = new RequestRecord(
            id: bin2hex(random_bytes(8)),
            method: $request->method(),
            uri: '/' . ltrim($request->path(), '/'),
            routeName: $routeName,
            action: $action,
            durationMs: $durationMs,
            statusCode: $response->getStatusCode(),
            queryCount: $queryCount,
            queryTimeMs: $queryTimeMs,
            memoryBytes: $memoryBytes,
            requestedAt: new DateTimeImmutable(),
        );

        $this->requestCollector->record($record);

        return $response;
    }
}
