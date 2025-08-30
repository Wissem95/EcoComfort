<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\SensorData;
use App\Models\SensorEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalibrationService
{
    // Position tolerance for Wirepas sensors (±0.5 units for sensitive detection)
    private const POSITION_TOLERANCE = 0.5;
    
    // Stability requirements
    private const MIN_STABILITY_SAMPLES = 10;
    private const MAX_POSITION_VARIANCE = 1.0;
    
    // Position limits for Wirepas sensors
    private const MIN_POSITION_VALUE = -127;
    private const MAX_POSITION_VALUE = 127;

    /**
     * Calibrate door position for Wirepas sensor
     * Records current X,Y,Z position as "closed" reference
     */
    public function calibrateDoorPosition(Sensor $sensor, array $options = []): array
    {
        Log::info("Starting door position calibration for sensor {$sensor->id}", $options);

        // Get position for calibration (uses last stable position, ignoring time constraints)
        $currentPosition = $this->getCurrentPosition($sensor, true); // forCalibration = true
        if (!$currentPosition) {
            return [
                'success' => false,
                'error' => 'NO_STABLE_DATA',
                'message' => 'Aucune position stable disponible pour la calibration. Assurez-vous que le capteur a au moins une position "stop-moving" enregistrée.',
                'confidence' => 0.0
            ];
        }

        // Log the position being used for calibration
        Log::info("Using position for calibration", [
            'sensor_id' => $sensor->id,
            'position' => $currentPosition
        ]);

        // Validate position values are within expected range
        if (!$this->validatePositionRange($currentPosition)) {
            return [
                'success' => false,
                'error' => 'INVALID_POSITION',
                'message' => 'Valeurs de position hors limites attendues',
                'confidence' => 0.0
            ];
        }

        // Check data stability for calibration
        $stabilityResult = $this->checkPositionStability($sensor, true); // forCalibration = true
        if (!$stabilityResult['stable']) {
            return [
                'success' => false,
                'error' => 'UNSTABLE_DATA',
                'message' => 'Les valeurs du capteur ne sont pas stables',
                'details' => $stabilityResult,
                'confidence' => 0.0
            ];
        }

        // Store calibration data
        $calibrationData = $sensor->calibration_data ?? [];
        $previousCalibration = $calibrationData['door_position'] ?? null;
        
        $calibrationData['door_position'] = [
            'closed_reference' => $currentPosition,
            'tolerance' => self::POSITION_TOLERANCE,
            'calibrated_at' => now()->toISOString(),
            'calibrated_by' => auth()->id(),
            'data_stability' => $stabilityResult['overall_stability']
        ];

        $sensor->update(['calibration_data' => $calibrationData]);

        // Store in history
        $this->storeCalibrationHistory($sensor, 'door_position', [
            'closed_reference' => $currentPosition,
            'confidence' => $stabilityResult['overall_stability'],
            'replaced_previous' => $previousCalibration !== null
        ]);

        // Invalidate cache
        $this->invalidateCalibrationCache($sensor->id);

        Log::info("Door position calibration completed for sensor {$sensor->id}", $currentPosition);

        return [
            'success' => true,
            'message' => 'Capteur calibré avec succès',
            'calibration' => [
                'closed_reference' => $currentPosition,
                'data_stability' => $stabilityResult['overall_stability'],
                'timestamp' => now()->toISOString(),
                'confidence' => $stabilityResult['overall_stability']
            ],
            'previous_calibration' => [
                'exists' => $previousCalibration !== null,
                'previous_position' => $previousCalibration['closed_reference'] ?? null
            ]
        ];
    }

    /**
     * Get last stable position for calibration (ignores time constraints)
     * Looks for the most recent "stop-moving" event without any subsequent "start-moving"
     */
    private function getLastStablePosition(Sensor $sensor): ?array
    {
        // Find the most recent stop-moving event
        $lastStopEvent = SensorEvent::where('sensor_id', $sensor->id)
            ->where('event_type', 'stop-moving')
            ->latest('created_at')
            ->first();

        if (!$lastStopEvent) {
            // Fallback to any sensor data if no movement events exist
            $recentData = SensorData::where('sensor_id', $sensor->id)
                ->whereNotNull(['acceleration_x', 'acceleration_y', 'acceleration_z'])
                ->latest('timestamp')
                ->first();

            if ($recentData) {
                return [
                    'x' => (int)$recentData->acceleration_x,
                    'y' => (int)$recentData->acceleration_y,
                    'z' => (int)$recentData->acceleration_z
                ];
            }
            return null;
        }

        // Check if there's been any movement since the last stop
        $hasMovedSince = SensorEvent::where('sensor_id', $sensor->id)
            ->where('event_type', 'start-moving')
            ->where('created_at', '>', $lastStopEvent->created_at)
            ->exists();

        if (!$hasMovedSince) {
            return [
                'x' => $lastStopEvent->position_x,
                'y' => $lastStopEvent->position_y,
                'z' => $lastStopEvent->position_z
            ];
        }

        return null; // Sensor has moved since last stable position
    }

    /**
     * Get current position from most recent sensor data
     */
    public function getCurrentPosition(Sensor $sensor, bool $forCalibration = false): ?array
    {
        // For calibration, use the last stable position (ignores time constraints)
        if ($forCalibration) {
            return $this->getLastStablePosition($sensor);
        }

        // Normal operation: only use recent data (last 30 seconds)
        // Try to get from recent SensorEvent (Wirepas movement data)
        $recentEvent = SensorEvent::where('sensor_id', $sensor->id)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->latest('created_at')
            ->first();

        if ($recentEvent) {
            return [
                'x' => $recentEvent->position_x,
                'y' => $recentEvent->position_y,
                'z' => $recentEvent->position_z
            ];
        }

        // Fallback to diagnostic data from SensorData
        $recentData = SensorData::where('sensor_id', $sensor->id)
            ->whereNotNull(['acceleration_x', 'acceleration_y', 'acceleration_z'])
            ->where('timestamp', '>=', now()->subSeconds(30))
            ->latest('timestamp')
            ->first();

        if ($recentData) {
            return [
                'x' => (int)$recentData->acceleration_x,
                'y' => (int)$recentData->acceleration_y,
                'z' => (int)$recentData->acceleration_z
            ];
        }

        return null;
    }

    /**
     * Check if sensor position data is stable for calibration
     */
    public function checkPositionStability(Sensor $sensor, bool $forCalibration = false): array
    {
        // For calibration, check if we have a stable position (last stop-moving without start-moving after)
        if ($forCalibration) {
            $stablePosition = $this->getLastStablePosition($sensor);
            
            if ($stablePosition) {
                return [
                    'stable' => true,
                    'variance_x' => 0.0,
                    'variance_y' => 0.0,
                    'variance_z' => 0.0,
                    'overall_stability' => 1.0, // Perfect stability for calibration
                    'sample_count' => 1,
                    'observation_period' => 0, // Not relevant for calibration
                    'reason' => 'Using last stable "stop-moving" position for calibration'
                ];
            } else {
                return [
                    'stable' => false,
                    'reason' => 'No stable position found. Sensor may still be moving or no position data available.',
                    'sample_count' => 0,
                    'overall_stability' => 0.0
                ];
            }
        }

        // Normal stability check for real-time operations (original logic)
        // Get recent position data from events and sensor data
        $recentEvents = SensorEvent::where('sensor_id', $sensor->id)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->orderBy('created_at', 'desc')
            ->limit(self::MIN_STABILITY_SAMPLES)
            ->get();

        $positions = [];
        foreach ($recentEvents as $event) {
            $positions[] = [
                'x' => $event->position_x,
                'y' => $event->position_y,
                'z' => $event->position_z
            ];
        }

        // If not enough events, try sensor data
        if (count($positions) < 3) {
            $recentData = SensorData::where('sensor_id', $sensor->id)
                ->whereNotNull(['acceleration_x', 'acceleration_y', 'acceleration_z'])
                ->where('timestamp', '>=', now()->subSeconds(30))
                ->orderBy('timestamp', 'desc')
                ->limit(self::MIN_STABILITY_SAMPLES)
                ->get();

            foreach ($recentData as $data) {
                $positions[] = [
                    'x' => (int)$data->acceleration_x,
                    'y' => (int)$data->acceleration_y,
                    'z' => (int)$data->acceleration_z
                ];
            }
        }

        if (count($positions) < 3) {
            return [
                'stable' => false,
                'reason' => 'Insufficient data points',
                'sample_count' => count($positions),
                'overall_stability' => 0.0
            ];
        }

        // Calculate variance for each axis
        $variances = $this->calculatePositionVariances($positions);
        
        // Overall stability score (lower variance = higher stability)
        $maxVariance = max($variances['x'], $variances['y'], $variances['z']);
        $overallStability = max(0.0, min(1.0, 1.0 - ($maxVariance / self::MAX_POSITION_VARIANCE)));
        
        $stable = $maxVariance <= self::MAX_POSITION_VARIANCE;

        return [
            'stable' => $stable,
            'variance_x' => $variances['x'],
            'variance_y' => $variances['y'],
            'variance_z' => $variances['z'],
            'overall_stability' => $overallStability,
            'sample_count' => count($positions),
            'observation_period' => 30
        ];
    }

    /**
     * Calculate position variances for stability check
     */
    private function calculatePositionVariances(array $positions): array
    {
        if (count($positions) < 2) {
            return ['x' => 0.0, 'y' => 0.0, 'z' => 0.0];
        }

        // Calculate means
        $means = [
            'x' => array_sum(array_column($positions, 'x')) / count($positions),
            'y' => array_sum(array_column($positions, 'y')) / count($positions),
            'z' => array_sum(array_column($positions, 'z')) / count($positions)
        ];

        // Calculate variances
        $variances = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0];
        
        foreach ($positions as $position) {
            $variances['x'] += pow($position['x'] - $means['x'], 2);
            $variances['y'] += pow($position['y'] - $means['y'], 2);
            $variances['z'] += pow($position['z'] - $means['z'], 2);
        }

        $count = count($positions);
        return [
            'x' => $variances['x'] / $count,
            'y' => $variances['y'] / $count,
            'z' => $variances['z'] / $count
        ];
    }

    /**
     * Validate position values are within Wirepas expected range
     */
    private function validatePositionRange(array $position): bool
    {
        foreach (['x', 'y', 'z'] as $axis) {
            $value = $position[$axis];
            if ($value < self::MIN_POSITION_VALUE || $value > self::MAX_POSITION_VALUE) {
                Log::warning("Position value out of range", [
                    'axis' => $axis,
                    'value' => $value,
                    'min' => self::MIN_POSITION_VALUE,
                    'max' => self::MAX_POSITION_VALUE
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Get cached calibration data for a sensor
     */
    public function getCachedCalibration(string $sensorId): array
    {
        return Cache::remember(
            "sensor_calibration_{$sensorId}",
            3600, // 1 hour
            function () use ($sensorId) {
                $sensor = Sensor::find($sensorId);
                return $sensor ? [
                    'calibration_data' => $sensor->calibration_data ?? [],
                    'door_position' => $sensor->calibration_data['door_position'] ?? null
                ] : [];
            }
        );
    }

    /**
     * Compare current position with calibrated closed position
     * Returns true if door is closed, false if open
     */
    public function isDoorClosed(array $currentPosition, string $sensorId): bool
    {
        $calibration = $this->getCachedCalibration($sensorId);
        $doorCalibration = $calibration['door_position'] ?? null;
        
        if (!$doorCalibration || !isset($doorCalibration['closed_reference'])) {
            return false; // Cannot determine without calibration
        }

        $closedReference = $doorCalibration['closed_reference'];
        $tolerance = $doorCalibration['tolerance'] ?? self::POSITION_TOLERANCE;
        
        return $this->comparePositions($currentPosition, $closedReference, $tolerance);
    }

    /**
     * Compare two positions with tolerance
     */
    private function comparePositions(array $position1, array $position2, int $tolerance = null): bool
    {
        $tolerance = $tolerance ?? self::POSITION_TOLERANCE;
        
        $dx = abs($position1['x'] - $position2['x']);
        $dy = abs($position1['y'] - $position2['y']);
        $dz = abs($position1['z'] - $position2['z']);
        
        return ($dx <= $tolerance && $dy <= $tolerance && $dz <= $tolerance);
    }

    /**
     * Get calibration information for a sensor
     */
    public function getCalibrationInfo(Sensor $sensor): array
    {
        $calibrationData = $sensor->calibration_data ?? [];
        $doorCalibration = $calibrationData['door_position'] ?? null;
        
        if (!$doorCalibration) {
            return [
                'calibrated' => false,
                'current_values' => $this->getCurrentPosition($sensor),
                'message' => 'Capteur non calibré'
            ];
        }
        
        $currentPosition = $this->getCurrentPosition($sensor);
        $isDoorClosed = $currentPosition ? $this->isDoorClosed($currentPosition, $sensor->id) : null;
        
        return [
            'calibrated' => true,
            'calibration' => [
                'door_position' => $doorCalibration,
                'statistics' => $this->getCalibrationStatistics($sensor)
            ],
            'current_state' => [
                'door_status' => $isDoorClosed === null ? 'unknown' : ($isDoorClosed ? 'closed' : 'open'),
                'last_position' => $currentPosition,
                'confidence' => $doorCalibration['data_stability'] ?? 0.0,
                'last_update' => $currentPosition ? now()->toISOString() : null
            ]
        ];
    }

    /**
     * Get calibration statistics for a sensor
     */
    private function getCalibrationStatistics(Sensor $sensor): array
    {
        $calibrationData = $sensor->calibration_data ?? [];
        $history = $calibrationData['history'] ?? [];
        
        return [
            'calibrations_count' => count($history),
            'last_verification' => $calibrationData['door_position']['calibrated_at'] ?? null,
            'accuracy_rate' => $calibrationData['door_position']['data_stability'] ?? 0.0
        ];
    }

    /**
     * Reset calibration for a sensor
     */
    public function resetCalibration(Sensor $sensor, string $reason = null): array
    {
        $calibrationData = $sensor->calibration_data ?? [];
        $previousCalibration = $calibrationData['door_position'] ?? null;
        
        // Remove door position calibration
        unset($calibrationData['door_position']);
        
        // Store in history
        if ($previousCalibration) {
            $this->storeCalibrationHistory($sensor, 'reset', [
                'reason' => $reason,
                'previous_calibration' => $previousCalibration
            ]);
        }
        
        $sensor->update(['calibration_data' => $calibrationData]);
        
        // Invalidate cache
        $this->invalidateCalibrationCache($sensor->id);
        
        return [
            'success' => true,
            'message' => 'Calibrage réinitialisé avec succès',
            'previous_calibration' => $previousCalibration,
            'reset_at' => now()->toISOString()
        ];
    }

    /**
     * Validate current position against calibration
     */
    public function validatePosition(Sensor $sensor): array
    {
        $currentPosition = $this->getCurrentPosition($sensor);
        if (!$currentPosition) {
            return [
                'success' => false,
                'error' => 'No current position available'
            ];
        }

        $calibration = $this->getCachedCalibration($sensor->id);
        $doorCalibration = $calibration['door_position'] ?? null;
        
        if (!$doorCalibration) {
            return [
                'success' => false,
                'error' => 'Sensor not calibrated'
            ];
        }

        $closedReference = $doorCalibration['closed_reference'];
        $tolerance = $doorCalibration['tolerance'] ?? self::POSITION_TOLERANCE;
        
        $isDoorClosed = $this->comparePositions($currentPosition, $closedReference, $tolerance);
        $differences = [
            'x' => $currentPosition['x'] - $closedReference['x'],
            'y' => $currentPosition['y'] - $closedReference['y'],
            'z' => $currentPosition['z'] - $closedReference['z']
        ];

        return [
            'success' => true,
            'validation' => [
                'door_state' => $isDoorClosed ? 'closed' : 'open',
                'confidence' => $doorCalibration['data_stability'] ?? 0.0,
                'position_match' => $isDoorClosed,
                'current_position' => $currentPosition,
                'calibrated_position' => $closedReference,
                'differences' => $differences,
                'within_tolerance' => $isDoorClosed,
                'tolerance_used' => $tolerance
            ]
        ];
    }

    private function storeCalibrationHistory(Sensor $sensor, string $type, array $result): void
    {
        $calibrationData = $sensor->calibration_data ?? [];
        $calibrationData['history'] = $calibrationData['history'] ?? [];
        
        $calibrationData['history'][] = [
            'type' => $type,
            'result' => $result,
            'timestamp' => now()->toISOString()
        ];

        // Keep only last 10 calibrations
        $calibrationData['history'] = array_slice($calibrationData['history'], -10);

        $sensor->update(['calibration_data' => $calibrationData]);
    }

    private function invalidateCalibrationCache(string $sensorId): void
    {
        Cache::forget("sensor_calibration_{$sensorId}");
    }
}