<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('vehicle_id');
            $table->date('service_date');
            $table->unsignedInteger('odometer');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('vehicle_id');
            $table->index('service_date');
            $table->foreign('user_id', 'users');
            $table->foreign('vehicle_id', 'vehicles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
