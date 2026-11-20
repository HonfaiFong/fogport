<?php
/**
 * 关闭插件自带的 consumer 进程。
 *
 * 原因：本项目在 config/process.php 中自行声明了 'task' 消费者进程，
 * 并单独指定 Swoole 驱动以承载慢 I/O。若此处再启用，会同时存在两批消费者
 * 争抢同一队列，且事件循环驱动不一致，难以排查。统一由 config/process.php 管理。
 */
return [
    'consumer' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'enable' => false,
        'count' => 8,
        'constructor' => [
            'consumer_dir' => app_path() . '/queue/redis'
        ]
    ]
];