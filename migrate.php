<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migrator;
use App\Support\Env;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);

$command = $argv[1] ?? 'up';

$migrator = new Migrator(Connection::get(), database_path('migrations'));

try {
    match ($command) {
        'up', 'migrate' => (function () use ($migrator) {
            $ran = $migrator->run();
            if ($ran === []) {
                echo "Nothing to migrate.\n";
                return;
            }
            foreach ($ran as $name) {
                echo "Migrated: {$name}\n";
            }
        })(),
        'rollback' => (function () use ($migrator, $argv) {
            $steps = isset($argv[2]) ? (int) $argv[2] : 1;
            $rolled = $migrator->rollback($steps);
            if ($rolled === []) {
                echo "Nothing to rollback.\n";
                return;
            }
            foreach ($rolled as $name) {
                echo "Rolled back: {$name}\n";
            }
        })(),
        'status' => (function () use ($migrator) {
            foreach ($migrator->status() as $row) {
                $mark = $row['ran'] ? '[OK]' : '[ ]';
                echo "{$mark} {$row['migration']}\n";
            }
        })(),
        default => (function () use ($command) {
            fwrite(STDERR, "Unknown command [{$command}]. Use: migrate|up|rollback|status\n");
            exit(1);
        })(),
    };
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
