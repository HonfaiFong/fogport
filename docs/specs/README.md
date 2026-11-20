# specs 目录

按模块拆分的详细设计文档。本轮暂未拆分，PROJECT_SPEC.md 仍为完整总纲。

计划拆分结构：
- 00-foundation.md    基础设施、compose、PG/Redis/RustFS 封装、中间件与异常规范
- 01-identity.md      认证双 Token、真身/分身 CRUD、AuthorPresenter 白名单脱敏、Push Token 上报
- 02-safety.md        穿透马甲双向拉黑、敏感词初筛、举报权重与自动 Shadowban、多维封禁
- 03-can.md           情绪之海、罐头/评论、can_participants 单帖映射、Redis 捞罐算法、媒体直传
- 04-chat.md          匿名发起私聊、seq 增量同步、WS 票据与长连接、亮真身状态机、闪聊撮合
- 05-notification.md  站内通知、脱敏推送策略、APNs/FCM/华为OV荣适配器与 PushConsumer
- 06-admin.md         后台鉴权、Shadowban 人工复核队列、确认封号与内容恢复
