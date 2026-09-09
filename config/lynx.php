<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Lynx Scout Master Switch
    |--------------------------------------------------------------------------
    |
    | Master enable switch for Lynx Scout runtime monitoring. When disabled,
    | no listeners, collectors, or analyzers will hook into the application.
    |
    */
    'enabled' => env('LYNX_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Environments where Lynx Scout is permitted to collect performance data.
    | An empty array or '*' permits all environments.
    |
    */
    'environments' => [
        'local',
        'testing',
        'staging',
        'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Monitoring
    |--------------------------------------------------------------------------
    |
    | Options governing SQL query observation, slow query thresholds,
    | duplicate detection, and binding sanitization for security.
    |
    */
    'query' => [
        'enabled' => env('LYNX_QUERY_ENABLED', true),
        'slow_threshold' => (float) env('LYNX_SLOW_QUERY_THRESHOLD', 100.0), // ms
        'duplicate_threshold' => (int) env('LYNX_DUPLICATE_QUERY_THRESHOLD', 2),
        'n_plus_one_threshold' => (int) env('LYNX_N_PLUS_ONE_THRESHOLD', 3),
        'record_bindings' => env('LYNX_RECORD_BINDINGS', true),
        'sanitize_bindings' => env('LYNX_SANITIZE_BINDINGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for tracking route performance, execution duration, and memory.
    |
    */
    'request' => [
        'enabled' => env('LYNX_REQUEST_ENABLED', true),
        'slow_threshold' => (float) env('LYNX_SLOW_REQUEST_THRESHOLD', 500.0), // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Candidate Analysis
    |--------------------------------------------------------------------------
    |
    | Detects expensive, repeated queries that may benefit from application caching.
    |
    */
    'cache' => [
        'enabled' => env('LYNX_CACHE_ENABLED', true),
        'candidate_frequency_threshold' => (int) env('LYNX_CACHE_CANDIDATE_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Health Checks
    |--------------------------------------------------------------------------
    |
    | Analyzes runtime configuration (e.g., APP_DEBUG, config/route caching).
    |
    */
    'health' => [
        'enabled' => env('LYNX_HEALTH_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Tracks job execution times, failures, and slow background processing.
    |
    */
    'queue' => [
        'enabled' => env('LYNX_QUEUE_ENABLED', true),
        'slow_job_threshold' => (float) env('LYNX_SLOW_JOB_THRESHOLD', 2000.0), // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Recommendations
    |--------------------------------------------------------------------------
    |
    | Enables actionable advisory feedback based on observed performance findings.
    |
    */
    'recommendations' => [
        'enabled' => env('LYNX_RECOMMENDATIONS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | Value between 0.0 and 1.0 (1.0 = 100% of requests analyzed).
    |
    */
    'sampling' => [
        'rate' => (float) env('LYNX_SAMPLING_RATE', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistent Storage
    |--------------------------------------------------------------------------
    |
    | Storage driver and retention configuration for historical performance findings.
    |
    */
    'storage' => [
        'driver' => env('LYNX_STORAGE_DRIVER', 'file'), // 'file' or 'memory'
        'path' => storage_path('lynx'),
        'max_findings' => (int) env('LYNX_MAX_FINDINGS', 500),
        'retention_days' => (int) env('LYNX_RETENTION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | In-Memory Collectors Capacity Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of records retained in memory before eviction or capping.
    |
    */
    'collectors' => [
        'max_queries' => (int) env('LYNX_MAX_QUERIES', 1000),
        'max_requests' => (int) env('LYNX_MAX_REQUESTS', 500),
        'max_queue_jobs' => (int) env('LYNX_MAX_QUEUE_JOBS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caller Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for identifying origin caller location from query execution stack.
    |
    */
    'callers' => [
        'enabled' => env('LYNX_CALLERS_ENABLED', true),
        'sample_rate' => (float) env('LYNX_CALLERS_SAMPLE_RATE', 1.0),
        'ignored_namespaces' => [
            'Illuminate\\',
            'Lynx\\Scout\\',
            'Laravel\\',
            'Symfony\\',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Normalizer Cache
    |--------------------------------------------------------------------------
    */
    'normalizer' => [
        'cache_size' => (int) env('LYNX_NORMALIZER_CACHE_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Baseline scoring weights assigned to findings by severity level.
    |
    */
    'scoring' => [
        'weights' => [
            'critical' => 45.0,
            'high' => 35.0,
            'medium' => 25.0,
            'low' => 15.0,
            'info' => 5.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CI & Regression Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds used during CI regression checks and comparisons.
    |
    */
    'ci' => [
        'regression_threshold' => (float) env('LYNX_CI_REGRESSION_THRESHOLD', 20.0), // 20%
        'query_count_threshold' => (float) env('LYNX_CI_QUERY_COUNT_THRESHOLD', 30.0), // 30%
    ],

];
