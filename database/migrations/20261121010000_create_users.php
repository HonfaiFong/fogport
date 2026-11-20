<?php

use Phinx\Migration\AbstractMigration;

/**
 * Identity 模块：真身用户表。
 *
 * 设计要点（PROJECT_SPEC.md 第 5 节）：
 * - 主键 UUID（应用层生成 UUIDv7，不用数据库自增，防枚举泄漏）
 * - phone_hash 唯一，存储手机号哈希而非明文
 * - trust_score 默认 10，众包风控中高信任用户可升至 50
 * - status: 1 正常 / 2 禁言 / 3 封禁
 */
final class CreateUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users', [
            'id' => false,
            'primary_key' => 'id',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('phone_hash', 'string', ['limit' => 64, 'null' => false, 'comment' => '手机号哈希，不存明文'])
            ->addColumn('nickname', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('avatar_key', 'string', ['limit' => 255, 'null' => true, 'comment' => '对象存储 key'])
            ->addColumn('bio', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('gender', 'smallinteger', ['null' => true, 'comment' => '0未知/1男/2女'])
            ->addColumn('trust_score', 'integer', ['default' => 10, 'null' => false, 'comment' => '众包风控信任分'])
            ->addColumn('is_guardian', 'boolean', ['default' => false, 'null' => false, 'comment' => '罐头英雄'])
            ->addColumn('status', 'smallinteger', ['default' => 1, 'null' => false, 'comment' => '1正常/2禁言/3封禁'])
            ->addColumn('created_at', 'timestamp', ['timezone' => true, 'null' => false])
            ->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true])
            ->addIndex(['phone_hash'], ['unique' => true, 'name' => 'uk_users_phone_hash'])
            ->addIndex(['status'], ['name' => 'idx_users_status'])
            ->create();
    }
}