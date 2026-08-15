<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Connection::get()->exec(
            "ALTER TABLE `documents`
             ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'owner' AFTER `user_id`,
             ADD COLUMN `vehicle_id` BIGINT UNSIGNED NULL AFTER `type`"
        );

        Connection::get()->exec(
            'ALTER TABLE `documents`
             ADD INDEX `documents_type_index` (`type`),
             ADD INDEX `documents_vehicle_id_index` (`vehicle_id`)'
        );

        Connection::get()->exec(
            'ALTER TABLE `documents`
             ADD CONSTRAINT `documents_vehicle_id_foreign`
             FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
             ON DELETE CASCADE'
        );
    }

    public function down(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `documents` DROP FOREIGN KEY `documents_vehicle_id_foreign`'
        );

        Connection::get()->exec(
            'ALTER TABLE `documents`
             DROP INDEX `documents_type_index`,
             DROP INDEX `documents_vehicle_id_index`,
             DROP COLUMN `vehicle_id`,
             DROP COLUMN `type`'
        );
    }
};
