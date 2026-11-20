<?php

namespace app\queue\redis;

use Webman\RedisQueue\Consumer;

/**
 * 异步任务消费者骨架（对应配置中的 slow-io 队列进程，Swoole 驱动）。
 *
 * 规范要求：HTTP/WS 进程严禁执行耗时外部 I/O，必须投递到此异步队列消费。
 * 典型任务：三方内容审核 API、APNs/FCM/厂商推送、废弃媒体（RustFS）清理。
 */
class SlowIoConsumer implements Consumer
{
    // 队列名
    public string $queue = 'slow_io';

    // 连接名，对应 config/plugin/webman/redis-queue/redis.php
    public string $connection = 'default';

    /**
     * @param array $data 队列数据
     * @return bool 返回 false 时消息会被重新投递（配合 max_attempts 重试）
     */
    public function consume($data): bool
    {
        // TODO: 按 data['task'] 分派到对应 Module Service
        return true;
    }
}