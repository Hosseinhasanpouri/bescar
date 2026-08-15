<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Database\Connection;
use App\Database\Seeder;
use PDO;

class ManufactorsSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Kia', 'Toyota', 'Iran Khodro'];
        $now = date('Y-m-d H:i:s');
        $db = Connection::get();

        $find = $db->prepare('SELECT id FROM manufactors WHERE name = ? LIMIT 1');
        $insert = $db->prepare(
            'INSERT INTO manufactors (name, logo, created_at, updated_at) VALUES (?, NULL, ?, ?)'
        );

        foreach ($names as $name) {
            $find->execute([$name]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo "  skip: {$name} (already exists)\n";
                continue;
            }

            $insert->execute([$name, $now, $now]);
            echo "  seeded: {$name}\n";
        }
    }
}
