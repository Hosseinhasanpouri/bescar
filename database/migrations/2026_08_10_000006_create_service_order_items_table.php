<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id');
            $table->foreignId('service_id');
            $table->decimal('price', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('service_order_id');
            $table->index('service_id');
            $table->foreign('service_order_id', 'service_orders');
            $table->foreign('service_id', 'services', 'id', 'RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_items');
    }
};
