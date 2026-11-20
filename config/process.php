<?php
/**
 * 进程声明：HTTP + WebSocket + 双优先级队列消费池 + Crontab + Monitor
 *
 * 事件循环分派策略（核心）：
 * - 全局默认使用 Event 驱动（libevent，C 扩展，无协程开销），承载日常高频 CRUD 的 HTTP/WS 服务，稳健高效。
 * - 慢 I/O 进程单独设为 Swoole 驱动，利用 SWOOLE_HOOK_ALL 把阻塞式 curl/PDO/文件 IO 自动协程化。
 *
 * 队列隔离策略（防队头阻塞）：
 * - consumer_fast：push-high，离线推送，要求秒级送达，Event 驱动
 * - consumer_slow：moderation-normal / cleanup-low，外部慢 I/O，Swoole 驱动 + 独立进程池
 *   两层隔离（队列名 + 进程池）确保机审超时不会拖垮推送。
 *
 * 注意：Worker 的 eventLoop 是进程级属性，webman 的 worker_start() 会透传本文件的 eventLoop 字段，
 * 因此同一套代码库内可以按进程混用两种事件循环。
 */

use support\Log;
use support\Request;
use app\process\Http;
use Workerman\Events\Event;
use Workerman\Events\Swoole;

global $argv;

return [
    // 主 HTTP 服务：高频 CRUD，保持 Event 驱动
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8787',
        'count' => cpu_count() * 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => Event::class,
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],

    // WebSocket 长连接：高并发连接管理，用 Event 驱动（libevent 支持大 fd 集）
    'websocket' => [
        'handler' => app\process\Websocket::class,
        'listen' => 'websocket://0.0.0.0:8788',
        'count' => 1,
        'eventLoop' => Event::class,
        // 本地开发明文直连；生产由反向代理终结 TLS，因此无需 ssl 上下文
        'context' => [],
    ],

    // 高时效性推送队列：离线 Push 要求秒级送达，独立进程池 + Event 驱动。
    // 推送本身是无阻塞的 HTTP 调用且量级可控，不需要协程化，用 Event 更轻。
    'consumer_fast' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 4,
        'eventLoop' => Event::class,
        'constructor' => [
            'consumer_dir' => base_path() . '/app/queue/redis/fast'
        ]
    ],

    // 慢 I/O 队列：三方内容机审、RustFS 媒体清理，独立进程池 + Swoole 驱动，
    // 利用 SWOOLE_HOOK_ALL 协程化阻塞调用，超时也不影响上面的推送池。
    'consumer_slow' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 2,
        'eventLoop' => Swoole::class,
        'constructor' => [
            'consumer_dir' => base_path() . '/app/queue/redis/slow'
        ]
    ],

    // 定时任务：修剪捞罐池 ZSET、清理超时闪聊节点
    'crontab' => [
        'handler' => app\process\Crontab::class,
        'count' => 1,
        'eventLoop' => Event::class,
    ],

    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'eventLoop' => Event::class,
        'constructor' => [
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ]
        ]
    ]
];