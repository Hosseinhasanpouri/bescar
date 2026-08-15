<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Connection::get()->exec(
            "ALTER TABLE `vehicles`
             ADD COLUMN `vehicle_type` ENUM('car','motorcycle') NULL AFTER `vin`,
             ADD COLUMN `plate_type` ENUM('national','free_zone','motorcycle') NULL AFTER `vehicle_type`,
             ADD COLUMN `plate` VARCHAR(30) NULL AFTER `plate_type`"
        );
    }

    public function down(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `vehicles`
             DROP COLUMN `vehicle_type`,
             DROP COLUMN `plate_type`,
             DROP COLUMN `plate`'
        );
    }
};
