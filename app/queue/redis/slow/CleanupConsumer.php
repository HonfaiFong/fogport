<?php

namespace app\queue\redis\slow;

use Webman\RedisQueue\Consumer;

/**
 * 低优先级清理消费者（独立进程池，Swoole 驱动）。
 *
 * 队列 `cleanup-low` 承载 RustFS 废弃媒体删除、异步统计归档等。
 * 允许延迟，但同样涉及外部慢 I/O（对象存储接口），故放在 Swoole 池。
 */
class CleanupConsumer implements Consumer
{
    /** @var string 队列名，与投递端 Client::send('cleanup-low', ...) 保持一致 */
    public string $queue = 'cleanup-low';

    public string $connection = 'default';

    public function consume($data): void
    {
        // TODO: 委托 app\Modules\Can\Service\MediaCleanupService::handle($data)
    }
}