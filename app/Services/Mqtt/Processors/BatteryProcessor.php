<?php

namespace App\Services\Mqtt\Processors;

use App\Data\MqttMessageData;
use App\Models\Sensor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BatteryProcessor extends BaseProcessor
{
    private const LOW_BATTERY_THRESHOLD = 2.5; // Volts
    private const CRITICAL_BATTERY_THRESHOLD = 2.2; // Volts
    
    public function process(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            $batteryVoltage = $this->extractBatteryVoltage($message);
            if ($batteryVoltage === null) {
                return;
            }

            $sensor = $this->findSensor($message->sourceAddress);
            if (!$sensor) {
                return;
            }

            // Update battery status
            $this->updateBatteryStatus($sensor, $batteryVoltage);

            // Check for low battery alerts
            $this->checkBatteryAlerts($sensor, $batteryVoltage);

            $this->recordProcessingSuccess((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            $this->recordProcessingError($e);
            Log::error("Error processing battery data", [
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function extractBatteryVoltage(MqttMessageData $message): ?float
    {
        $payload = $message->payload;
        
        // Handle different battery data formats
        if (isset($payload['data']['batteryVoltage'])) {
            return $payload['data']['batteryVoltage'] / 1000.0; // mV to V
        }
        
        if (isset($payload['batteryVoltage'])) {
            return $payload['batteryVoltage'] / 1000.0;
        }
        
        if (isset($payload['data']['voltage'])) {
            return $payload['data']['voltage'];
        }
        
        return null;
    }

    private function updateBatteryStatus(Sensor $sensor, float $voltage): void
    {
        $batteryPercent = $this->calculateBatteryPercentage($voltage);
        
        $sensor->update([
            'battery_voltage' => $voltage,
            'battery_percentage' => $batteryPercent,
            'last_seen' => now(),
        ]);

        Cache::put("sensor:{$sensor->id}:battery", [
            'voltage' => $voltage,
            'percentage' => $batteryPercent,
            'updated_at' => now()->toISOString()
        ], 3600);
    }

    private function calculateBatteryPercentage(float $voltage): int
    {
        // Li-Ion battery curve approximation (3V nominal)
        $maxVoltage = 3.3; // Fully charged
        $minVoltage = 2.0; // Depleted
        
        $percentage = (($voltage - $minVoltage) / ($maxVoltage - $minVoltage)) * 100;
        
        return (int) max(0, min(100, $percentage));
    }

    private function checkBatteryAlerts(Sensor $sensor, float $voltage): void
    {
        $alertType = null;
        
        if ($voltage <= self::CRITICAL_BATTERY_THRESHOLD) {
            $alertType = 'battery_critical';
        } elseif ($voltage <= self::LOW_BATTERY_THRESHOLD) {
            $alertType = 'battery_low';
        }

        if ($alertType) {
            $alertKey = "battery_alert:{$sensor->id}:{$alertType}";
            
            // Send alert only once per 24 hours for the same alert type
            if (!Cache::has($alertKey)) {
                Log::warning("Battery alert triggered", [
                    'sensor_id' => $sensor->id,
                    'sensor_name' => $sensor->name,
                    'voltage' => $voltage,
                    'alert_type' => $alertType,
                    'battery_percentage' => $this->calculateBatteryPercentage($voltage)
                ]);
                
                Cache::put($alertKey, true, 86400); // 24 hours
            }
        }
    }

    private function findSensor(int $sourceAddress): ?Sensor
    {
        return Cache::remember(
            "sensor:address:{$sourceAddress}",
            3600,
            fn() => \App\Models\Sensor::where('wirepas_address', $sourceAddress)->first()
        );
    }
}