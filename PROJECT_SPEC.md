# 项目开发总纲：树洞型匿名社交后端（类“一罐” App）

## 1. 项目定位与 AI 开发铁律（Guardrails）

### 1.1 产品核心定位

一款主打“情绪树洞 + 低压力破冰”的匿名社交 App，核心围绕“真身 / 分身双轨身份”运转：用户可以用零负担的分身在“情绪之海”倾诉、评论、随机捞罐头与闪聊；当与陌生人产生共鸣并建立信任后，可在私聊中选择单向或双向“亮出真身”，沉淀为稳定社交关系。

### 1.2 绝对禁止踩踏的架构红线（AI 写代码前必须遵守）

1. **严格采用模块化单体（Modular Monolith）**：严禁拆分为微服务，严禁引入 gRPC、Protobuf、注册中心、独立 MQ（Kafka/RabbitMQ）或 Outbox 补偿表。所有业务运行在同一套 Webman 代码库中。
2. **Webman 常驻内存防串号红线**：严禁在静态属性（`static`）、全局变量或单例 Service 中缓存当前请求的用户态（`userId`、身份信息等）。请求上下文一律挂载于 `$request` 或作为方法参数显式传递。
3. **隐私脱敏零泄露红线**：数据库永远记录底层真实 `user_id`，但任何返回给客户端的接口，在 `PERSONA`（分身）模式下**绝对禁止输出真实 `user_id`、真实头像/昵称、或可跨帖关联的 `persona_id**`。所有作者信息必须经由 `Identity\Presenter\AuthorPresenter` 统一白名单渲染。
4. **单库 ACID 事务优先**：涉及跨表强一致性的动作（如发消息+递增会话 `seq`+更新预览、举报达阈值+隐藏内容）必须在单个 PostgreSQL 事务中完成。
5. **跨模块边界隔离**：各业务模块仅允许通过对方暴露的 `Facade`（Service 接口）通信，严禁模块 A 直接读写模块 B 的数据库表；反向联动统一走进程内事件总线（`webman/event`）。
6. **禁止阻塞 Worker**：HTTP 与 WebSocket 进程严禁执行耗时外部 I/O（如调 RustFS 删图、调三方内容审核 API、发 APNs/FCM/厂商推送），必须投递至 `webman/redis-queue` 异步消费。
7. **全站防枚举 ID**：所有业务主键统一在应用层生成 **UUIDv7**（有序、无自增泄漏），前端入参严禁要求客户端直接传递匿名对象的真实 `user_id`。

---

## 2. 技术栈与运行进程模型

### 2.1 技术栈清单

| 层级 | 选型 | 说明 |
| --- | --- | --- |
| **运行环境与框架** | PHP 8.3+ / Webman 2.x（Workerman） | 常驻内存高性能框架，统一管理 HTTP / WS / 队列 / 定时任务 |
| **主数据库** | PostgreSQL 16+（`pdo_pgsql` + `illuminate/database`） | 善用 `UUID`、`JSONB`、部分索引与 `UPDATE ... RETURNING` |
| **数据库迁移** | Phinx（`robmorgan/phinx`） | webman 官方推荐方案；单库单基线迁移，拒绝多库多引擎。类名须与文件名驼峰形式一致 |
| **缓存 / 实时 / 队列** | Redis 7（`ext-redis` + `webman/redis-queue`） | 承担捞罐池 ZSET、去重 Set、闪聊队列、WS 票据、异步队列与跨进程广播 |
| **对象存储** | RustFS（兼容 S3 协议） | 由**独立设备**提供，不在本地 compose 中启动；通过 `async-aws/s3`（或 `aws/aws-sdk-php`）对接，配置 `path_style_endpoint = true`；客户端走 Presigned URL 直传。SDK 待选型安装 |
| **实时长连接** | Workerman WebSocket 进程 + Redis Pub/Sub（或 GatewayWorker） | 与 HTTP 同仓库启动，通过 Redis 跨进程路由推送到对应连接 |
| **消息推送** | 自建统一推送抽象层（`PushChannelInterface`） | 适配 APNs + FCM + 华/米/OV/荣厂商通道，支持分阶段接入 |
| **鉴权与 ID** | `firebase/php-jwt` + `ramsey/uuid` (UUIDv7) | AccessToken（短效）+ RefreshToken（可吊销）；WS 走一次性 Ticket |

