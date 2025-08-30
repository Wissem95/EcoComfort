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
        Schema::create('energy_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Référence au capteur
            $table->uuid('sensor_id');
            $table->foreign('sensor_id')->references('id')->on('sensors')->onDelete('cascade');
            
            // Période de l'événement
            $table->timestamp('start_time')->comment('When the door was opened');
            $table->timestamp('end_time')->nullable()->comment('When the door was closed (null if ongoing)');
            
            // Métriques énergétiques
            $table->decimal('total_energy_kwh', 10, 3)->comment('Total energy lost during this event (kWh)');
            $table->decimal('total_cost_euros', 8, 2)->comment('Total cost for this event (€)');
            $table->decimal('average_power_watts', 8, 2)->comment('Average power loss rate during event (W)');
            
            // Durée et conditions
            $table->integer('duration_seconds')->comment('Event duration in seconds');
            $table->decimal('avg_indoor_temp', 4, 2)->nullable()->comment('Average indoor temperature (°C)');
            $table->decimal('outdoor_temp', 4, 2)->nullable()->comment('Outdoor temperature (°C)');
            $table->decimal('delta_temp', 4, 2)->nullable()->comment('Temperature difference (°C)');
            
            // Métadonnées
            $table->boolean('is_ongoing')->default(false)->comment('True if door is still open');
            $table->enum('detection_method', ['automatic', 'manual_confirmation'])->default('automatic');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes pour les performances
            $table->index(['sensor_id', 'start_time']);
            $table->index(['is_ongoing']);
            $table->index(['start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_events');
    }
};