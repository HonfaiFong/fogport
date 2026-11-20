<?php

use Phinx\Migration\AbstractMigration;

/**
 * Identity 模块：设备推送 Token 表。
 *
 * 设计要点：
 * - vendor 覆盖 APNs / FCM / 华米OV荣厂商通道
 * - UNIQUE(device_id, vendor)，同一设备同一厂商只保留一条，重复上报走 upsert
 */
final class CreateUserPushDevices extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_push_devices', [
            'id' => false,
            'primary_key' => 'id',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('platform', 'string', ['limit' => 16, 'null' => false, 'comment' => 'ios/android'])
            ->addColumn('vendor', 'string', ['limit' => 16, 'null' => false, 'comment' => 'apns/fcm/huawei/xiaomi/oppo/vivo/honor'])
            ->addColumn('push_token', 'string', ['limit' => 512, 'null' => false])
            ->addColumn('device_id', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => false])
            ->addIndex(['device_id', 'vendor'], ['unique' => true, 'name' => 'uk_push_devices_device_vendor'])
            ->addIndex(['user_id'], ['name' => 'idx_push_devices_user_id'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_push_devices_user_id',
            ])
            ->create();
    }
}