<?php

/**
 * Phinx 数据库迁移配置（webman 官方推荐方案）。
 *
 * 连接参数统一从 .env 读取，与 config/database.php 保持同一份来源，
 * 避免出现「迁移连 A 库、业务连 B 库」的不一致。
 */

// 直接解析 .env，因为 Phinx 以独立 CLI 进程运行，不经过 webman 的 Dotenv 引导
$envFile = __DIR__ . '/.env';
$env = static function (string $key, $default = null) use ($envFile) {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
};

return [
    'paths' => [
        'migrations' => 'database/migrations',
        'seeds' => 'database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'dev',
        'dev' => [
            'adapter' => 'pgsql',
            'host' => $env('DB_HOST', '127.0.0.1'),
            'name' => $env('DB_DATABASE', 'fogport'),
            'user' => $env('DB_USERNAME', 'fogport'),
            'pass' => $env('DB_PASSWORD', ''),
            'port' => (int) $env('DB_PORT', 5432),
            'charset' => 'utf8',
            // 单库单 schema，与 config/database.php 一致
            'schema' => 'public',
        ],
    ],
];