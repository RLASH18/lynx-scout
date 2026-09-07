<p align="center">
  <img src="./lynx.png" width="190" alt="Lynx Scout Mascot">
</p>

<h1 align="center">LYNX SCOUT</h1>

<p align="center">
  <strong>Automated Performance Intelligence and Actionable Recommendations for Laravel 13</strong>
</p>

<p align="center">
  <em>"See what your Laravel application is trying to tell you."</em>
</p>

<p align="center">
  <a href="https://packagist.org/packages/rlash18/lynx-scout"><img src="https://img.shields.io/badge/packagist-v1.0.0-f59e0b.svg?style=for-the-badge&logo=packagist&logoColor=white" alt="Packagist"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13.x-ff2d20.svg?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777bb4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-10b981.svg?style=for-the-badge" alt="MIT License"></a>
</p>

---

## The Observer and Advisor Principle

Traditional profilers dump thousands of raw log lines and queries into your console. **Lynx Scout** takes a fundamentally different path: it acts as an observant scout that passively profiles runtime telemetry, correlates patterns across requests and database queries, and gives you prioritized, evidence-backed advice.

```text
  [ Observe ]  ──▶ Passively captures queries, requests, and queue timings
       │
  [ Collect ]  ──▶ Sanitizes bindings, normalizes SQL, measures execution
       │
  [ Analyze ]  ──▶ Evaluates N+1 loops, slow queries, and cache potential
       │
 [ Correlate ] ──▶ Connects slow routes with their primary database root causes
       │
 [ Prioritize ]──▶ Calculates impact score: Cost × Volume × Confidence
       │
 [ Recommend ] ──▶ Delivers actionable solutions with zero automated code changes
```

> [!IMPORTANT]
> **Zero-Mutation Guarantee:** Lynx Scout will never alter your PHP source code, add database indexes, modify `.env`, or touch your production configuration. It is strictly an intelligence layer.

---

## Quickstart

### 1. Install via Composer
```bash
composer require rlash18/lynx-scout --dev
```

### 2. Publish Configuration (Optional)
```bash
php artisan vendor:publish --tag=lynx-config
```

### 3. Run Your First Scan
```bash
php artisan lynx:scan
```

```text
Lynx Scout

Scanning application...

✓ Requests analyzed
✓ Queries analyzed
✓ Performance patterns analyzed
✓ Findings prioritized

3 findings detected.

  1. N+1 query pattern detected ................. Critical
  2. Slow database query detected ............... High
  3. Potential cache candidate detected ......... Medium

Run php artisan lynx:report for detailed recommendations.
```

---

## Detections

| Bottleneck | Description | Example Threshold |
| :--- | :--- | :--- |
| **N+1 Relational Queries** | Loops executing child relationship lookups instead of batched eager loads. | `3+` child queries in request |
| **Slow Database Queries** | Individual queries exceeding execution duration limits. | `> 100ms` (configurable) |
| **Duplicate Query Patterns** | Identical or parameterized queries executing repeatedly in one request. | `2+` identical executions |
| **Slow HTTP Endpoints** | Routes where overall latency degrades user experience. | `> 500ms` (configurable) |
| **Cache Candidates** | Heavy, frequent read queries that could be safely cached in Redis/Memcached. | High frequency + high read time |
| **Correlated Bottlenecks** | Connects slow routes directly to their root cause (e.g. 85% DB time vs CPU lock). | Request duration vs DB time % |
| **Queue Performance** | Unusually slow background jobs and recurring job failure loops. | `> 2,000ms` execution |
| **Production Health** | Leaked `APP_DEBUG=true`, missing route/config caching, or inactive OPcache. | Production environment check |

---

## Recommendations

Every finding provides a structured breakdown explaining the cause and suggested solution:

```text
────────────────────────────────
Critical
────────────────────────────────
N+1 query pattern detected

Route:
GET /api/orders

Occurrences:
42

Duration:
680.50ms

Estimated Impact:
Critical (Score: 92.4)

Confidence:
High confidence (96%)

Recommendation:
Review relationship loading and consider eager loading.

Why:
The same relationship query is executed repeatedly in loops, adding unnecessary round-trip latency.

Example:
Order::with('customer')->get();

────────────────────────────────
```

---

## Artisan Command Suite

### 1. Live Runtime Scan
```bash
php artisan lynx:scan
```

### 2. Detailed Performance Report
```bash
# Human-readable report
php artisan lynx:report

# Filter by minimum severity
php artisan lynx:report --min-severity=high

# Machine-readable JSON output (ideal for CI/CD or custom dashboards)
php artisan lynx:report --json
```

### 3. Interactive Findings History
Inspect historical findings persisted in `storage/lynx`:
```bash
# View all recent findings in a formatted table
php artisan lynx:findings

# Filter by severity or category
php artisan lynx:findings --severity=critical
php artisan lynx:findings --type=n-plus-one
php artisan lynx:findings --recent
```

### 4. Performance Baselines and Snapshots
Capture your application's current health benchmark before making changes:
```bash
php artisan lynx:snapshot --name=v1.2-baseline
```

### 5. Regression Comparison and CI Gating
Compare two performance snapshots to catch speed degradations:
```bash
php artisan lynx:compare v1.2-baseline v1.3-release
```

```text
Performance Regression

/api/orders

Before:
184ms

After:
327ms

Regression:
+78%

Queries:
18 → 46

Status:
[!] Regression detected
```

#### Prevent Regressions in CI Pipelines:
```bash
# Exits with status code 1 if response times regress by >20% or query volume by >30%
php artisan lynx:compare baseline latest --fail-on-regression

# Custom regression threshold (e.g., allow up to 15%)
php artisan lynx:compare baseline latest --fail-on-regression --threshold=15
```

---

## Security and Privacy

Lynx Scout is designed from the ground up for strict data privacy:
- **Binding Masking:** Sensitive SQL parameters (passwords, auth tokens, JWTs, credit cards, bcrypt hashes) are automatically redacted with `********`.
- **Payload Truncation:** Large payloads exceeding 512 characters are truncated to protect worker memory.
- **Zero Request Body Logging:** Headers, bearer tokens, cookies, and raw request bodies are never recorded or stored.
- **100% On-Premises:** All data lives inside your local `storage/lynx` folder. Zero telemetry leaves your server.

---

## Configuration

```php
// config/lynx.php
return [
    'enabled' => env('LYNX_ENABLED', true),

    'environments' => ['local', 'testing', 'staging', 'production'],

    'query' => [
        'enabled' => true,
        'slow_threshold' => 100.0, // ms
        'duplicate_threshold' => 2,
        'n_plus_one_threshold' => 3,
        'sanitize_bindings' => true,
    ],

    'request' => [
        'enabled' => true,
        'slow_threshold' => 500.0, // ms
    ],

    'sampling' => [
        'rate' => env('LYNX_SAMPLING_RATE', 1.0), // 0.1 for 10% sampling in heavy traffic
    ],

    'ci' => [
        'regression_threshold' => 20.0, // 20% max degradation
        'query_count_threshold' => 30.0, // 30% max query growth
    ],
];
```

---

## Testing

Lynx Scout is thoroughly verified with **70 tests and 268 assertions** on Laravel 13 and PHP 8.4:

```bash
composer test
# OK (70 tests, 268 assertions)
```

---

## License

Lynx Scout is open-sourced software licensed under the **[MIT License](LICENSE)**.
