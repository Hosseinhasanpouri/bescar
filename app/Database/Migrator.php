<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

class Migrator
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $path,
    ) {
    }

    public function run(): array
    {
        $this->ensureMigrationsTable();

        $ran = [];
        $pending = $this->pendingMigrations();

        if ($pending === []) {
            return $ran;
        }

        $batch = $this->lastBatch() + 1;

        foreach ($pending as $file) {
            $migration = $this->resolve($file);
            $name = basename($file, '.php');

            try {
                // MySQL auto-commits DDL (CREATE/DROP TABLE), so avoid wrapping in a transaction.
                $migration->up();
                $this->log($name, $batch);
                $ran[] = $name;
            } catch (Throwable $e) {
                throw new RuntimeException("Migration [{$name}] failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $ran;
    }

    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTable();

        $rolled = [];
        $batches = $this->recentBatches($steps);

        if ($batches === []) {
            return $rolled;
        }

        $placeholders = implode(',', array_fill(0, count($batches), '?'));
        $stmt = $this->db->prepare(
            "SELECT migration FROM migrations WHERE batch IN ({$placeholders}) ORDER BY batch DESC, id DESC"
        );
        $stmt->execute($batches);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($migrations as $name) {
            $file = $this->path . DIRECTORY_SEPARATOR . $name . '.php';

            if (! is_file($file)) {
                throw new RuntimeException("Migration file [{$name}.php] not found.");
            }

            $migration = $this->resolve($file);

            try {
                // MySQL auto-commits DDL (CREATE/DROP TABLE), so avoid wrapping in a transaction.
                $migration->down();
                $delete = $this->db->prepare('DELETE FROM migrations WHERE migration = ?');
                $delete->execute([$name]);
                $rolled[] = $name;
            } catch (Throwable $e) {
                throw new RuntimeException("Rollback [{$name}] failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $rolled;
    }

    public function status(): array
    {
        $this->ensureMigrationsTable();

        $ran = $this->ranMigrations();
        $files = $this->migrationFiles();
        $rows = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $rows[] = [
                'migration' => $name,
                'ran' => in_array($name, $ran, true),
            ];
        }

        return $rows;
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL,
                UNIQUE KEY `migrations_migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        return array_values($files);
    }

    /** @return list<string> */
    private function ranMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id ASC');

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $ran = $this->ranMigrations();

        return array_values(array_filter(
            $this->migrationFiles(),
            static fn (string $file): bool => ! in_array(basename($file, '.php'), $ran, true)
        ));
    }

    private function lastBatch(): int
    {
        $batch = $this->db->query('SELECT MAX(batch) FROM migrations')->fetchColumn();

        return (int) ($batch ?: 0);
    }

    /** @return list<int> */
    private function recentBatches(int $steps): array
    {
        $stmt = $this->db->query('SELECT DISTINCT batch FROM migrations ORDER BY batch DESC');
        $batches = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        return array_slice($batches, 0, max(1, $steps));
    }

    private function log(string $migration, int $batch): void
    {
        $stmt = $this->db->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->execute([$migration, $batch]);
    }

    private function resolve(string $file): Migration
    {
        $migration = require $file;

        if (! $migration instanceof Migration) {
            throw new RuntimeException("Migration [{$file}] must return a Migration instance.");
        }

        return $migration;
    }
}
