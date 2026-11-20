<?php
/**
 * 全局启动引导（每个 Worker 进程启动时按顺序执行一次）。
 *
 * 注意：本项目为无状态 JSON API + WebSocket 服务，**不使用 Session**。
 * 因此这里刻意不注册 support\bootstrap\Session，config/session.php 也已删除。
 * 登录态一律通过 JWT（AccessToken + RefreshToken）承载，中间件解析后注入请求上下文。
 */

return [
    // 注册 Illuminate Database：查询构造器、Eloquent、DB Facade
    app\bootstrap\Database::class,
];