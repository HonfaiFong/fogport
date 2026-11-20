<?php
/**
 * PHPUnit 引导文件。
 *
 * 仅加载 Composer 自动加载与 .env，不启动 webman 内核。
 * 纯单元测试（如 UuidV7）不需要数据库与 Redis；
 * 需要数据库的 Feature 测试应自行在 setUp 中初始化连接。
 */

require __DIR__ . '/../vendor/autoload.php';

// 单元测试阶段可能访问 env()，需加载 .env（illuminate 的 env 依赖 phpdotenv）
if (class_exists(\Dotenv\Dotenv::class) && is_file(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();
}