### 2.2 Webman 多进程拓扑（`config/process.php`）

同一仓库执行 `php start.php start` 拉起 5 类进程：

1. **HTTP Worker**：处理全部 REST API、本地 PG 事务、签发 RustFS 预签名链接与 WS 票据。
2. **WebSocket Worker**：校验一次性 WS Ticket（`GETDEL` 阅后即焚），维护 `user_id -> connections` 映射，订阅 Redis Pub/Sub 通道将实时帧推给在线客户端。
3. **队列消费进程池**（双池隔离，详见 2.4）：`consumer_fast` 消费 `push-high` 做离线 Push；`consumer_slow` 消费 `moderation-normal` / `cleanup-low` 做三方内容机审与废弃媒体清理。
4. **Crontab 定时进程**：每小时修剪 Redis 捞罐池长度（每海仅保留最新 5000 条）、清理超时闪聊等待节点。
5. **Monitor 文件监控进程**：开发期检测代码变更并热重载，生产环境不启用文件监控。

### 2.3 事件循环分派策略（Event / Swoole 双驱动）

同时安装 `ext-event` 与 `ext-swoole`，在 `config/process.php` 中**按进程粒度**指定 `eventLoop`，而非全局统一。

| 进程 | 事件循环 | 职责与理由 |
| --- | --- | --- |
| `webman`（HTTP CRUD） | `Workerman\Events\Event` | libevent（C 扩展），无协程调度开销，日常高频 CRUD 最稳健 |
| `websocket`（WS 长连接） | `Workerman\Events\Event` | libevent 支持大 fd 集，适合海量并发连接 |
| `consumer_fast`（4 进程） | `Workerman\Events\Event` | 消费 `push-high`，离线推送要求秒级送达；推送是无阻塞 HTTP 调用，Event 更轻 |
| `consumer_slow`（2 进程） | `Workerman\Events\Swoole` | 消费 `moderation-normal` / `cleanup-low`，有外部慢 I/O，需 `SWOOLE_HOOK_ALL` 协程化 |
| `crontab` / `monitor` | `Workerman\Events\Event` | 轻量定时 / 文件扫描，无需协程 |

**实现依据**：`Workerman\Worker::$eventLoop` 是**进程级**属性，webman 的 `worker_start()` 会把 `config/process.php` 中的 `eventLoop` 字段透传给对应 Worker 实例，因此同一代码库内可让不同进程混用两种事件循环。

**配套约束（踩坑记录，务必遵守）**：

1. `event.so` 依赖 `sockets` 扩展导出的 `socket_ce` 符号，其 ini 必须排在 `sockets` 之后加载（Dockerfile 中用 `--ini-name zz-event.ini` 保证），否则启动报 `undefined symbol: socket_ce`。`conf.d` 按文件名排序加载，默认 `docker-php-ext-event.ini` 会排在 `docker-php-ext-sockets.ini` 之前，因此必须显式改名。
2. 不得 purge `libevent-dev`：`event.so` 运行时动态链接 `libevent_openssl` / `libevent_extra`，删除开发包会连带删除运行时库，导致加载失败。
3. `swoole` 的 `swoole-curl` 组件在编译期需要 `libcurl4-openssl-dev` 与 `libssl-dev`。
4. `webman/redis-queue` 插件自带的 `consumer` 进程必须设为 `enable => false`（见 `config/plugin/webman/redis-queue/process.php`），队列消费统一由 `consumer_fast` / `consumer_slow` 双池承担，避免两批消费者争抢同一队列且事件循环驱动不一致。
5. **本项目禁用 Session**：`config/bootstrap.php` 刻意不注册 `support\bootstrap\Session`，`config/session.php` 已删除。登录态一律由 JWT（AccessToken + RefreshToken）承载，**严禁使用** `$request->session()` 存储验证码或登录态。

### 2.4 队列双层隔离策略（防队头阻塞）

慢 I/O 与高时效性任务必须做**两层隔离**，只拆队列名是不够的：

