<?php

namespace app\bootstrap;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * 启动 Illuminate Database（Capsule），使 Eloquent 与查询构造器在 webman 中可用。
 *
 * 这是「Laravel 风格」的接入点，一次性注册以下能力：
 * - Capsule 连接池（按 config/database.php 的 connections 定义）
 * - 全局 Facade 根容器，使 DB::table(...)、DB::transaction(...) 等静态门面可用
 * - Eloquent ORM 与事件分发器（模型事件、观察者依赖它）
 *
 * 常驻内存注意：本方法在每个 Worker 进程中执行一次，绝不可在此缓存请求用户态。
 */
class Database implements Bootstrap
{
    /**
     * @param Worker|null $worker
     * @return void
     */
    public static function start(?Worker $worker): void
    {
        $capsule = new Capsule(Container::getInstance());

        $connections = config('database.connections', []);
        $default = config('database.default', 'pgsql');
        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);
        }
        $capsule->getDatabaseManager()->setDefaultConnection($default);

        // 模型事件、观察者需要事件分发器
        $capsule->setEventDispatcher(new Dispatcher(Container::getInstance()));

        // 注册为全局可访问（Eloquent 静态调用依赖）
        $capsule->setAsGlobal();

        // 启动 Eloquent
        $capsule->bootEloquent();

        // Capsule 的 setAsGlobal() 只写入它自己的静态属性，不会注册到容器，
        // 因此需手动绑定 db / db.schema，否则 Facade 报 "Target class [db] does not exist"。
        $container = Container::getInstance();
        $container->instance('db', $capsule->getDatabaseManager());

        // Schema Facade 通过容器解析 db.schema，须注册为单例工厂。
        // 单例保证同一进程内 Schema 操作复用同一连接，避免重复建连。
        $container->singleton('db.schema', static fn ($c) => $c['db']->connection()->getSchemaBuilder());

        // 让 Facade（DB / Schema 等）拿到容器实例，否则报 "A facade root has not been set"
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
    }
}