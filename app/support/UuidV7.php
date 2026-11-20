<?php

namespace app\support;

use Ramsey\Uuid\Uuid;

/**
 * UUIDv7 生成器（全站防枚举 ID，见 PROJECT_SPEC.md 第 1.2 节红线 7）。
 *
 * UUIDv7 按时间有序，索引友好，且不泄漏自增数量。
 */
final class UuidV7
{
    /**
     * 生成 UUIDv7 字符串。
     *
     * @return string 形如 019a1b2c-3d4e-7f80-9abc-def012345678
     */
    public static function generate(): string
    {
        return Uuid::uuid7()->toString();
    }
}