1. **队列名拆分**：`push-high`（离线推送）/ `moderation-normal`（内容机审）/ `cleanup-low`（媒体清理）。
2. **进程池拆分**：通过 `webman/redis-queue` 的 `consumer_dir` 构造参数，让两组消费进程**各扫各自目录**，实现物理隔离。

若仅拆队列名而共用同一进程池，三方审核接口超时仍会占满 Worker，直接堵死推送。

**实现要点**（已实测验证）：`Webman\RedisQueue\Process\Consumer` 用 `RecursiveDirectoryIterator` 扫描 `consumer_dir`，只加载该目录下的消费者类，因此 `app/queue/redis/fast` 与 `app/queue/redis/slow` 相互不可见，是真正的进程级隔离，而非逻辑约定。

| 进程池 | consumer_dir | 事件循环 | 队列 | 设计意图 |
| --- | --- | --- | --- | --- |
| `consumer_fast` | `app/queue/redis/fast` | Event | `push-high` | 独立进程保障推送秒级送达 |
| `consumer_slow` | `app/queue/redis/slow` | Swoole | `moderation-normal`、`cleanup-low` | 外部慢 I/O 协程化，超时不影响推送 |

**消费者类约定**：保持极薄，仅做反序列化并委托给对应 Module 的 Service，禁止在消费者内写业务逻辑；投递端 `Client::send('push-high', ...)` 的队列名必须与 `$queue` 属性严格一致。

---

## 3. 项目目录结构与模块依赖规范

### 3.1 推荐目录结构

```text
fogport/
├── config/
│   ├── database.php             # PostgreSQL 连接配置
│   ├── redis.php                # 业务 Redis 连接（default 加 fogport: 前缀；pubsub 不加前缀）
│   ├── storage.php              # RustFS (S3) Endpoint / Bucket / CDN 域名配置
│   ├── push.php                 # APNs / FCM / 各安卓厂商通道配置（待建）
│   ├── process.php              # 进程声明与事件循环分派（含队列双进程池）
│   ├── event.php                # 模块间解耦事件监听注册
│   ├── bootstrap.php            # 启动引导注册（含 Database 引导，**不注册 Session**）
│   ├── exception.php            # 全局异常处理器注册
│   ├── static.php               # 静态文件服务配置（本项目 enable => false）
│   ├── route.php                # 聚合加载各模块路由
│   └── plugin/webman/
│       └── redis-queue/redis.php # 队列插件专用连接（fogport: 前缀）
├── app/
│   ├── bootstrap/
│   │   └── Database.php         # 注册 Capsule：查询构造器 / Eloquent / DB Facade
│   ├── command/                 # CLI：migrate / migrate:rollback / migrate:status
│   ├── exception/               # BusinessException / ErrorCode / 全局 Handler（统一 7.1 响应格式）
│   ├── middleware/              # TraceId / TrustedProxyIp / Auth / RateLimit（禁写业务逻辑）
│   ├── model/
│   │   └── Model.php            # 唯一 Eloquent 基类（UUIDv7 主键约定），禁止放具体业务表模型
│   ├── process/                 # 自定义进程：Http / Websocket / Crontab / Monitor
│   ├── queue/redis/             # 异步队列消费入口（薄壳，仅反序列化并委托 Module Service）
│   │   ├── fast/
│   │   │   └── PushConsumer.php        # 队列 push-high（离线推送，秒级送达）
│   │   └── slow/
│   │       ├── ModerationConsumer.php  # 队列 moderation-normal（三方内容机审）
│   │       └── CleanupConsumer.php     # 队列 cleanup-low（RustFS 媒体清理）
│   ├── support/                 # 全局纯技术基础设施（禁止写业务逻辑）
│   │   ├── UuidV7.php           # UUIDv7 生成
│   │   ├── ApiResponse.php      # 统一响应封装（待建）
│   │   ├── HmacSigner.php       # 签名工具（待建）
│   │   ├── Storage/             # RustFsSigner：生成直传 Presigned URL 与访问 URL（待建）
│   │   └── Realtime/            # RealtimePusher：向 Redis Pub/Sub 发布推送指令（待建）
│   └── Modules/                 # 业务域（跨模块只能经 Facade + webman/event）
│       ├── Identity/            # [模块1] 登录认证、真身资料、分身卡、WS票据、出口脱敏 Presenter
│       ├── Safety/              # [模块2] 双向拉黑、敏感词初筛、举报与众包 Shadowban、多维封禁
│       ├── Can/                 # [模块3] 情绪之海、罐头/评论 CRUD、单帖匿名映射、捞罐算法、媒体直传
│       ├── Chat/                # [模块4] 私聊会话、seq 增量同步、亮真身状态机、闪聊撮合、WS 处理器
│       ├── Notification/        # [模块5] 站内通知、PushChannelInterface 及各厂商推送适配器
│       └── Admin/               # [模块6] 人工复核 Shadowban 队列、确认封号与解封、内容恢复
├── database/
│   ├── migrations/              # Phinx 迁移文件（PHP，非 SQL）
│   └── seeds/                   # 种子数据
├── docker/php/                  # PHP 镜像定义（Dockerfile / php.ini）
├── docs/specs/                  # 按模块拆分的详细设计文档（后续填充）
├── tests/
│   ├── bootstrap.php            # PHPUnit 引导（仅 autoload + .env，不启动内核）
│   ├── Unit/                    # 重点覆盖：AuthorPresenter 防掉马断言、捞罐算分、敏感词
│   └── Feature/                 # 重点覆盖：端到端核心业务闭环测试
├── compose.yaml                 # 本地开发环境：app + postgres:16 + redis:7（RustFS 由独立设备提供）
├── phinx.php                    # Phinx 迁移配置（连接参数复用 .env）
├── phpunit.xml                  # PHPUnit 配置（Unit / Feature 两个测试套件）
├── webman                       # webman 命令行入口
├── PROJECT_SPEC.md              # 本文档
└── composer.json

```

