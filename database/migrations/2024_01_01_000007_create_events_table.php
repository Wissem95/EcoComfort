<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sensor_id');
            $table->uuid('room_id');
            $table->enum('type', ['door_open', 'window_open', 'temperature_high', 'temperature_low', 'humidity_high', 'humidity_low', 'energy_loss', 'battery_low']);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->text('message');
            $table->json('data')->nullable();
            $table->decimal('cost_impact', 10, 2)->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->uuid('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            
            $table->foreign('sensor_id')->references('id')->on('sensors')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['acknowledged', 'created_at']);
            $table->index('type');
            $table->index('severity');
            $table->index('sensor_id');
            $table->index('room_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};