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
        Schema::table('sensor_data', function (Blueprint $table) {
            // Énergie cumulative depuis l'ouverture de la porte (en kWh)
            $table->decimal('cumulative_energy_kwh', 10, 3)
                ->default(0)
                ->after('energy_loss_watts')
                ->comment('Cumulative energy lost since door opened (kWh)');
            
            // Timestamp d'ouverture de la porte (null si fermée)
            $table->timestamp('door_open_since')
                ->nullable()
                ->after('cumulative_energy_kwh')
                ->comment('Timestamp when door was opened (null if closed)');
            
            // Coût cumulé en euros
            $table->decimal('energy_cost_euros', 8, 2)
                ->default(0)
                ->after('door_open_since')
                ->comment('Cumulative energy cost in euros');
            
            // Durée d'ouverture en secondes
            $table->integer('door_open_duration_seconds')
                ->default(0)
                ->after('energy_cost_euros')
                ->comment('Total door open duration in seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropColumn([
                'cumulative_energy_kwh',
                'door_open_since',
                'energy_cost_euros',
                'door_open_duration_seconds'
            ]);
        });
    }
};