### 3.1.1 目录铁律（AI 与人工都必须遵守）

1. **禁止新建 `app/Shared/`**：该目录已废弃并删除。全局纯技术基础设施统一放 webman 默认目录 —— `app/support`（工具类）、`app/middleware`（中间件）、`app/exception`（异常与错误码）、`app/model`（唯一 Eloquent 基类）。
2. **业务代码只能放 `app/Modules/{Module}/`**：按模块划分 Controller / Service / Model / Presenter / Facade / DTO。
3. **两个 Redis 配置缺一不可**：`config/redis.php` 供业务代码使用（ZSET 捞罐池、去重 Set、WS 票据、限流、Pub/Sub）；`config/plugin/webman/redis-queue/redis.php` 仅供队列插件使用。二者不可互相替代或合并。
4. **`app/model/` 下只允许存在 `Model.php`**，具体业务表模型一律放各自模块的 `Model/` 子目录。

### 3.2 模块单向依赖矩阵（严禁循环依赖）

* **`Identity`**：最底层，不依赖其他业务模块。
* **`Safety`**：仅可依赖 `Identity`（读取用户 `trust_score`、执行账号封禁状态更新）。
* **`Can`**：可依赖 `Identity`（校验分身归属、调用脱敏渲染）、`Safety`（内容初筛、过滤双向拉黑）。
* **`Chat`**：可依赖 `Identity`、`Safety`、`Can`（通过罐头/评论 ID 反查底层目标用户与匿名快照）。
* **`Notification`**：仅监听 `webman/event` 事件，只读 `Identity` 的设备推送 Token。
* **反向联动机制**：当 `Safety` 执行“拉黑”时，触发 `safety.user_blocked` 事件，由 `Chat` 监听并自动关闭双方活跃私聊会话，彻底解耦。

### 3.3 数据访问层规范（Laravel 风格）

业务逻辑统一采用 Laravel 风格写法，基于 `illuminate/database`。注意这是**组件库而非完整 Laravel 框架**，没有 `artisan`、没有 ServiceProvider 体系。

**启动接入**：`app/bootstrap/Database.php` 在 `config/bootstrap.php` 中注册，每个 Worker 进程启动时执行一次，完成四件事：

1. 按 `config/database.php` 的 `connections` 注册 Capsule 多连接，并设置默认连接
2. 挂载 `illuminate/events` 分发器（Eloquent 模型事件、观察者依赖它）
3. `bootEloquent()` 启动 ORM
4. 注册容器 `db` / `db.schema` 单例绑定，并设置 Facade 根容器

