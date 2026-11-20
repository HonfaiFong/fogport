<?php
/**
 * 全局启动引导（每个 Worker 进程启动时按顺序执行一次）。
 */

return [
    support\bootstrap\Session::class,
    // 注册 Illuminate Database：查询构造器、Eloquent、DB Facade
    app\bootstrap\Database::class,
];