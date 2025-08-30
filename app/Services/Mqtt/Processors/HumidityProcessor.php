<?php

namespace App\Services\Mqtt\Processors;

use App\Data\MqttMessageData;
use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HumidityProcessor extends BaseProcessor
{
    public function process(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            $humidity = $message->extractHumidity();
            if ($humidity === null) {
                Log::warning("No humidity data found in MQTT message", [
                    'source_address' => $message->sourceAddress
                ]);
                return;
            }

            $sensor = $this->findSensor($message->sourceAddress);
            if (!$sensor) {
                return;
            }

            // Store humidity data
            $this->storeHumidityData($sensor, $humidity);

            // Check for humidity alerts
            $this->checkHumidityAlerts($sensor, $humidity);

            $this->recordProcessingSuccess((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            $this->recordProcessingError($e);
            Log::error("Error processing humidity data", [
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function storeHumidityData(Sensor $sensor, float $humidity): void
    {
        // Update or create sensor data record
        $sensorData = SensorData::where('sensor_id', $sensor->id)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->first();

        if ($sensorData) {
            $sensorData->update(['humidity' => $humidity]);
        } else {
            SensorData::create([
                'sensor_id' => $sensor->id,
                'humidity' => $humidity,
                'created_at' => now(),
            ]);
        }

        Cache::put("sensor:{$sensor->id}:humidity", $humidity, 3600);
    }

    private function checkHumidityAlerts(Sensor $sensor, float $humidity): void
    {
        $room = $sensor->room;
        if (!$room) return;

        $minHumidity = $room->min_humidity ?? 30.0;
        $maxHumidity = $room->max_humidity ?? 60.0;

        $shouldAlert = false;
        $alertType = null;

        if ($humidity < $minHumidity) {
            $shouldAlert = true;
            $alertType = 'humidity_too_low';
        } elseif ($humidity > $maxHumidity) {
            $shouldAlert = true;
            $alertType = 'humidity_too_high';
        }

        if ($shouldAlert) {
            $alertKey = "humidity_alert:{$sensor->id}:{$alertType}";
            if (!Cache::has($alertKey)) {
                Log::info("Humidity alert triggered", [
                    'sensor_id' => $sensor->id,
                    'humidity' => $humidity,
                    'alert_type' => $alertType
                ]);
                Cache::put($alertKey, true, 1800); // 30 minutes
            }
        }
    }

    private function findSensor(int $sourceAddress): ?Sensor
    {
        return Cache::remember(
            "sensor:address:{$sourceAddress}",
            3600,
            fn() => Sensor::where('wirepas_address', $sourceAddress)->first()
        );
    }
}