> ⚠️ Capsule 的 `setAsGlobal()` **只写入它自己的静态属性，不会注册到容器**。因此必须手动 `instance('db', ...)` 与 `singleton('db.schema', ...)`，否则 Facade 会报 `Target class [db] does not exist`；不调用 `setFacadeApplication()` 则报 `A facade root has not been set`。`db.schema` 必须是 `singleton` 而非 `bind`，以保证同一进程内 Schema 操作复用连接。

**可直接使用**：Eloquent 模型、`DB::table()` 查询构造器、`DB::transaction()` 事务、`DB::selectOne()` 原生查询、Facade 门面。

**不可直接使用**（webman 非 Laravel 容器）：

| Laravel 能力 | 本项目替代方案 |
| --- | --- |
| `php artisan migrate` 及全部 artisan 命令 | 用封装命令 `php webman migrate` / `migrate:rollback` / `migrate:status` |
| Laravel ServiceProvider | webman 的 `config/bootstrap.php` + `Webman\Bootstrap` 接口 |
| `Route::middleware('auth')` 路由中间件语法 | webman 中间件 + `config/middleware.php` |
| Laravel Validator / FormRequest | 入口统一校验，用 webman 验证器组件 |

**模型基类约定**（`app/model/Model.php`）：

- 主键 UUIDv7：`$keyType = 'string'`、`$incrementing = false`
- 时间字段 `$dateFormat = 'Y-m-d H:i:sP'`，对应 PG 的 `TIMESTAMPTZ`
- 跨模块只能通过 Facade 访问对方数据，禁止直接用模型读写其他模块的表（呼应 3.2）

**常驻内存红线**：模型与查询构造器实例在进程内复用，**严禁**在模型静态属性、全局变量或单例 Service 中缓存当前请求的用户态（呼应 1.2 红线 2）。

**ORM 与迁移的职责边界**：ORM 用 `illuminate/database` 负责运行时读写，迁移用 Phinx 只负责表结构变更，两者独立、互不干扰。不要试图用 `illuminate` 的 Migrator 替代 Phinx。

---

## 4. 核心业务规则与领域不变量（Domain Invariants）

AI 在实现任何功能时，必须严格保证以下 6 条核心业务规则成立：

### 规则 1：动作级真身/分身双轨与单帖身份锁定

* 用户有且仅有 1 个**真身（User）**，可创建多个常驻或一次性**分身（Persona）**。
* 发罐头、发评论、发起私聊、进入闪聊时，每次动作均可独立选择 `REAL` 或 `PERSONA`。
* **单帖身份隔离与锁定**：用户在同一罐头下首次发言/评论时，写入 `can_participants` 表，生成单帖唯一标识 `scoped_token = substr(hmac_sha256(secret, can_id . ':' . user_id), 0, 16)`。
* 同一用户在**同一个罐头**下多次回复，拥有相同的 `scoped_token`，前端可稳定识别“楼主”或“同一个匿名层主”。
* 同一用户在**不同罐头**下，`scoped_token` 完全不同，彻底切断跨帖追踪。
* 发布或评论时的名称与图标写入 `author_snapshot (JSONB)`，用户日后修改/删除分身卡，不影响历史内容展示。



### 规则 2：反马太效应的“随机捞罐头”机制

* 树洞社区的核心目标是让**普通人的 0 回复新罐头优先被看见**。
* 每个“情绪之海”在 Redis 中维护候选池 `sea:pool:{sea_id}`（ZSET）：
* 新罐头入池分值：`score = unix_timestamp + 14400`（0 回复享有 4 小时加权红利）。
* 一旦收到回复：移除 4 小时红利，且每增加 1 条回复分值衰减 `1800` 秒（最多衰减 6 小时），主动把曝光让给其他 0 回复罐头。


* 捞罐头逻辑：取 ZSET 头部前 200 条 -> 内存随机打乱 -> 依次过滤“用户已捞过（Redis Set）”、“自己发的”、“双向黑名单作者” -> 命中首条返回并记入已捞集合（TTL 7 天）。

