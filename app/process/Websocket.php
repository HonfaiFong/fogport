<?php

namespace app\process;

use Workerman\Connection\TcpConnection;
use Workerman\Timer;

/**
 * WebSocket 长连接进程骨架。
 *
 * 建立流程（后续在此实现）：
 * 1. 客户端先调 POST /v1/ws/ticket 取得 30 秒一次性票据 nonce；
 * 2. 连接 ws://host:8788?ticket={nonce}，此处用 GETDEL 原子消费票据防重放；
 * 3. 维护 user_id -> connections 映射，订阅 Redis Pub/Sub（yg:ws:bus）转发实时帧；
 * 4. 心跳：25 秒间隔，10 秒内无心跳则断开。
 */
class Websocket
{
    public function onWorkerStart(): void
    {
        // 心跳扫描：清理超时未发心跳的连接
        Timer::add(25, static function (): void {
            // TODO: 遍历连接，对比最后活跃时间，超时则 close
        });
    }

    public function onConnect(TcpConnection $connection): void
    {
        // TODO: 校验收到的 ticket 是否有效（Redis GETDEL），无效立即断开
    }

    public function onMessage(TcpConnection $connection, mixed $data): void
    {
        // TODO: 解析 JSON 信封 {type, ts, data}，处理 PING/PONG 等控制帧
    }

    public function onClose(TcpConnection $connection): void
    {
        // TODO: 从 user_id -> connections 映射中移除，清理在线标记
    }
}