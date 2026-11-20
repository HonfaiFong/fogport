# fogport

树洞型匿名社交后端（类「一罐」App），Webman + PostgreSQL + Redis 模块化单体。

## 快速开始

```bash
# 1. 准备环境变量
cp .env.example .env      # 按需修改；.env 不提交

# 2. 启动（首次会自动构建 PHP 镜像，含 swoole 编译约 3-5 分钟）
docker compose up -d --build

# 3. 执行数据库迁移
docker exec fogport-app php webman migrate
```

访问 <http://127.0.0.1:8787/> 应返回 `404`（尚无业务路由，属正常）。

## 目录结构

```
app/
├── Modules/          # 业务模块：Identity / Safety / Can / Chat / Notification
│                     # 模块间只通过 Facade 通信，禁止跨模块读写他人表
├── Shared/           # 纯技术基础设施：Exception / Middleware / Support / Storage / Realtime
├── bootstrap/        # 进程启动引导（Database 注册查询构造器与 Eloquent）
├── command/          # webman 命令行命令
├── model/            # Eloquent 模型基类
├── process/          # 自定义进程：Websocket / Crontab
├── queue/redis/      # 异步队列消费者（慢 I/O）
└── support/          # 通用工具（UuidV7 等）

database/
├── migrations/       # Phinx 迁移文件
└── seeds/            # 种子数据

config/               # webman 配置（database / redis / storage / process / event 等）
docker/php/           # PHP 镜像定义
phinx.php             # Phinx 迁移配置（连接参数复用 .env）
```

## 数据库迁移

使用 Phinx（[webman 官方推荐方案](https://webman.workerman.net/doc/zh-cn/db/migration.html)），已封装为 webman 命令：

```bash
docker exec fogport-app php webman migrate                      # 执行全部未执行迁移
docker exec fogport-app php webman migrate -t 20261121010000    # 迁移到指定版本
docker exec fogport-app php webman migrate:rollback             # 回滚最近一批
docker exec fogport-app php webman migrate:rollback -t 0        # 回滚全部
docker exec fogport-app php webman migrate:status               # 查看状态
```

**注意**：迁移文件一经合并不得修改，变更需新建迁移文件。

命名规范：

| 操作 | 命名 |
| --- | --- |
| 建表 | `{time}_create_{表名}` |
| 改表 | `{time}_modify_{表名}_{项}` |
| 删表 | `{time}_delete_{表名}_{项}` |
| 填充 | `{time}_fill_{表名}_{项}` |

类名需与文件名的驼峰形式一致（如 `20261121010000_create_users.php` → `class CreateUsers`），否则 Phinx 找不到类。

## 进程模型

`php start.php start` 拉起 5 类进程，事件循环按进程分派：

| 进程 | 事件循环 | 职责 |
| --- | --- | --- |
| `webman` | event (libevent) | 主 HTTP 服务，高频 CRUD |
| `websocket` | event | WS 长连接（8788） |
| `task` | **swoole** | 慢 I/O 队列消费（AI 接口、推送、媒体清理） |
| `crontab` | event | 定时修剪捞罐池、清理闪聊队列 |
| `monitor` | event | 开发期文件热重载 |

## 技术要点

- **ORM**：`illuminate/database`（Laravel 风格），查询构造器 `DB::table()`、Eloquent、`DB::transaction()`，在 `app/bootstrap/Database.php` 启动
- **主键**：UUIDv7，应用层生成（`app/support/UuidV7.php`），防枚举泄漏
- **对象存储**：RustFS（S3 兼容），由独立设备提供，连接参数见 `.env` 的 `RUSTFS_*`
- **迁移**：Phinx；**ORM**：illuminate —— 两者独立，互不干扰