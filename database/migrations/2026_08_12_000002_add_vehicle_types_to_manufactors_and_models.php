<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $db = Connection::get();

        $db->exec(
            "ALTER TABLE `manufactors`
             ADD COLUMN `vehicle_types` VARCHAR(255) NULL AFTER `logo`"
        );

        $db->exec(
            "ALTER TABLE `vehicle_models`
             ADD COLUMN `vehicle_types` VARCHAR(255) NULL AFTER `image`"
        );

        $db->exec(
            "ALTER TABLE `vehicles`
             MODIFY COLUMN `vehicle_type` ENUM('car','truck','motorcycle') NULL"
        );

        // Default existing manufactors and models to all types
        $db->exec("UPDATE `manufactors` SET `vehicle_types` = 'car,truck,motorcycle' WHERE `vehicle_types` IS NULL");
        $db->exec("UPDATE `vehicle_models` SET `vehicle_types` = 'car,truck,motorcycle' WHERE `vehicle_types` IS NULL");
    }

    public function down(): void
    {
        $db = Connection::get();

        $db->exec("ALTER TABLE `manufactors` DROP COLUMN `vehicle_types`");
        $db->exec("ALTER TABLE `vehicle_models` DROP COLUMN `vehicle_types`");
        $db->exec(
            "ALTER TABLE `vehicles`
             MODIFY COLUMN `vehicle_type` ENUM('car','motorcycle') NULL"
        );
    }
};
