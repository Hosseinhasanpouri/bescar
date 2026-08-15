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
             ADD COLUMN `vin` VARCHAR(17) NULL AFTER `year`'
        );
    }

    public function down(): void
    {
        Connection::get()->exec(
            'ALTER TABLE `vehicles` DROP COLUMN `vin`'
        );
    }
};
