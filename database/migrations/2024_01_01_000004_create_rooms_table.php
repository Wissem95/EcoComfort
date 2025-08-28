<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('building_id');
            $table->string('name', 255);
            $table->integer('floor')->default(0);
            $table->decimal('surface_m2', 8, 2);
            $table->enum('type', ['office', 'meeting', 'corridor', 'bathroom', 'kitchen', 'storage', 'other'])->default('office');
            $table->decimal('target_temperature', 4, 1)->default(21.0);
            $table->decimal('target_humidity', 4, 1)->default(50.0);
            $table->timestamps();
            
            $table->foreign('building_id')->references('id')->on('buildings')->onDelete('cascade');
            $table->index('building_id');
            $table->index('type');
            $table->index('floor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};