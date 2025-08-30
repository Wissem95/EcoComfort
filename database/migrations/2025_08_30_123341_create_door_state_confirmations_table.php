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
        Schema::create('door_state_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Foreign keys
            $table->uuid('sensor_id');
            $table->uuid('user_id');
            
            // State information
            $table->string('confirmed_state', 20); // 'closed', 'opened', 'probably_opened'
            $table->string('previous_state', 20);
            $table->enum('previous_certainty', ['CERTAIN', 'PROBABLE', 'UNCERTAIN'])->nullable();
            
            // Context information
            $table->json('sensor_position')->nullable(); // Position when confirmed
            $table->float('confidence_before')->nullable(); // Confidence before confirmation
            $table->text('user_notes')->nullable(); // Optional user notes
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('sensor_id')->references('id')->on('sensors')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['sensor_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_state_confirmations');
    }
};
