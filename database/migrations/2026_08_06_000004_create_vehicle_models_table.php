<?php

declare(strict_types=1);

use App\Database\Migration;
use App\Database\Schema;
use App\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('manufactor_id');
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index('manufactor_id');
            $table->foreign('manufactor_id', 'manufactors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
