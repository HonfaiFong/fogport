<?php
/**
 * 业务 Redis 连接配置（与 webman/redis-queue 的队列连接相互独立）。
 *
 * 用途：捞罐池 ZSET、已捞去重 Set、WS 一次性票据（GETDEL）、限流、双向黑名单缓存等。
 *
 * 设计要点：
 * - default 连接统一加 `fogport:` 键前缀，对应 PROJECT_SPEC.md 第 6 节键空间规范。
 * - pubsub 连接**刻意不加前缀**：Redis Pub/Sub 的频道名不参与键前缀改写，
 *   若加了前缀会导致 HTTP 进程与 WS 进程订阅的频道名不一致，消息永远收不到。
 * - 每个连接各自独立连接池，避免 Pub/Sub 长连接阻塞普通命令连接。
 */
return [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD') ?: null,
        'port' => (int) env('REDIS_PORT', 6379),
        'database' => (int) env('REDIS_DB', 0),
        'options' => [
            // 全局键前缀，遵循 spec「所有 Redis Key 必须带 fogport: 前缀」
            'prefix' => 'fogport:',
        ],
        'pool' => [
            'max_connections' => 10,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],

    // HTTP 进程 -> WebSocket 进程的实时帧广播专用连接（发布端）
    'pubsub' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD') ?: null,
        'port' => (int) env('REDIS_PORT', 6379),
        'database' => (int) env('REDIS_PUBSUB_DB', 0),
        // 注意：此连接不加 prefix，频道名保持原样（如 fogport:ws:bus 已在代码中写全）
        'pool' => [
            'max_connections' => 2,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];