<?php

use Phinx\Migration\AbstractMigration;

/**
 * Identity 模块：分身卡表。
 *
 * 设计要点：
 * - 一个用户可创建多个分身，is_ephemeral 区分常驻 / 一次性分身
 * - 软删除用 is_deleted，因历史内容通过 author_snapshot 保留展示，不能物理删除
 */
final class CreatePersonas extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('personas', [
            'id' => false,
            'primary_key' => 'id',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('alias_name', 'string', ['limit' => 32, 'null' => false, 'comment' => '分身昵称'])
            ->addColumn('avatar_code', 'string', ['limit' => 32, 'null' => true, 'comment' => '内置头像编码'])
            ->addColumn('mood_tag', 'string', ['limit' => 32, 'null' => true, 'comment' => '情绪标签'])
            ->addColumn('is_ephemeral', 'boolean', ['default' => false, 'null' => false, 'comment' => '一次性分身'])
            ->addColumn('is_deleted', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['timezone' => true, 'null' => false])
            ->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true])
            ->addIndex(['user_id'], ['name' => 'idx_personas_user_id'])
            // 常用查询：某用户的有效分身列表
            ->addIndex(['user_id', 'is_deleted'], ['name' => 'idx_personas_user_active'])
            // 同模块内（Identity）加物理外键，保证引用完整性
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_personas_user_id',
            ])
            ->create();

        // 跨模块（如 Can/Chat 引用 personas）不加外键，走应用层校验 + Facade 隔离，
        // 避免模块间表结构耦合与迁移顺序依赖。
    }
}