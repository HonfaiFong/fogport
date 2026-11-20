<?php

/**
 * Redis 7 连接配置（供 webman/redis-queue 与业务缓存使用）。
 * 所有业务 Key 必须带统一前缀 yg:，见 PROJECT_SPEC.md 第 6 节。
 */
return [
    'default' => [
        'host' => 'redis://' . env('REDIS_HOST', '127.0.0.1') . ':' . env('REDIS_PORT', '6379'),
        'options' => [
            'auth' => env('REDIS_PASSWORD', '') ?: null,
            'db' => (int) env('REDIS_DB', 0),
            'prefix' => env('REDIS_PREFIX', 'yg:'),
            'max_attempts' => 5,
            'retry_seconds' => 5,
        ],
        // 连接池仅在 Swoole / Swow 驱动下生效，Event 驱动进程会忽略
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];