### 规则 3：隐私保护的“从匿名内容发起私聊”

* 客户端发起私聊时，**绝对不传对方 `user_id**`，只传 `{source_type: 'CAN'|'COMMENT', source_id, my_identity_mode, my_persona_id}`。
* 后端通过 `CanFacade::resolveAnonymousTarget()` 反查底层 `target_user_id` 与对方在该内容下的匿名快照；再校验 `SafetyFacade::isMutuallyBlocked()`；最后以确定性 `pair_key` 幂等创建或复用会话。

### 规则 4：基于 `seq` 的可靠 IM 与“分身转真身”状态机

* 每条会话维护单调递增的 `last_seq`。发消息时在一个 PG 事务内执行：
1. `UPDATE conversations SET last_seq = last_seq + 1, ... WHERE id = ? RETURNING last_seq`（利用行锁防并发序号冲突）。
2. 结合 `client_msg_id` 幂等写入 `messages` 表。
3. 事务提交后通过 `RealtimePusher` 推送在线 WS；若对方不在线则投递离线 Push 队列。
4. 客户端重连或弱网丢帧时，统一调 `GET /v1/conversations/{id}/messages?after_seq=N` 补齐。


* **亮真身状态机**：`conversation_members` 为双方各自记录 `identity_revealed (bool)`。支持**单向先亮真身**：A 点击亮真身后，单事务更新 `identity_revealed = true` 并写入一条 `SYSTEM_REVEAL` 系统消息，实时推送 `PEER_REVEALED` 帧给 B。聊天界面头像/昵称绑定到会话成员对象，一旦亮真身，历史消息视图立刻切换为真身并解锁主页入口。

### 规则 5：闪聊（即时匹配）

* 用户携带分身卡 + 情绪标签进入 `flash:queue:{tag}` 队列。
* 通过 Redis Lua 脚本原子弹出最早等待的候选人（剔除超过 30 秒的过期节点），后端校验非本人、非双向拉黑、对方 WS 在线后，直接创建 `source_type = 'FLASH_MATCH'` 的会话，通过 WS 同时通知双方进入聊天。

### 规则 6：穿透马甲拉黑与众包自动 Shadowban

* **穿透拉黑**：客户端只传 `{target_type: 'CAN'|'COMMENT'|'CONVERSATION', target_id}`，后端反查真实 `blocked_user_id` 写入 `user_blocks`。一旦拉黑，无论对方换多少个分身，双方在海列表、捞罐头、评论区、闪聊、私聊中均彻底隔离。
* **众包风控（罐头英雄机制）**：普通用户 `trust_score = 10`，有效举报率高的老用户可升至 `50`。同一内容累计举报权重 `>= 100` 时，自动触发 **Shadowban**：立刻从 Redis 捞罐池移除，数据库状态置为 `SHADOW_HIDDEN`（作者自己仍可见，但对其他所有人不可见），并进入人工复核列表。
* **锁屏脱敏推送**：凡涉及分身罐头回复或匿名私聊的离线 Push，严禁透出真实昵称或敏感原文，统一发送中性文案（如 `"你的树洞收到一条新回音"`）。

---

## 5. 核心数据库 Schema 清单（PostgreSQL）

全部主键使用 `UUID`（UUIDv7），时间字段统一 `TIMESTAMPTZ`。

