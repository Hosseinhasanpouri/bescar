<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `services`
             ADD COLUMN `default_interval_km` INT UNSIGNED NULL AFTER `is_active`,
             ADD COLUMN `default_interval_months` INT UNSIGNED NULL AFTER `default_interval_km`'
        );
    }

    public function down(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `services`
             DROP COLUMN `default_interval_km`,
             DROP COLUMN `default_interval_months`'
        );
    }
};
