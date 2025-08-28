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
        Schema::table('sensors', function (Blueprint $table) {
            // RuuviTag specific fields
            $table->bigInteger('source_address')->nullable()->after('mac_address')->comment('RuuviTag source address');
            $table->integer('sensor_type_id')->nullable()->after('source_address')->comment('RuuviTag sensor type ID (112=temp, 114=humidity, 127=movement)');
            $table->string('type')->default('generic')->after('name')->comment('Sensor type: ruuvitag, generic, etc.');
            
            // Calibration offsets for RuuviTag
            $table->decimal('temperature_offset', 5, 2)->default(0.0)->after('calibration_data')->comment('Temperature calibration offset');
            $table->decimal('humidity_offset', 5, 2)->default(0.0)->after('temperature_offset')->comment('Humidity calibration offset');
            
            // Add indexes for performance
            $table->index(['source_address', 'sensor_type_id'], 'sensors_ruuvitag_lookup');
            $table->index('type', 'sensors_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropIndex('sensors_ruuvitag_lookup');
            $table->dropIndex('sensors_type_index');
            $table->dropColumn([
                'source_address', 
                'sensor_type_id', 
                'type',
                'temperature_offset', 
                'humidity_offset'
            ]);
        });
    }
};
