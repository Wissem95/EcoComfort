<?php

namespace App\Services\Mqtt\Processors;

use App\Data\MqttMessageData;
use App\Services\DoorDetection\AccelerometerNormalizer;
use App\Services\DoorDetection\DoorStateAnalyzer;
use App\Services\DoorDetection\KalmanFilterService;
use App\Models\Sensor;
use App\Models\SensorData;
use App\Events\DoorStateChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MovementProcessor extends BaseProcessor
{
    public function __construct(
        private AccelerometerNormalizer $normalizer,
        private DoorStateAnalyzer $analyzer,
        private KalmanFilterService $kalmanFilter,
    ) {}

    public function process(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            $movementData = $message->extractMovementData();
            if (!$movementData) {
                Log::warning("No movement data found in MQTT message", [
                    'source_address' => $message->sourceAddress
                ]);
                return;
            }

            $sensor = $this->findOrCreateSensor($message->sourceAddress);
            if (!$sensor) {
                return;
            }

            // Apply Kalman filtering to accelerometer data
            $filteredData = $this->applyKalmanFilter($sensor, $movementData);
            
            // Normalize to g-force
            $normalizedData = $this->normalizer->normalize(
                $filteredData['x'],
                $filteredData['y'],
                $filteredData['z']
            );

            // Analyze door state
            $calibration = $sensor->getCalibrationData();
            $doorStateResult = $this->analyzer->analyze($normalizedData, $calibration);

            // Store sensor data
            $this->storeSensorData($sensor, $movementData, $doorStateResult);

            // Dispatch events if state changed
            $this->checkAndDispatchStateChange($sensor, $doorStateResult);

            $this->recordProcessingSuccess((microtime(true) - $startTime) * 1000);
            
        } catch (\Exception $e) {
            $this->recordProcessingError($e);
            Log::error("Error processing movement data", [
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function applyKalmanFilter(Sensor $sensor, array $movementData): array
    {
        $sensorId = $sensor->id;
        $cacheKey = "kalman_states:{$sensorId}";
        $previousStates = Cache::get($cacheKey);

        $filteredResult = $this->kalmanFilter->filterAccelerometer(
            $movementData['x_axis'],
            $movementData['y_axis'],
            $movementData['z_axis'],
            $previousStates
        );

        // Store updated states in cache
        Cache::put($cacheKey, [
            'x' => $filteredResult['x'],
            'y' => $filteredResult['y'],
            'z' => $filteredResult['z'],
        ], 3600); // 1 hour TTL

        return $filteredResult['filtered_values'];
    }

    private function storeSensorData(Sensor $sensor, array $movementData, $doorStateResult): void
    {
        $sensorData = new SensorData([
            'sensor_id' => $sensor->id,
            'acceleration_x' => $movementData['x_axis'],
            'acceleration_y' => $movementData['y_axis'], 
            'acceleration_z' => $movementData['z_axis'],
            'door_state' => $doorStateResult->isOpen(),
            'door_state_certainty' => $doorStateResult->certainty,
            'needs_confirmation' => $doorStateResult->needsConfirmation,
            'movement_duration' => $movementData['move_duration'] ?? null,
            'movement_number' => $movementData['move_number'] ?? null,
            'created_at' => now(),
        ]);

        $sensorData->save();
    }

    private function checkAndDispatchStateChange(Sensor $sensor, $doorStateResult): void
    {
        $previousState = Cache::get("door_state:{$sensor->id}");
        $currentState = $doorStateResult->state;

        if ($previousState !== $currentState) {
            // State changed - dispatch event
            DoorStateChanged::dispatch($sensor, $doorStateResult, $previousState);
            
            Cache::put("door_state:{$sensor->id}", $currentState, 86400); // 24 hours
            
            Log::info("Door state changed", [
                'sensor_id' => $sensor->id,
                'previous_state' => $previousState,
                'current_state' => $currentState,
                'certainty' => $doorStateResult->certainty,
                'confidence' => $doorStateResult->confidence,
            ]);
        }
    }

    private function findOrCreateSensor(int $sourceAddress): ?Sensor
    {
        $sensor = Cache::remember(
            "sensor:address:{$sourceAddress}",
            3600, // 1 hour
            fn() => Sensor::where('wirepas_address', $sourceAddress)->first()
        );

        if (!$sensor) {
            Log::warning("Unknown sensor address", [
                'source_address' => $sourceAddress,
                'message' => 'Sensor not found in database'
            ]);
        }

        return $sensor;
    }
}