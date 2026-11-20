<?php

use Phinx\Migration\AbstractMigration;

/**
 * Identity 模块：RefreshToken 表（AccessToken 短效不入库）。
 *
 * 设计要点：
 * - 只存 token_hash，不存明文，防数据库泄漏后被直接使用
 * - revoked_at 支持吊销；expires_at 支持过期清理
 */
final class CreateUserRefreshTokens extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_refresh_tokens', [
            'id' => false,
            'primary_key' => 'id',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('device_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('expires_at', 'timestamp', ['timezone' => true, 'null' => false])
            ->addColumn('revoked_at', 'timestamp', ['timezone' => true, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['timezone' => true, 'null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uk_refresh_tokens_hash'])
            ->addIndex(['user_id'], ['name' => 'idx_refresh_tokens_user_id'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_refresh_tokens_user_id',
            ])
            ->create();
    }
}