<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('vehicle_id');
            $table->foreignId('service_id');
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedInteger('interval_months')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'service_id']);
            $table->index('user_id');
            $table->index('vehicle_id');
            $table->index('service_id');

            $table->foreign('user_id', 'users');
            $table->foreign('vehicle_id', 'vehicles');
            $table->foreign('service_id', 'services');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_rules');
    }
};
