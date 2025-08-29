<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sensor_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('sensor_id');
            $table->foreign('sensor_id')->references('id')->on('sensors')->onDelete('cascade');
            $table->string('event_type', 50); // 'start-moving', 'stop-moving'
            $table->integer('position_x');
            $table->integer('position_y');
            $table->integer('position_z');
            $table->integer('move_duration')->nullable();
            $table->integer('move_number')->nullable();
            $table->string('door_state', 20)->nullable(); // 'open', 'closed', 'unknown'
            $table->bigInteger('tx_time_ms_epoch'); // Timestamp from Wirepas
            $table->integer('event_id'); // Wirepas event ID
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['sensor_id', 'event_type']);
            $table->index(['sensor_id', 'created_at']);
            $table->index('tx_time_ms_epoch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_events');
    }
};