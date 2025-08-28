<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->string('mac_address', 17)->unique();
            $table->string('name', 255);
            $table->enum('position', ['door', 'window', 'wall', 'ceiling', 'floor'])->default('wall');
            $table->integer('battery_level')->default(100);
            $table->json('calibration_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->index('room_id');
            $table->index('mac_address');
            $table->index('position');
            $table->index('is_active');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};