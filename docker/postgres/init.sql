-- Enable TimescaleDB extension
CREATE EXTENSION IF NOT EXISTS timescaledb;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create partitioned table function
CREATE OR REPLACE FUNCTION create_monthly_partitions(table_name text, start_date date, end_date date)
RETURNS void AS $$
DECLARE
    partition_date date := start_date;
    partition_name text;
BEGIN
    WHILE partition_date < end_date LOOP
        partition_name := table_name || '_' || to_char(partition_date, 'YYYY_MM');
        
        EXECUTE format('CREATE TABLE IF NOT EXISTS %I PARTITION OF %I 
                        FOR VALUES FROM (%L) TO (%L)',
                        partition_name, table_name, 
                        partition_date, 
                        partition_date + interval '1 month');
        
        partition_date := partition_date + interval '1 month';
    END LOOP;
END;
$$ LANGUAGE plpgsql;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_sensor_data_sensor_timestamp ON sensor_data (sensor_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_sensor_data_door_state ON sensor_data (door_state) WHERE door_state IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_events_acknowledged ON events (acknowledged, created_at DESC) WHERE acknowledged = false;