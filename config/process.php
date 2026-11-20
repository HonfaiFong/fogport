<?php
/**
 * 进程声明：HTTP + WebSocket + RedisQueue + Crontab
 *
 * 事件循环分派策略（核心）：
 * - 全局默认使用 Event 驱动（libevent，C 扩展，无协程开销），承载日常高频 CRUD 的 HTTP/WS 服务，稳健高效。
 * - 仅"慢 I/O"进程（第三方 AI 接口、消息推送、异步队列消费）单独设为 Swoole 驱动，
 *   以便利用 SWOOLE_HOOK_ALL 把阻塞式 curl/PDO/文件 IO 自动协程化，不阻塞 Worker。
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

    // 异步任务队列：慢 I/O 集中于此，使用 Swoole 驱动获得协程化阻塞调用
    'task' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 4,
        'eventLoop' => Swoole::class,
        'constructor' => [
            'consumer_dir' => base_path() . '/app/queue/redis'
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