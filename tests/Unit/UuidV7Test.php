<?php

namespace tests\Unit;

use app\support\UuidV7;
use PHPUnit\Framework\TestCase;

/**
 * UUIDv7 生成器冒烟测试。
 *
 * 该测试的意义是让 `composer test` 从第一天就是绿的，
 * 同时锁死两条被业务依赖的性质：
 * 1. 版本位必须是 7（否则 PostgreSQL uuid 列虽能存，但失去时间有序性）
 * 2. 生成结果单调递增（UUIDv7 按时间有序，是索引友好的前提）
 */
class UuidV7Test extends TestCase
{
    public function testGenerateReturnsValidUuidV7Format(): void
    {
        $uuid = UuidV7::generate();

        // 标准 UUID 格式：8-4-4-4-12
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
            "生成的 UUID 不符合 v7 格式: {$uuid}"
        );
    }

    public function testGenerateProducesMonotonicValues(): void
    {
        $previous = UuidV7::generate();

        for ($i = 0; $i < 100; $i++) {
            $current = UuidV7::generate();

            // UUIDv7 前 48 位为毫秒时间戳，后续位随机。
            // 同一毫秒内生成的 UUID 仍应保证字典序递增（ramsey/uuid 实现了该保证）。
            $this->assertGreaterThan(0, strcmp($current, $previous), 'UUIDv7 未保持单调递增');
            $previous = $current;
        }
    }

    public function testGenerateProducesUniqueValues(): void
    {
        $uuids = [];
        for ($i = 0; $i < 500; $i++) {
            $uuids[] = UuidV7::generate();
        }

        $this->assertCount(500, array_unique($uuids), 'UUIDv7 出现了重复值');
    }
}