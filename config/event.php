<?php

/**
 * 模块间解耦事件监听注册（webman/event）。
 *
 * 规范要求（PROJECT_SPEC.md 3.2）：反向联动统一走进程内事件总线，
 * 例如 Safety 拉黑后触发 safety.user_blocked，由 Chat 监听并关闭会话，
 * 避免模块之间直接依赖。
 */
return [
    // 示例（待对应模块实现后启用）：
    // 'safety.user_blocked' => [
    //     [app\modules\chat\listener\CloseConversationOnBlock::class, 'handle'],
    // ],
];