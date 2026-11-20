<?php

namespace app\queue\redis\fast;

use Webman\RedisQueue\Consumer;

/**
 * 高时效性推送消费者（独立进程池，Event 驱动）。
 *
 * 队列 `push-high` 承载离线私聊推送、站内通知推送，要求秒级送达。
 * 之所以独立成池：慢 I/O 的机审/媒体清理若与推送共用进程，
 * 一旦外部接口超时会直接堵死推送，私聊消息延迟不可接受。
 *
 * 本类保持极薄，仅做反序列化与委托，业务逻辑在 Notification 模块。
 */
class PushConsumer implements Consumer
{
    /** @var string 队列名，与投递端 Client::send('push-high', ...) 保持一致 */
    public string $queue = 'push-high';

    /** @var string 使用 redis-queue 插件自身连接，避免与业务 Redis 连接混用 */
    public string $connection = 'default';

    public function consume($data): void
    {
        // TODO: 委托 app\Modules\Notification\Service\PushDispatchService::dispatch($data)
        // 需实现 PROJECT_SPEC.md 规则 6「锁屏脱敏推送」：严禁透出真实昵称与敏感原文
    }
}