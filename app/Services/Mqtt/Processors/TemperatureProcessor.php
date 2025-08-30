<?php

namespace App\Services\Mqtt\Processors;

use App\Data\MqttMessageData;
use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TemperatureProcessor extends BaseProcessor
{
    public function process(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            $temperature = $message->extractTemperature();
            if ($temperature === null) {
                Log::warning("No temperature data found in MQTT message", [
                    'source_address' => $message->sourceAddress
                ]);
                return;
            }

            $sensor = $this->findSensor($message->sourceAddress);
            if (!$sensor) {
                return;
            }

            // Apply calibration offset if available
            $calibratedTemp = $this->applyCalibration($sensor, $temperature);

            // Store temperature data
            $this->storeTemperatureData($sensor, $calibratedTemp);

            // Check for temperature alerts
            $this->checkTemperatureAlerts($sensor, $calibratedTemp);

            $this->recordProcessingSuccess((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            $this->recordProcessingError($e);
            Log::error("Error processing temperature data", [
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function applyCalibration(Sensor $sensor, float $temperature): float
    {
        $calibrationData = $sensor->calibration_data;
        $offset = $calibrationData['temperature_offset'] ?? 0;
        
        return $temperature + $offset;
    }

    private function storeTemperatureData(Sensor $sensor, float $temperature): void
    {
        // Update or create sensor data record
        $sensorData = SensorData::where('sensor_id', $sensor->id)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->first();

        if ($sensorData) {
            // Update existing record within the same minute
            $sensorData->update(['temperature' => $temperature]);
        } else {
            // Create new record
            SensorData::create([
                'sensor_id' => $sensor->id,
                'temperature' => $temperature,
                'created_at' => now(),
            ]);
        }

        // Cache latest temperature for quick access
        Cache::put("sensor:{$sensor->id}:temperature", $temperature, 3600);
    }

    private function checkTemperatureAlerts(Sensor $sensor, float $temperature): void
    {
        // Get room comfort settings
        $room = $sensor->room;
        if (!$room) return;

        $minTemp = $room->min_temperature ?? 18.0;
        $maxTemp = $room->max_temperature ?? 24.0;

        $shouldAlert = false;
        $alertType = null;

        if ($temperature < $minTemp) {
            $shouldAlert = true;
            $alertType = 'temperature_too_low';
        } elseif ($temperature > $maxTemp) {
            $shouldAlert = true;
            $alertType = 'temperature_too_high';
        }

        if ($shouldAlert) {
            // Avoid duplicate alerts within 30 minutes
            $alertKey = "temp_alert:{$sensor->id}:{$alertType}";
            if (!Cache::has($alertKey)) {
                $this->sendTemperatureAlert($sensor, $temperature, $alertType);
                Cache::put($alertKey, true, 1800); // 30 minutes
            }
        }
    }

    private function sendTemperatureAlert(Sensor $sensor, float $temperature, string $alertType): void
    {
        Log::info("Temperature alert triggered", [
            'sensor_id' => $sensor->id,
            'temperature' => $temperature,
            'alert_type' => $alertType,
            'room' => $sensor->room->name ?? 'Unknown'
        ]);

        // TODO: Integrate with NotificationService
        // This could dispatch an event or call the notification service directly
    }

    private function findSensor(int $sourceAddress): ?Sensor
    {
        $sensor = Cache::remember(
            "sensor:address:{$sourceAddress}",
            3600, // 1 hour
            fn() => Sensor::where('wirepas_address', $sourceAddress)->first()
        );

        if (!$sensor) {
            Log::warning("Unknown sensor address for temperature data", [
                'source_address' => $sourceAddress
            ]);
        }

        return $sensor;
    }
}