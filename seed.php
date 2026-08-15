<?php

declare(strict_types=1);

use App\Support\Env;
use Database\Seeders\ManufactorsSeeder;
use Database\Seeders\ServicesSeeder;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);

$seeders = [
    ManufactorsSeeder::class,
    ServicesSeeder::class,
];

$only = $argv[1] ?? null;

try {
    foreach ($seeders as $class) {
        $short = (new ReflectionClass($class))->getShortName();

        if ($only !== null && strcasecmp($only, $short) !== 0 && strcasecmp($only, $class) !== 0) {
            continue;
        }

        echo "Seeding {$short}...\n";
        (new $class())->run();
    }

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
