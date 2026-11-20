<?php

namespace app\queue\redis\slow;

use Webman\RedisQueue\Consumer;

/**
 * 内容机审消费者（独立进程池，Swoole 驱动）。
 *
 * 队列 `moderation-normal` 承载三方文本/图片内容安全审核。
 * 外部审核接口可能耗时数秒甚至超时，因此独立进程池 + Swoole 协程化，
 * 使其阻塞不影响推送与常规业务。
 */
class ModerationConsumer implements Consumer
{
    /** @var string 队列名，与投递端 Client::send('moderation-normal', ...) 保持一致 */
    public string $queue = 'moderation-normal';

    public string $connection = 'default';

    public function consume($data): void
    {
        // TODO: 委托 app\Modules\Safety\Service\ModerationService::handle($data)
        // 达阈值需触发 Shadowban：移出捞罐池 + 状态置 SHADOW_HIDDEN
    }
}