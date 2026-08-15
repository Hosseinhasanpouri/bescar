<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odometer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('vehicle_id');
            $table->unsignedInteger('value');
            $table->timestamps();

            $table->index('user_id');
            $table->index('vehicle_id');
            $table->foreign('user_id', 'users');
            $table->foreign('vehicle_id', 'vehicles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odometer');
    }
};
