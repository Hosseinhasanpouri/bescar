<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Database\Seeder;
use App\Models\Service;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Oil Change',
                'slug' => 'oil-change',
                'description' => 'Engine oil and filter replacement',
                'default_interval_km' => 5000,
                'default_interval_months' => 6,
            ],
            [
                'name' => 'Brake Service',
                'slug' => 'brake-service',
                'description' => 'Brake pads, discs, or fluid service',
                'default_interval_km' => 20000,
                'default_interval_months' => 12,
            ],
            [
                'name' => 'Tire Replacement',
                'slug' => 'tire-replacement',
                'description' => 'Tire change or rotation',
                'default_interval_km' => 40000,
                'default_interval_months' => 24,
            ],
            [
                'name' => 'Battery Replacement',
                'slug' => 'battery-replacement',
                'description' => 'Car battery replacement',
                'default_interval_km' => null,
                'default_interval_months' => 24,
            ],
            [
                'name' => 'General Inspection',
                'slug' => 'general-inspection',
                'description' => 'Routine vehicle inspection',
                'default_interval_km' => 10000,
                'default_interval_months' => 12,
            ],
        ];

        foreach ($services as $service) {
            $existing = Service::findBySlug($service['slug']);
            if ($existing !== null) {
                $existing->update([
                    'default_interval_km' => $service['default_interval_km'],
                    'default_interval_months' => $service['default_interval_months'],
                ]);
                echo "  updated defaults: {$service['name']}\n";
                continue;
            }

            Service::create([
                'name' => $service['name'],
                'slug' => $service['slug'],
                'description' => $service['description'],
                'is_active' => true,
                'default_interval_km' => $service['default_interval_km'],
                'default_interval_months' => $service['default_interval_months'],
            ]);
            echo "  seeded: {$service['name']}\n";
        }
    }
}