| 所属模块 | 表名 | 核心字段与关键约束 |
| --- | --- | --- |
| **Identity** | `users` | `id`, `phone_hash (UNIQUE)`, `nickname`, `avatar_key`, `bio`, `gender`, `trust_score (default 10)`, `is_guardian (bool)`, `status (1正常/2禁言/3封禁)`, `created_at` |
| **Identity** | `personas` | `id`, `user_id`, `alias_name`, `avatar_code`, `mood_tag`, `is_ephemeral`, `is_deleted`, `created_at` |
| **Identity** | `user_refresh_tokens` | `id`, `user_id`, `token_hash (UNIQUE)`, `device_id`, `expires_at`, `revoked_at` |
| **Identity** | `user_push_devices` | `id`, `user_id`, `platform (ios/android)`, `vendor (apns/fcm/huawei/xiaomi/oppo/vivo/honor)`, `push_token`, `device_id`, `updated_at`；`UNIQUE(device_id, vendor)` |
| **Can** | `seas` | `id (SMALLINT)`, `slug (UNIQUE)`, `name`, `warning_banner`, `allow_real_only`, `sort_order` |
| **Can** | `cans` | `id`, `sea_id`, `author_user_id`, `identity_mode`, `persona_id`, `author_snapshot (JSONB)`, `content`, `media_items (JSONB)`, `visibility (PUBLIC/PRIVATE/SHADOW_HIDDEN/DELETED)`, `allow_comment`, `show_guardian`, `like_count`, `reply_count`, `created_at` |
| **Can** | `can_participants` | `PK(can_id, user_id)`, `scoped_token (CHAR 16)`, `identity_mode`, `display_name`, `display_avatar`, `is_op` |
| **Can** | `comments` | `id`, `can_id`, `author_user_id`, `reply_to_id`, `identity_mode`, `scoped_token`, `author_snapshot (JSONB)`, `content`, `status (1正常/2折叠/3删除)`, `created_at` |
| **Can** | `can_likes` | `PK(can_id, user_id)`, `created_at` |
| **Chat** | `conversations` | `id`, `source_type (CAN/COMMENT/FLASH_MATCH/PROFILE)`, `source_id`, `pair_key (UNIQUE)`, `last_seq (BIGINT)`, `last_msg_preview`, `last_msg_at`, `status (ACTIVE/CLOSED_BY_BLOCK)` |
| **Chat** | `conversation_members` | `PK(conversation_id, user_id)`, `initial_mode`, `persona_snapshot (JSONB)`, `identity_revealed (bool)`, `revealed_at`, `last_read_seq (BIGINT)` |
| **Chat** | `messages` | `id`, `conversation_id`, `seq (BIGINT)`, `sender_user_id`, `msg_type (TEXT/IMAGE/VOICE/SYSTEM_REVEAL)`, `body (JSONB)`, `client_msg_id`, `created_at`；`UNIQUE(conversation_id, seq)`，`UNIQUE(conversation_id, sender_user_id, client_msg_id)` |
| **Safety** | `user_blocks` | `PK(blocker_user_id, blocked_user_id)`, `source_context`, `created_at` + 反向联合索引 |
| **Safety** | `reports` | `id`, `reporter_id`, `target_type`, `target_id`, `target_user_id`, `reason (YP/HARASSMENT/AD/PORN)`, `weight`, `status`, `created_at`；`UNIQUE(reporter_id, target_type, target_id)` |
| **Safety** | `banned_fingerprints` | `id`, `type (PHONE_HASH/DEVICE_ID)`, `value_hash (UNIQUE)`, `reason`, `expires_at` |
| **Notification** | `notifications` | `id`, `user_id`, `type`, `payload (JSONB 脱敏后)`, `is_read`, `created_at` |

---

## 6. Redis 7 键空间设计规范

所有 Redis Key 必须带统一前缀 `fogport:`，严禁随意命名：

| Key 格式 | 数据结构 | TTL | 用途说明 |
| --- | --- | --- | --- |
| `fogport:sms:code:{phone_hash}` | String | 5 min | 登录短信验证码 |
| `fogport:rl:{scene}:{key}` | String / ZSET | 按窗口 | 短信、发帖、举报、闪聊分级限流 |
| `fogport:ws:ticket:{nonce}` | String (JSON) | 30 sec | WS 一次性建连票据（校验时必须用 `GETDEL` 原子消费防重放） |
| `fogport:ws:online:{user_id}` | Set | 动态续期 | 记录用户在线状态/所在节点标识，用于闪聊在线校验与离线 Push 判定 |
| `fogport:ws:bus` | Pub/Sub Channel | 持久 | HTTP 进程向 WS 进程推送实时帧的广播通道 |
| `fogport:sea:pool:{sea_id}` | ZSET | 定时修剪 | 情绪之海捞罐头权重池（Member=`can_id`，Score=动态权重） |
| `fogport:user:seen:{user_id}` | Set | 7 days | 记录用户最近已捞过的 `can_id`，用于去重 |
| `fogport:flash:queue:{tag}` | ZSET | 实时清理 | 闪聊即时匹配等待池（Member=`user_id:persona_id`，Score=入队时间戳） |
| `fogport:block:rel:{user_id}` | Set | 1 hour | 双向关联黑名单缓存（拉黑/取消拉黑时主动失效） |

