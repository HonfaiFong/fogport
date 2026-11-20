<?php

namespace app\Modules\Admin\Controller;

use support\Request;
use support\Response;

/**
 * 后台管理控制器（占位）。
 *
 * 职责范围（见 PROJECT_SPEC.md 7.2 节 `/admin/*`）：
 * - 人工复核 Shadowban 队列
 * - 确认封号与解封
 * - 恢复误报内容
 *
 * 注意：后台需独立鉴权中间件，与面向 App 的 JWT 体系隔离，
 * 避免普通用户 Token 越权访问管理接口。
 */
class AdminController
{
    public function index(Request $request): Response
    {
        // TODO: 实现待复核列表（分页 + 举报权重排序）
        return json(['code' => 0, 'message' => 'ok', 'data' => new \stdClass()]);
    }
}