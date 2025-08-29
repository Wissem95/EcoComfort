<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Services\CalibrationService;
use App\Events\CalibrationStarted;
use App\Events\CalibrationCompleted;
use App\Events\CalibrationFailed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CalibrationController extends Controller
{
    private CalibrationService $calibrationService;

    public function __construct(CalibrationService $calibrationService)
    {
        $this->calibrationService = $calibrationService;
    }

    /**
     * Get calibration status for a sensor
     * GET /api/sensors/{sensor_id}/calibration
     */
    public function getCalibration(string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        $calibrationInfo = $this->calibrationService->getCalibrationInfo($sensor);
        
        return response()->json([
            'success' => true,
            'sensor_id' => $sensorId,
            ...$calibrationInfo
        ]);
    }

    /**
     * Calibrate door position for a sensor
     * POST /api/sensors/{sensor_id}/calibrate/door
     */
    public function calibrateDoor(Request $request, string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'type' => 'required|string|in:closed_position',
                'confirm' => 'required|boolean|accepted',
                'override_existing' => 'sometimes|boolean'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Données de requête invalides',
                'details' => $e->errors()
            ], 400);
        }

        // Check if already calibrated and override not allowed
        $existingCalibration = $sensor->calibration_data['door_position'] ?? null;
        if ($existingCalibration && !($validated['override_existing'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => 'CALIBRATION_EXISTS',
                'message' => 'Capteur déjà calibré. Utilisez override_existing=true pour remplacer.',
                'existing_calibration' => $existingCalibration
            ], 400);
        }

        // Broadcast calibration started
        $currentPosition = $this->calibrationService->getCurrentPosition($sensor);
        if ($currentPosition) {
            broadcast(new CalibrationStarted($sensorId, $currentPosition));
        }

        $result = $this->calibrationService->calibrateDoorPosition($sensor, $validated);

        if (!$result['success']) {
            // Broadcast calibration failed
            broadcast(new CalibrationFailed(
                $sensorId,
                $result['error'],
                $result['message']
            ));
            $statusCode = match ($result['error'] ?? '') {
                'NO_RECENT_DATA' => 400,
                'INVALID_POSITION' => 400,
                'UNSTABLE_DATA' => 400,
                default => 500
            };
            
            return response()->json($result, $statusCode);
        }

        // Broadcast calibration completed
        broadcast(new CalibrationCompleted($sensorId, $result['calibration']));

        return response()->json($result);
    }

    /**
     * Check sensor stability for calibration
     * GET /api/sensors/{sensor_id}/stability
     */
    public function getStability(string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        // For calibration purposes, check if we have any stable position available
        $currentPosition = $this->calibrationService->getCurrentPosition($sensor, true); // forCalibration = true
        if (!$currentPosition) {
            return response()->json([
                'stable' => false,
                'reason' => 'Aucune donnée de position stable disponible pour la calibration',
                'current_values' => null,
                'ready_for_calibration' => false
            ]);
        }

        $stabilityResult = $this->calibrationService->checkPositionStability($sensor, true); // forCalibration = true
        
        return response()->json([
            'stable' => $stabilityResult['stable'],
            'current_values' => $currentPosition,
            'stability_metrics' => [
                'variance_x' => $stabilityResult['variance_x'] ?? 0.0,
                'variance_y' => $stabilityResult['variance_y'] ?? 0.0,
                'variance_z' => $stabilityResult['variance_z'] ?? 0.0,
                'overall_stability' => $stabilityResult['overall_stability'] ?? 0.0,
                'sample_count' => $stabilityResult['sample_count'] ?? 0,
                'observation_period' => $stabilityResult['observation_period'] ?? 30
            ],
            'ready_for_calibration' => $stabilityResult['stable']
        ]);
    }

    /**
     * Get calibration history for a sensor
     * GET /api/sensors/{sensor_id}/calibration/history
     */
    public function getHistory(Request $request, string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        $limit = min(50, max(1, $request->get('limit', 10)));
        $calibrationData = $sensor->calibration_data ?? [];
        $history = $calibrationData['history'] ?? [];

        // Filter by date range if provided
        if ($request->has('from') || $request->has('to')) {
            $from = $request->get('from') ? \Carbon\Carbon::parse($request->get('from')) : null;
            $to = $request->get('to') ? \Carbon\Carbon::parse($request->get('to')) : null;

            $history = array_filter($history, function ($entry) use ($from, $to) {
                $timestamp = \Carbon\Carbon::parse($entry['timestamp']);
                if ($from && $timestamp->lt($from)) return false;
                if ($to && $timestamp->gt($to)) return false;
                return true;
            });
        }

        // Sort by timestamp descending and limit
        usort($history, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        
        $history = array_slice($history, 0, $limit);

        // Add user information if available
        $enrichedHistory = array_map(function ($entry, $index) {
            $result = $entry['result'] ?? [];
            return [
                'id' => $index + 1,
                'type' => $entry['type'],
                'closed_reference' => $result['calibration']['closed_reference'] ?? null,
                'confidence' => $result['calibration']['confidence'] ?? 0.0,
                'calibrated_at' => $entry['timestamp'],
                'calibrated_by' => [
                    'id' => auth()->id() ?? 1,
                    'name' => auth()->user()->name ?? 'System'
                ],
                'replaced_previous' => $result['previous_calibration']['exists'] ?? false
            ];
        }, $history, array_keys($history));

        return response()->json([
            'success' => true,
            'sensor_id' => $sensorId,
            'history' => $enrichedHistory,
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 1,
                'total_records' => count($enrichedHistory)
            ]
        ]);
    }

    /**
     * Reset calibration for a sensor
     * DELETE /api/sensors/{sensor_id}/calibration
     */
    public function resetCalibration(Request $request, string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        $reason = $request->get('reason', 'Réinitialisation manuelle');
        $result = $this->calibrationService->resetCalibration($sensor, $reason);

        return response()->json($result);
    }

    /**
     * Validate current position against calibration
     * POST /api/sensors/{sensor_id}/validate-position
     */
    public function validatePosition(string $sensorId): JsonResponse
    {
        $sensor = Sensor::find($sensorId);
        if (!$sensor) {
            return response()->json([
                'success' => false,
                'error' => 'SENSOR_NOT_FOUND',
                'message' => 'Capteur non trouvé'
            ], 404);
        }

        $result = $this->calibrationService->validatePosition($sensor);
        
        return response()->json($result);
    }

    /**
     * Get calibration metrics for admin dashboard
     * GET /api/admin/calibration/metrics
     */
    public function getMetrics(): JsonResponse
    {
        // Ensure user has admin permissions
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'error' => 'INSUFFICIENT_PERMISSIONS',
                'message' => 'Permissions administrateur requises'
            ], 403);
        }

        $totalSensors = Sensor::count();
        $calibratedSensors = Sensor::whereNotNull('calibration_data->door_position')
            ->count();

        $avgConfidence = Sensor::whereNotNull('calibration_data->door_position->data_stability')
            ->avg('calibration_data->door_position->data_stability') ?? 0.0;

        $threshold = 0.90;
        $aboveThreshold = Sensor::whereRaw(
            "CAST(calibration_data->'door_position'->>'data_stability' AS FLOAT) >= ?",
            [$threshold]
        )->count();

        // Today's calibrations (simplified)
        $today = now()->startOfDay();
        $calibrationsToday = Sensor::whereRaw(
            "calibration_data->'door_position'->>'calibrated_at' >= ?",
            [$today->toISOString()]
        )->count();

        // This week's calibrations
        $thisWeek = now()->startOfWeek();
        $calibrationsThisWeek = Sensor::whereRaw(
            "calibration_data->'door_position'->>'calibrated_at' >= ?",
            [$thisWeek->toISOString()]
        )->count();

        return response()->json([
            'success' => true,
            'metrics' => [
                'calibrated_sensors' => [
                    'total' => $totalSensors,
                    'calibrated' => $calibratedSensors,
                    'percentage' => $totalSensors > 0 ? round($calibratedSensors / $totalSensors, 2) : 0.0
                ],
                'accuracy' => [
                    'average_confidence' => round($avgConfidence, 2),
                    'above_threshold_count' => $aboveThreshold,
                    'threshold' => $threshold
                ],
                'activity' => [
                    'calibrations_today' => $calibrationsToday,
                    'calibrations_this_week' => $calibrationsThisWeek,
                    'recalibrations_needed' => 0 // TODO: Implement logic to detect needed recalibrations
                ]
            ],
            'generated_at' => now()->toISOString()
        ]);
    }
}