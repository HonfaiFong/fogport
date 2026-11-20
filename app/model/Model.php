<?php

namespace app\model;

use Illuminate\Database\Eloquent\Model as LaravelModel;

/**
 * 全站模型基类（Laravel 风格 Eloquent）。
 *
 * 统一约定（见 PROJECT_SPEC.md 第 5 节）：
 * - 主键 UUIDv7，非自增、非数字，需关闭 incrementing 并声明 string 类型
 * - 时间字段 TIMESTAMPTZ，用 Model::$timestamps 由 Eloquent 维护 created_at/updated_at
 * - 模块内模型放 app\model 下，跨模块只能通过 Facade 访问，禁止直接跨模块读写他人表
 */
abstract class Model extends LaravelModel
{
    // UUIDv7 主键：字符串类型且非自增
    protected $keyType = 'string';
    public $incrementing = false;

    // 时间字段由数据库 TIMESTAMPTZ 承载
    protected $dateFormat = 'Y-m-d H:i:sP';
}