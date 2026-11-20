<?php

/**
 * PostgreSQL 16 连接配置（illuminate/database）。
 * 全站主键为 UUIDv7（应用层生成），时间字段统一 TIMESTAMPTZ。
 */
return [
    'default' => 'pgsql',

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'fogport'),
            'username' => env('DB_USERNAME', 'fogport'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
            // 常驻内存进程必须关闭持久连接，避免连接数泄漏
            'options' => [
                \PDO::ATTR_PERSISTENT => false,
            ],
        ],
    ],

    // 迁移目录：database/migrations
    'migrations' => 'database/migrations',
];