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
            // Add door state certainty level
            $table->enum('door_state_certainty', ['CERTAIN', 'PROBABLE', 'UNCERTAIN'])
                  ->default('CERTAIN')
                  ->after('door_state');
            
            // Add flag to indicate if manual confirmation is needed
            $table->boolean('needs_confirmation')
                  ->default(false)
                  ->after('door_state_certainty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropColumn('needs_confirmation');
            $table->dropColumn('door_state_certainty');
        });
    }
};
