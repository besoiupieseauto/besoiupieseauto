<?php

declare(strict_types=1);

/**
 * Config central joburi asincrone — valori din .env cu fallback sigur.
 */
return [
    'redis_host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'redis_port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
    'redis_timeout' => (float) ($_ENV['REDIS_TIMEOUT'] ?? 2.0),
    'redis_prefix' => $_ENV['REDIS_PREFIX'] ?? 'besoiu:async:',
    'fallback_to_files' => filter_var($_ENV['ASYNC_FILE_FALLBACK'] ?? '1', FILTER_VALIDATE_BOOLEAN),

    'default_job_timeout_sec' => (int) ($_ENV['ASYNC_JOB_TIMEOUT_SEC'] ?? 60),
    'worker_idle_sleep_ms' => (int) ($_ENV['ASYNC_WORKER_IDLE_MS'] ?? 250),
    'worker_max_jobs_idle_sec' => (int) ($_ENV['ASYNC_WORKER_MAX_JOBS_IDLE_SEC'] ?? 8),
    'worker_heartbeat_sec' => (int) ($_ENV['ASYNC_WORKER_HEARTBEAT_SEC'] ?? 15),

    'sse_max_connection_sec' => (int) ($_ENV['ASYNC_SSE_MAX_SEC'] ?? 85),
    'sse_poll_interval_ms' => (int) ($_ENV['ASYNC_SSE_POLL_MS'] ?? 1000),
    'sse_inactivity_sec' => (int) ($_ENV['ASYNC_SSE_INACTIVITY_SEC'] ?? 30),

    'idempotency_ttl_sec' => (int) ($_ENV['ASYNC_IDEMPOTENCY_TTL_SEC'] ?? 86400),
    'job_max_attempts' => (int) ($_ENV['ASYNC_JOB_MAX_ATTEMPTS'] ?? 3),

    'circuit_failure_threshold' => (int) ($_ENV['ASYNC_CB_FAILURES'] ?? 5),
    'circuit_cooldown_sec' => (int) ($_ENV['ASYNC_CB_COOLDOWN_SEC'] ?? 60),

    'storage_dir' => dirname(__DIR__) . '/storage/async_jobs',

    // Tip job => [queue, max_workers, timeout_sec]
    'queues' => [
        'import' => ['max_workers' => 2, 'timeout_sec' => 3600],
        'products' => ['max_workers' => 3, 'timeout_sec' => 60],
        'images' => ['max_workers' => 2, 'timeout_sec' => 600],
        'tecdoc' => ['max_workers' => 1, 'timeout_sec' => 3600],
        'reports' => ['max_workers' => 1, 'timeout_sec' => 90],
        'ai-events' => ['max_workers' => 2, 'timeout_sec' => 30],
    ],
];
