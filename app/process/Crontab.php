<?php

namespace app\process;

use Workerman\Crontab\Crontab as WorkermanCrontab;

/**
 * 定时任务进程骨架。
 *
 * 计划任务（后续在此实现）：
 * - 每小时：修剪 Redis 捞罐池 yg:sea:pool:{sea_id}，每个情绪海仅保留最新 5000 条；
 * - 每分钟：清理闪聊队列 yg:flash:queue:{tag} 中超时（>30s）的等待节点。
 */
class Crontab
{
    public function onWorkerStart(): void
    {
        // 每小时修剪捞罐池
        new WorkermanCrontab('0 * * * *', static function (): void {
            // TODO: ZREMRANGEBYRANK 保留头部 5000 条
        });

        // 每分钟清理超时闪聊节点
        new WorkermanCrontab('* * * * *', static function (): void {
            // TODO: ZREMRANGEBYSCORE 移除入队时间早于 now-30s 的节点
        });
    }
}