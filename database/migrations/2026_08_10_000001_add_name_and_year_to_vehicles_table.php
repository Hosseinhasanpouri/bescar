<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `vehicles`
             ADD COLUMN `name` VARCHAR(255) NULL AFTER `vehicle_model_id`,
             ADD COLUMN `year` SMALLINT UNSIGNED NULL AFTER `name`'
        );
    }

    public function down(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `vehicles`
             DROP COLUMN `name`,
             DROP COLUMN `year`'
        );
    }
};
