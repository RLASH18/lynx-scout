# Changelog

All notable changes to `rlash18/lynx-scout` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-08

### Added
- Complete automated performance intelligence and recommendation package for Laravel 13.
- Query collector with SQL normalization, stack-trace caller detection, and binding sanitization.
- Slow query detector with configurable duration thresholds and severity levels.
- Duplicate query pattern detector with SQL statement normalization.
- High-accuracy N+1 relational query detection with confidence scoring.
- HTTP request performance profiling middleware tracking latency, memory, query counts, and route names.
- Multi-domain performance correlation engine diagnosing primary root causes.
- Prioritized impact scoring engine combining cost, volume, and detection confidence.
- Structured, actionable recommendation engine with non-destructive code examples.
- Cache candidate analysis for frequent, read-heavy query patterns.
- Application health checks (APP_DEBUG in production, config/route caching, OPcache status).
- Queue performance analysis and repeated job failure tracking.
- Persistent finding storage with fingerprint aggregation and retention pruning.
- Artisan commands:
  - `php artisan lynx:scan` — prioritized console performance scan.
  - `php artisan lynx:report` — human-readable and structured `--json` reporting.
  - `php artisan lynx:findings` — interactive historical findings inspection with filters.
  - `php artisan lynx:snapshot` — reproducible performance state captures.
  - `php artisan lynx:compare` — regression comparison with `--fail-on-regression` CI support.
- Configurable sampling rate and memoization caching to minimize runtime overhead.
- Comprehensive security and privacy review with JWT and bcrypt parameter masking.

## [0.1.0] - 2026-09-08

### Added
- Initial package structure and metadata for Laravel 13.
- `LynxServiceProvider` foundation with auto-discovery support.
- Base test environment with Orchestra Testbench.
