# Changelog

All notable changes to `rlash18/lynx-scout` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Fixed `NPlusOneDetector` misclassifying identical queries as N+1 by checking parameter signature variation across executions.
- Suppressed duplicate query and cache candidate findings when an N+1 pattern has already been diagnosed for the same query.
- Enhanced `ReportCommand` and `ReportGenerator` to display normalized SQL patterns for distinct findings from the same caller.
- Added `--clear` flag to `php artisan lynx:findings` to allow clearing stored findings.

## [1.2.0] - 2026-09-10

### Changed
- Prevented duplicate performance middleware registration.
- Added request-scoped query accounting and long-running worker collector cleanup.
- Made detector grouping request-aware and improved finding fingerprints.
- Added atomic, locked file persistence and safe snapshot identifiers.
- Fixed overall query regression gating, recommendation toggles, retention cleanup, and nested binding sanitization.

## [1.1.0] - 2026-09-09

### Added
- Termwind CLI command suite redesign featuring enclosed dashboard cards, healthbar widgets, and box-drawing tables.
- Tagged container architecture allowing custom detectors to be registered via `$this->app->tag([...], 'lynx.detectors.query')`.
- Configurable in-memory collector limits (`lynx.collectors.max_queries`, `max_requests`, `max_queue_jobs`).
- Caller detection sampling (`lynx.callers.sample_rate`), enable/disable toggle, and custom ignored namespaces.
- Configurable impact scoring severity weights (`lynx.scoring.weights`).
- Argon2id and Argon2i hash redaction, PEM private key masking, and custom hidden patterns in `BindingSanitizer`.
- Safe JSON validation using native PHP 8.3 `json_validate()` in repository drivers.
- In-memory LRU cache eviction and `flushCache()` in `SqlNormalizer` for Laravel Octane / FrankenPHP memory safety.
- Expanded test suite to 92 tests and 320 assertions with dedicated tests for detectors, normalizer, and storage edge cases.
- Comprehensive configuration and custom detector documentation for Laravel 12 and 13.

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
