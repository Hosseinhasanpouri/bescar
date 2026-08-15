<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('vehicle_model_id');
            $table->timestamps();

            $table->index('user_id');
            $table->index('vehicle_model_id');
            $table->foreign('user_id', 'users');
            $table->foreign('vehicle_model_id', 'vehicle_models');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