---

## 7. API 与实时协议总览

### 7.1 统一响应格式

```json
{
  "code": 0,
  "message": "ok",
  "trace_id": "019a...",
  "data": {}
}
```

### 7.2 REST API 清单（前缀 `/v1`）

| 模块 | Method & Path | 核心职责 |
| --- | --- | --- |
| **Auth** | `POST /auth/sms/send` | 发送短信验证码（IP + 手机号限流） |
| **Auth** | `POST /auth/login` | 手机号验证码登录/注册，校验封禁指纹，签发双 Token |
| **Auth** | `POST /auth/refresh` / `POST /auth/logout` | 刷新 Token / 吊销当前设备 RefreshToken |
| **Identity** | `GET/PATCH /me` | 获取/更新真身主页资料 |
| **Identity** | `GET/POST/PATCH/DELETE /personas` | 分身卡列表与管理 |
| **Identity** | `PUT /devices/push-token` | 上报客户端平台、厂商（APNs/FCM/华米OV荣）与 Push Token |
| **Identity** | `GET /users/{userId}` | 查看指定用户的真身公开主页（校验双向拉黑） |
| **Can** | `GET /seas` | 获取情绪之海列表及各板块顶部雷区预警 |
| **Can** | `GET/POST /cans` | 按情绪海分页游标浏览罐头 / 发布新罐头（支持真身或分身） |
| **Can** | `POST /cans/pick` | **随机捞罐头**（消费 Redis 权重池 + 去重 + 过滤黑名单） |
| **Can** | `GET/DELETE /cans/{canId}` | 罐头详情 / 作者删除 |
| **Can** | `GET/POST /cans/{canId}/comments` | 评论列表 / 发表评论（自动处理单帖 `can_participants` 绑定） |
| **Can** | `POST/DELETE /cans/{canId}/like` | 共鸣点赞与取消 |
| **Media** | `POST /media/presign` | 校验文件类型与大小限制，签发 RustFS 直传 Presigned URL |
| **Chat** | `POST /ws/ticket` | 签发 30 秒一次性 WS 建连票据 |
| **Chat** | `POST /conversations/from-content` | 从匿名罐头或评论发起私聊（不传目标用户 ID） |
| **Chat** | `GET /conversations` | 我的私聊会话列表（附带未读数与按 `revealed` 脱敏后的对方视图） |
| **Chat** | `GET/POST /conversations/{id}/messages` | 按 `after_seq` 增量拉取消息 / 发送消息（幂等 `client_msg_id`） |
| **Chat** | `POST /conversations/{id}/read` | 上报已读水位线 `last_read_seq` |
| **Chat** | `POST /conversations/{id}/reveal` | **单向亮出真身** |
| **Chat** | `POST/DELETE /flash-match` | 进入 / 取消闪聊匹配队列 |
| **Safety** | `POST /blocks/by-target` | 穿透马甲拉黑（仅传载体类型与 ID） |
| **Safety** | `GET/DELETE /blocks` | 拉黑列表（仅展示脱敏标签与时间，防反推）与解除拉黑 |
| **Safety** | `POST /reports` | 提交举报（累加权重，达阈值自动触发 Shadowban） |
| **Notification** | `GET /notifications` / `POST /notifications/read` | 站内通知列表与标为已读 |
| **Admin** | `/admin/*`（独立鉴权中间件） | 人工审核 Shadowban 队列、确认封号与解封、恢复误报内容 |

### 7.3 WebSocket 协议（`/ws?ticket={nonce}`）

所有上下行帧均采用 JSON 信封：

```json
{
  "type": "PING | PONG | MSG_NEW | PEER_REVEALED | FLASH_MATCHED | CONV_CLOSED | NOTIFY_NEW",
  "ts": 1795100000,
  "data": {}
}
```

* 建连 10 秒内无心跳或票据无效直接断开；心跳间隔建议 25 秒。