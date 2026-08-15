<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Schema\Blueprint;
use Closure;
use PDO;

class Schema
{
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        Connection::get()->exec($blueprint->toSql());
    }

    public static function dropIfExists(string $table): void
    {
        Connection::get()->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    public static function hasTable(string $table): bool
    {
        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
