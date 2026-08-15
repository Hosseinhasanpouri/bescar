<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

abstract class Migration
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
