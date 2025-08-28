<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            $this->createPostgreSQLTable();
        } else {
            $this->createStandardTable();
        }
    }
    
    private function createPostgreSQLTable(): void
    {
        // Create partitioned table using raw SQL for PostgreSQL
        DB::statement('
            CREATE TABLE sensor_data (
                id BIGSERIAL,
                sensor_id UUID NOT NULL,
                timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                temperature DECIMAL(5,2),
                humidity DECIMAL(5,2),
                acceleration_x DECIMAL(8,4),
                acceleration_y DECIMAL(8,4),
                acceleration_z DECIMAL(8,4),
                door_state BOOLEAN,
                energy_loss_watts DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, timestamp)
            ) PARTITION BY RANGE (timestamp);
        ');
        
        // Add foreign key
        DB::statement('ALTER TABLE sensor_data ADD CONSTRAINT sensor_data_sensor_id_foreign 
                       FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE;');
        
        // Create indexes for better performance
        DB::statement('CREATE INDEX idx_sensor_data_sensor_timestamp ON sensor_data (sensor_id, timestamp DESC);');
        DB::statement('CREATE INDEX idx_sensor_data_door_state ON sensor_data (door_state) WHERE door_state IS NOT NULL;');
        DB::statement('CREATE INDEX idx_sensor_data_timestamp ON sensor_data (timestamp DESC);');
        
        // Create function to automatically create monthly partitions
        DB::statement("
            CREATE OR REPLACE FUNCTION create_sensor_data_partition()
            RETURNS trigger AS $$
            DECLARE
                partition_name text;
                start_date date;
                end_date date;
            BEGIN
                start_date := date_trunc('month', NEW.timestamp);
                end_date := start_date + interval '1 month';
                partition_name := 'sensor_data_' || to_char(NEW.timestamp, 'YYYY_MM');
                
                IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = partition_name) THEN
                    EXECUTE format('CREATE TABLE %I PARTITION OF sensor_data 
                                    FOR VALUES FROM (%L) TO (%L)',
                                    partition_name, start_date, end_date);
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
        
        // Create trigger for automatic partition creation
        DB::statement("
            CREATE TRIGGER trigger_create_sensor_data_partition
            BEFORE INSERT ON sensor_data
            FOR EACH ROW EXECUTE FUNCTION create_sensor_data_partition();
        ");
        
        // Create initial partitions for the next 3 months
        $currentMonth = now();
        for ($i = 0; $i < 3; $i++) {
            $partitionDate = $currentMonth->copy()->addMonths($i);
            $partitionName = 'sensor_data_' . $partitionDate->format('Y_m');
            $startDate = $partitionDate->startOfMonth()->format('Y-m-d');
            $endDate = $partitionDate->copy()->addMonth()->startOfMonth()->format('Y-m-d');
            
            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partitionName} PARTITION OF sensor_data 
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}');
            ");
        }
    }
    
    private function createStandardTable(): void
    {
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sensor_id', 36);
            $table->timestamp('timestamp')->nullable(false);
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('acceleration_x', 8, 4)->nullable();
            $table->decimal('acceleration_y', 8, 4)->nullable();
            $table->decimal('acceleration_z', 8, 4)->nullable();
            $table->boolean('door_state')->nullable();
            $table->decimal('energy_loss_watts', 10, 2)->nullable();
            $table->timestamps();
            
            $table->index(['sensor_id', 'timestamp']);
            $table->index('timestamp');
            $table->index('door_state');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // Drop trigger and function
            DB::statement('DROP TRIGGER IF EXISTS trigger_create_sensor_data_partition ON sensor_data;');
            DB::statement('DROP FUNCTION IF EXISTS create_sensor_data_partition();');
            
            // Drop the main table (this will also drop all partitions)
            DB::statement('DROP TABLE IF EXISTS sensor_data CASCADE;');
        } else {
            Schema::dropIfExists('sensor_data');
        }
    }
};