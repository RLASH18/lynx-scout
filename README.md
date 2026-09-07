# Lynx Scout

[![Latest Version on Packagist](https://img.shields.io/badge/packagist-v1.0.0-blue.svg)](https://packagist.org/packages/rlash18/lynx-scout)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-indigo.svg)](https://php.net)

> **Automated performance intelligence and recommendations for Laravel.**  
> *See what your Laravel application is trying to tell you.*

---

## 1. What is Lynx Scout?

**Lynx Scout** is an automated performance intelligence and recommendation package designed for modern Laravel applications. It continuously and transparently observes your application's runtime behavior, detects potential performance bottlenecks, analyzes root causes, prioritizes findings by estimated impact, and generates actionable recommendations for developers.

The package embraces an **Observer and Advisor** model:

```text
Observe  ──▶  Collect  ──▶  Analyze  ──▶  Correlate  ──▶  Prioritize  ──▶  Recommend
```

> **Important Guarantee:**  
> **Lynx Scout provides recommendations and does not automatically modify your application.**  
> It will never alter your PHP code, generate database indexes, alter migrations, manipulate `.env` variables, or modify production configurations automatically. You remain in full control.

---

## 2. Why It Exists

Modern Laravel applications grow rapidly in complexity. Often, performance degrades gradually due to:
- Subtle **N+1 queries** introduced by nested Blade loops or API resource transformers.
- **Duplicate query patterns** executing dozens of times within a single HTTP request.
- Individual **slow database queries** that lack composite indexes.
- Endpoints where database queries are fast, but excessive **application processing or external API calls** dominate runtime.
- Misconfigured production environments where debug flags or uncompiled caches harm response latency.

Traditional profilers overwhelm developers with raw dumps of every query. Lynx Scout cuts through the noise by correlating events, calculating confidence scores, estimating impact, and telling you **what to investigate first**.

---

## 3. Installation

Install Lynx Scout via Composer into your Laravel application:

```bash
composer require rlash18/lynx-scout --dev
```

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=lynx-config
```

---

## 4. Configuration

The published `config/lynx.php` file provides straightforward controls over all monitoring features:

```php
return [

    // Master enable switch
    'enabled' => env('LYNX_ENABLED', true),

    // Environments permitted to collect telemetry
    'environments' => [
        'local',
        'testing',
        'staging',
        'production',
    ],

    // Database query monitoring
    'query' => [
        'enabled' => env('LYNX_QUERY_ENABLED', true),
        'slow_threshold' => (float) env('LYNX_SLOW_QUERY_THRESHOLD', 100.0), // ms
        'duplicate_threshold' => (int) env('LYNX_DUPLICATE_QUERY_THRESHOLD', 2),
        'n_plus_one_threshold' => (int) env('LYNX_N_PLUS_ONE_THRESHOLD', 3),
        'record_bindings' => env('LYNX_RECORD_BINDINGS', true),
        'sanitize_bindings' => env('LYNX_SANITIZE_BINDINGS', true),
    ],

    // HTTP request monitoring
    'request' => [
        'enabled' => env('LYNX_REQUEST_ENABLED', true),
        'slow_threshold' => (float) env('LYNX_SLOW_REQUEST_THRESHOLD', 500.0), // ms
    ],

    // Cache candidate analysis
    'cache' => [
        'enabled' => env('LYNX_CACHE_ENABLED', true),
        'candidate_frequency_threshold' => (int) env('LYNX_CACHE_CANDIDATE_THRESHOLD', 5),
    ],

    // Health and runtime configuration checks
    'health' => [
        'enabled' => env('LYNX_HEALTH_ENABLED', true),
    ],

    // Queue job monitoring
    'queue' => [
        'enabled' => env('LYNX_QUEUE_ENABLED', true),
        'slow_job_threshold' => (float) env('LYNX_SLOW_JOB_THRESHOLD', 2000.0), // ms
    ],

    // Persistent storage for historical findings
    'storage' => [
        'driver' => env('LYNX_STORAGE_DRIVER', 'file'), // 'file' or 'memory'
        'path' => storage_path('lynx'),
        'max_findings' => (int) env('LYNX_MAX_FINDINGS', 500),
        'retention_days' => (int) env('LYNX_RETENTION_DAYS', 7),
    ],

    // CI and regression comparison thresholds
    'ci' => [
        'regression_threshold' => (float) env('LYNX_CI_REGRESSION_THRESHOLD', 20.0), // 20%
        'query_count_threshold' => (float) env('LYNX_CI_QUERY_COUNT_THRESHOLD', 30.0), // 30%
    ],

];
```

---

## 5. Automatic Monitoring

Once installed and enabled, Lynx Scout automatically attaches non-intrusive listeners:
- **Database Queries**: Automatically listens to `Illuminate\Database\Events\QueryExecuted`. Queries are normalized and analyzed for duration and repetition.
- **HTTP Requests**: Measures endpoint durations, query counts, and memory consumption through global middleware.
- **Queue Jobs**: Listens to queue lifecycle events (`JobProcessing`, `JobProcessed`, `JobFailed`) to profile background job duration and track failure counts.

---

## 6. Supported Detections

| Detection | Description | Example Condition |
| :--- | :--- | :--- |
| **Slow Queries** | Individual queries exceeding execution duration threshold. | Query took 428ms (threshold 100ms) |
| **Duplicate Queries** | Repetitive identical or parameterized queries within the same request. | Query pattern executed 42 times |
| **N+1 Relational Patterns** | Relationship query loops executing across model instances. | Parent query followed by 10+ child lookups |
| **Slow HTTP Requests** | Endpoints whose overall response latency exceeds target threshold. | Route duration > 500ms |
| **Potential Cache Candidates** | Expensive, read-heavy query patterns running with high frequency. | SELECT query executed 8,400 times |
| **Correlated Bottlenecks** | Cross-domain correlation between slow requests, DB time, and app logic. | 85% of request latency spent in DB |
| **Slow Queue Jobs** | Background jobs running longer than expected. | Queue job exceeded 2,000ms |
| **Repeated Job Failures** | Background jobs failing multiple times during execution window. | Job failed 3 times |
| **Application Health Concerns** | Production environment configuration checks (APP_DEBUG, caching). | `APP_DEBUG=true` in production |

---

## 7. Recommendations

Every recommendation generated by Lynx Scout is structured and actionable:

```text
Finding:
N+1 Query Pattern

Evidence:
142 repeated queries during GET /posts (850.00ms total).

Impact:
Critical (Score: 92.4)

Confidence:
High confidence (96%)

Recommendation:
Review relationship loading and consider eager loading.

Why:
The same relationship query is executed repeatedly in loops, adding unnecessary round-trip latency.

Example:
Post::with('author')->get();
```

---

## 8. Artisan Commands

### `php artisan lynx:scan`
Performs an on-demand scan of runtime telemetry and outputs prioritized findings:

```bash
php artisan lynx:scan
```

### `php artisan lynx:report`
Generates a detailed, human-readable performance report:

```bash
php artisan lynx:report
php artisan lynx:report --min-severity=high
```

### `php artisan lynx:findings`
Inspects stored historical findings with interactive filters:

```bash
php artisan lynx:findings
php artisan lynx:findings --severity=critical
php artisan lynx:findings --type=n-plus-one
php artisan lynx:findings --recent
```

### `php artisan lynx:snapshot`
Captures current application performance state into a baseline snapshot:

```bash
php artisan lynx:snapshot
php artisan lynx:snapshot --name=release-2.4
```

### `php artisan lynx:compare`
Compares two snapshots to identify performance regressions:

```bash
php artisan lynx:compare baseline-v1 release-2.4
```

---

## 9. JSON Output

Lynx Scout outputs structured, machine-readable JSON for dashboards and external tooling:

```bash
php artisan lynx:report --json
```

Example output:
```json
{
  "package": "rlash18/lynx-scout",
  "version": "1.0.0",
  "generated_at": "2026-09-08T01:30:00+00:00",
  "summary": {
    "total_findings": 2,
    "critical": 1,
    "high": 1,
    "medium": 0,
    "low": 0,
    "info": 0
  },
  "findings": [
    {
      "id": "c8f2b3e4",
      "type": "n_plus_one",
      "severity": "critical",
      "title": "N+1 query pattern detected",
      "score": 92.4,
      "impact": "Critical",
      "recommendation": "Review relationship loading and consider eager loading."
    }
  ]
}
```

---

## 10. Snapshots

Snapshots capture the exact performance profile of your application at a given moment:
- Average request duration
- Total query volume
- Slow query counts
- Active findings and impact scores
- Per-route duration and query benchmarks

Snapshots are stored in `storage/lynx/snapshots` as lightweight JSON files.

---

## 11. CI/CD Usage

Integrate Lynx Scout into your continuous integration workflow to catch regressions before deployment:

```bash
# Fail CI build if response times regress by >20% or query counts by >30%
php artisan lynx:compare baseline latest --fail-on-regression

# Custom threshold for major refactors (e.g. allow up to 15% regression)
php artisan lynx:compare baseline latest --fail-on-regression --threshold=15
```

---

## 12. Privacy Considerations

Lynx Scout is designed with data security in mind:
- **Binding Sanitization**: Passwords, API tokens, auth secrets, and card numbers in SQL bindings are automatically masked (`********`).
- **Payload Truncation**: Abnormally large bindings are truncated to prevent memory overhead and sensitive data leaks.
- **Local Storage**: All telemetry and snapshots remain on your server inside `storage/lynx`. No data is sent to external cloud services.

---

## 13. Performance Overhead

Lynx Scout is built to be lightweight:
- Query normalization uses fast, single-pass regular expressions.
- Storage writes are aggregated by fingerprint to avoid database write storms.
- In-memory collectors enforce strict bounds (e.g., max 1,000 queries, 500 requests) to guarantee constant memory usage.
- In high-throughput environments, set `LYNX_SAMPLING_RATE=0.1` to sample 10% of requests.

---

## 14. Limitations

- **Probabilistic Heuristics**: Performance detections are based on runtime statistical heuristics and confidence scores. They should be evaluated alongside application domain context.
- **Advisory Only**: Lynx Scout does not apply code or schema changes. Optimizations must be reviewed and deployed by engineering teams.
- **In-Memory Storage Cap**: In-memory collectors profile active requests and periodic windows; long-term trend analysis requires snapshots.

---

## 15. Supported Laravel & PHP Versions

- **PHP**: `^8.3 || ^8.4`
- **Laravel**: `^13.0` (also compatible with `^12.0`)

---

## 16. Contributing

Contributions are welcome! Please submit Pull Requests with comprehensive unit tests and adhere to PSR-12 coding standards.

```bash
composer test
```

---

## 17. License

Lynx Scout is open-sourced software licensed under the **[MIT License](LICENSE)**.
