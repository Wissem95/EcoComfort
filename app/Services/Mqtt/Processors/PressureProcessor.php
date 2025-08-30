<?php

namespace App\Services\Mqtt\Processors;

use App\Data\MqttMessageData;
use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PressureProcessor extends BaseProcessor
{
    public function process(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            $pressure = $this->extractPressure($message);
            if ($pressure === null) {
                return;
            }

            $sensor = $this->findSensor($message->sourceAddress);
            if (!$sensor) {
                return;
            }

            // Store pressure data
            $this->storePressureData($sensor, $pressure);

            $this->recordProcessingSuccess((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            $this->recordProcessingError($e);
            Log::error("Error processing pressure data", [
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function extractPressure(MqttMessageData $message): ?float
    {
        $payload = $message->payload;
        
        if (isset($payload['data']['pressure'])) {
            return $payload['data']['pressure'] / 100.0; // Pa to hPa
        }
        
        if (isset($payload['pressure'])) {
            return $payload['pressure'] / 100.0;
        }
        
        return null;
    }

    private function storePressureData(Sensor $sensor, float $pressure): void
    {
        // Update or create sensor data record
        $sensorData = SensorData::where('sensor_id', $sensor->id)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->first();

        if ($sensorData) {
            $sensorData->update(['atmospheric_pressure' => $pressure]);
        } else {
            SensorData::create([
                'sensor_id' => $sensor->id,
                'atmospheric_pressure' => $pressure,
                'created_at' => now(),
            ]);
        }

        Cache::put("sensor:{$sensor->id}:pressure", $pressure, 3600);
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