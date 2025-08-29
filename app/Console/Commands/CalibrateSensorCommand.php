<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\SensorData;
use App\Services\DoorDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalibrateSensorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sensor:calibrate 
                           {sensor_id : The sensor ID to calibrate}
                           {--door : Calibrate door detection thresholds}
                           {--temperature : Calibrate temperature offset}
                           {--duration=30 : Duration in seconds to collect calibration data}';

    /**
     * The console command description.
     */
    protected $description = 'Calibrate sensor parameters for door detection and temperature accuracy';

    /**
     * Execute the console command.
     */
    public function handle(DoorDetectionService $doorDetectionService): int
    {
        $sensorId = $this->argument('sensor_id');
        $duration = (int) $this->option('duration');
        
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            $this->error("❌ Sensor with ID {$sensorId} not found");
            return self::FAILURE;
        }
        
        $this->info("🔧 Starting calibration for sensor: {$sensor->name} (ID: {$sensorId})");
        
        if ($this->option('door')) {
            return $this->calibrateDoorDetection($sensor, $doorDetectionService, $duration);
        }
        
        if ($this->option('temperature')) {
            return $this->calibrateTemperature($sensor, $duration);
        }
        
        $this->error("❌ Please specify --door or --temperature calibration option");
        return self::FAILURE;
    }
    
    private function calibrateDoorDetection(Sensor $sensor, DoorDetectionService $doorDetectionService, int $duration): int
    {
        $this->info("🚪 Starting door detection calibration...");
        $this->info("📋 Instructions:");
        $this->line("1. Make sure the door is CLOSED");
        $this->line("2. Press Enter when ready to start collecting CLOSED position data");
        
        $this->ask('Press Enter to continue...');
        
        // Collect closed door data
        $this->info("📊 Collecting CLOSED door data for {$duration} seconds...");
        $closedData = $this->collectAccelerometerData($sensor, $duration);
        
        if (empty($closedData)) {
            $this->error("❌ No accelerometer data collected for closed position");
            return self::FAILURE;
        }
        
        $this->info("✅ Collected " . count($closedData) . " data points for closed position");
        
        // Calculate closed position averages
        $closedAvg = $this->calculateAverageAcceleration($closedData);
        
        $this->newLine();
        $this->info("📊 CLOSED position averages:");
        $this->line("  X: " . round($closedAvg['x'], 3));
        $this->line("  Y: " . round($closedAvg['y'], 3));
        $this->line("  Z: " . round($closedAvg['z'], 3));
        
        $this->newLine();
        $this->info("🚪 Now OPEN the door and press Enter when ready");
        $this->ask('Press Enter when door is OPEN...');
        
        // Collect open door data
        $this->info("📊 Collecting OPEN door data for {$duration} seconds...");
        $openData = $this->collectAccelerometerData($sensor, $duration);
        
        if (empty($openData)) {
            $this->error("❌ No accelerometer data collected for open position");
            return self::FAILURE;
        }
        
        $this->info("✅ Collected " . count($openData) . " data points for open position");
        
        // Calculate open position averages
        $openAvg = $this->calculateAverageAcceleration($openData);
        
        $this->info("📊 OPEN position averages:");
        $this->line("  X: " . round($openAvg['x'], 3));
        $this->line("  Y: " . round($openAvg['y'], 3));
        $this->line("  Z: " . round($openAvg['z'], 3));
        
        // Calculate differences and determine primary axis
        $differences = [
            'x' => abs($openAvg['x'] - $closedAvg['x']),
            'y' => abs($openAvg['y'] - $closedAvg['y']),
            'z' => abs($openAvg['z'] - $closedAvg['z'])
        ];
        
        $primaryAxis = array_keys($differences, max($differences))[0];
        $threshold = $differences[$primaryAxis] / 2; // Threshold is halfway between positions
        
        $this->newLine();
        $this->info("🎯 Calibration Results:");
        $this->line("  Primary axis: {$primaryAxis}");
        $this->line("  Difference: " . round($differences[$primaryAxis], 3));
        $this->line("  Suggested threshold: " . round($threshold, 3));
        
        // Save calibration data
        $calibrationData = [
            'door_detection' => [
                'primary_axis' => $primaryAxis,
                'threshold' => $threshold,
                'closed_position' => $closedAvg,
                'open_position' => $openAvg,
                'calibrated_at' => now()->toISOString()
            ]
        ];
        
        // Update sensor calibration data
        $sensor->update([
            'calibration_data' => array_merge($sensor->calibration_data ?? [], $calibrationData)
        ]);
        
        $this->info("✅ Door detection calibration saved to sensor configuration");
        $this->info("🔄 The door detection service will use these parameters automatically");
        
        return self::SUCCESS;
    }
    
    private function calibrateTemperature(Sensor $sensor, int $duration): int
    {
        $this->info("🌡️ Starting temperature calibration...");
        $this->info("📋 Instructions:");
        $this->line("1. Place a reference thermometer near the sensor");
        $this->line("2. Wait for both readings to stabilize");
        
        $actualTemp = $this->ask('Enter the actual temperature from reference thermometer (°C)');
        $actualTemp = floatval($actualTemp);
        
        if ($actualTemp === 0.0) {
            $this->error("❌ Invalid temperature value");
            return self::FAILURE;
        }
        
        // Collect current sensor readings
        $this->info("📊 Collecting sensor temperature data for {$duration} seconds...");
        $temperatureData = $this->collectTemperatureData($sensor, $duration);
        
        if (empty($temperatureData)) {
            $this->error("❌ No temperature data collected");
            return self::FAILURE;
        }
        
        $sensorAvgTemp = array_sum($temperatureData) / count($temperatureData);
        $offset = $actualTemp - $sensorAvgTemp;
        
        $this->info("📊 Temperature Calibration Results:");
        $this->line("  Sensor average: " . round($sensorAvgTemp, 2) . "°C");
        $this->line("  Actual temperature: " . round($actualTemp, 2) . "°C");
        $this->line("  Calculated offset: " . round($offset, 2) . "°C");
        
        // Save temperature calibration
        $calibrationData = [
            'temperature' => [
                'offset' => $offset,
                'reference_temp' => $actualTemp,
                'sensor_reading' => $sensorAvgTemp,
                'calibrated_at' => now()->toISOString()
            ]
        ];
        
        $sensor->update([
            'calibration_data' => array_merge($sensor->calibration_data ?? [], $calibrationData)
        ]);
        
        $this->info("✅ Temperature calibration saved to sensor configuration");
        
        return self::SUCCESS;
    }
    
    private function collectAccelerometerData(Sensor $sensor, int $duration): array
    {
        $startTime = now();
        $endTime = $startTime->copy()->addSeconds($duration);
        $data = [];
        
        $progressBar = $this->output->createProgressBar($duration);
        $progressBar->start();
        
        while (now()->lt($endTime)) {
            $recent = SensorData::where('sensor_id', $sensor->id)
                ->where('timestamp', '>=', now()->subSeconds(5))
                ->whereNotNull(['acceleration_x', 'acceleration_y', 'acceleration_z'])
                ->latest()
                ->first();
            
            if ($recent) {
                $data[] = [
                    'x' => $recent->acceleration_x,
                    'y' => $recent->acceleration_y,
                    'z' => $recent->acceleration_z,
                    'timestamp' => $recent->timestamp
                ];
            }
            
            sleep(1);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        return $data;
    }
    
    private function collectTemperatureData(Sensor $sensor, int $duration): array
    {
        $startTime = now();
        $endTime = $startTime->copy()->addSeconds($duration);
        $data = [];
        
        $progressBar = $this->output->createProgressBar($duration);
        $progressBar->start();
        
        while (now()->lt($endTime)) {
            $recent = SensorData::where('sensor_id', $sensor->id)
                ->where('timestamp', '>=', now()->subSeconds(5))
                ->whereNotNull('temperature')
                ->latest()
                ->first();
            
            if ($recent) {
                $data[] = $recent->temperature;
            }
            
            sleep(1);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        return $data;
    }
    
    private function calculateAverageAcceleration(array $data): array
    {
        if (empty($data)) {
            return ['x' => 0, 'y' => 0, 'z' => 0];
        }
        
        $sums = ['x' => 0, 'y' => 0, 'z' => 0];
        
        foreach ($data as $point) {
            $sums['x'] += $point['x'];
            $sums['y'] += $point['y'];
            $sums['z'] += $point['z'];
        }
        
        $count = count($data);
        
        return [
            'x' => $sums['x'] / $count,
            'y' => $sums['y'] / $count,
            'z' => $sums['z'] / $count,
        ];
    }
}