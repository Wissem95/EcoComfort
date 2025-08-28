<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\SensorData;
use App\Models\Room;
use App\Services\DoorDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SensorController extends Controller
{
    public function __construct(
        private DoorDetectionService $doorDetectionService
    ) {}

    /**
     * Get all sensors for organization
     */
    public function index(Request $request)
    {
        $organization = $request->user()->organization;
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 20), 100);
        $roomId = $request->get('room_id');
        $status = $request->get('status'); // active, inactive, offline

        $query = Sensor::with(['room.building'])
            ->whereIn('room_id', 
                Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
            );

        if ($roomId) {
            $query->where('room_id', $roomId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'offline') {
            $query->where('is_active', true)
                  ->where(function ($q) {
                      $q->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', now()->subMinutes(10));
                  });
        }

        $sensors = $query->paginate($limit, ['*'], 'page', $page);

        $sensorData = $sensors->getCollection()->map(function ($sensor) {
            return [
                'id' => $sensor->id,
                'name' => $sensor->name,
                'mac_address' => $sensor->mac_address,
                'position' => $sensor->position,
                'battery_level' => $sensor->battery_level,
                'is_active' => $sensor->is_active,
                'is_online' => $sensor->isOnline(),
                'last_seen_at' => $sensor->last_seen_at?->toISOString(),
                'room' => [
                    'id' => $sensor->room->id,
                    'name' => $sensor->room->name,
                    'building_name' => $sensor->room->building->name,
                    'floor' => $sensor->room->floor,
                ],
                'calibration_data' => $sensor->calibration_data,
                'created_at' => $sensor->created_at->toISOString(),
            ];
        });

        return response()->json([
            'sensors' => $sensorData,
            'pagination' => [
                'current_page' => $sensors->currentPage(),
                'last_page' => $sensors->lastPage(),
                'per_page' => $sensors->perPage(),
                'total' => $sensors->total(),
            ],
        ]);
    }

    /**
     * Create new sensor
     */
    public function store(Request $request)
    {
        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'room_id' => ['required', 'uuid', Rule::exists('rooms', 'id')->where(function ($query) use ($organization) {
                $query->whereIn('building_id', $organization->buildings->pluck('id'));
            })],
            'name' => 'required|string|max:255',
            'mac_address' => 'required|string|size:17|unique:sensors,mac_address',
            'position' => 'required|in:door,window,wall,ceiling,floor',
            'calibration_data' => 'sometimes|array',
        ]);

        $sensor = Sensor::create($validated);

        return response()->json([
            'message' => 'Sensor created successfully',
            'sensor' => $sensor->load('room.building'),
        ], 201);
    }

    /**
     * Get sensor details
     */
    public function show(Request $request, string $sensorId)
    {
        $sensor = Sensor::with(['room.building', 'latestData'])->findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sensorData = [
            'sensor' => [
                'id' => $sensor->id,
                'name' => $sensor->name,
                'mac_address' => $sensor->mac_address,
                'position' => $sensor->position,
                'battery_level' => $sensor->battery_level,
                'is_active' => $sensor->is_active,
                'is_online' => $sensor->isOnline(),
                'last_seen_at' => $sensor->last_seen_at?->toISOString(),
                'calibration_data' => $sensor->calibration_data,
                'created_at' => $sensor->created_at->toISOString(),
                'room' => [
                    'id' => $sensor->room->id,
                    'name' => $sensor->room->name,
                    'building_name' => $sensor->room->building->name,
                    'floor' => $sensor->room->floor,
                    'type' => $sensor->room->type,
                ],
                'latest_data' => $sensor->latestData ? [
                    'timestamp' => $sensor->latestData->timestamp->toISOString(),
                    'temperature' => $sensor->latestData->temperature,
                    'humidity' => $sensor->latestData->humidity,
                    'acceleration_x' => $sensor->latestData->acceleration_x,
                    'acceleration_y' => $sensor->latestData->acceleration_y,
                    'acceleration_z' => $sensor->latestData->acceleration_z,
                    'door_state' => $sensor->latestData->door_state,
                    'energy_loss_watts' => $sensor->latestData->energy_loss_watts,
                ] : null,
            ],
            'detection_accuracy' => $this->doorDetectionService->getAccuracyMetrics($sensor->id),
            'statistics' => $this->getSensorStatistics($sensor),
        ];

        return response()->json($sensorData);
    }

    /**
     * Update sensor
     */
    public function update(Request $request, string $sensorId)
    {
        $sensor = Sensor::findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'position' => 'sometimes|in:door,window,wall,ceiling,floor',
            'is_active' => 'sometimes|boolean',
            'calibration_data' => 'sometimes|array',
        ]);

        $sensor->update($validated);

        return response()->json([
            'message' => 'Sensor updated successfully',
            'sensor' => $sensor->fresh()->load('room.building'),
        ]);
    }

    /**
     * Delete sensor
     */
    public function destroy(Request $request, string $sensorId)
    {
        $sensor = Sensor::findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sensor->delete();

        return response()->json([
            'message' => 'Sensor deleted successfully',
        ]);
    }

    /**
     * Get sensor historical data
     */
    public function history(Request $request, string $sensorId)
    {
        $sensor = Sensor::findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'interval' => 'sometimes|in:1m,5m,15m,1h,6h,1d',
            'metrics' => 'sometimes|array',
            'metrics.*' => 'in:temperature,humidity,door_state,energy_loss_watts',
        ]);

        $startDate = $validated['start_date'] ?? now()->subDay();
        $endDate = $validated['end_date'] ?? now();
        $interval = $validated['interval'] ?? '15m';
        $metrics = $validated['metrics'] ?? ['temperature', 'humidity', 'door_state', 'energy_loss_watts'];

        $cacheKey = "sensor_history_{$sensorId}_" . md5(json_encode($validated));
        
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($sensor, $startDate, $endDate, $interval, $metrics) {
            // Convert interval to PostgreSQL format
            $pgInterval = match($interval) {
                '1m' => '1 minute',
                '5m' => '5 minutes',
                '15m' => '15 minutes',
                '1h' => '1 hour',
                '6h' => '6 hours',
                '1d' => '1 day',
            };

            // Build select clauses for requested metrics
            $selects = [
                'time_bucket(\'' . $pgInterval . '\', timestamp) as time_bucket',
            ];

            foreach ($metrics as $metric) {
                if (in_array($metric, ['temperature', 'humidity', 'energy_loss_watts'])) {
                    $selects[] = "AVG({$metric}) as avg_{$metric}";
                    $selects[] = "MIN({$metric}) as min_{$metric}";
                    $selects[] = "MAX({$metric}) as max_{$metric}";
                } elseif ($metric === 'door_state') {
                    $selects[] = "bool_or(door_state) as door_open_in_period";
                    $selects[] = "COUNT(CASE WHEN door_state = true THEN 1 END) as door_open_count";
                }
            }

            return DB::table('sensor_data')
                ->select($selects)
                ->where('sensor_id', $sensor->id)
                ->whereBetween('timestamp', [$startDate, $endDate])
                ->groupBy('time_bucket')
                ->orderBy('time_bucket')
                ->get()
                ->map(function ($row) {
                    $data = ['timestamp' => $row->time_bucket];
                    
                    foreach ($row as $key => $value) {
                        if ($key !== 'time_bucket') {
                            $data[$key] = $value;
                        }
                    }
                    
                    return $data;
                });
        });

        return response()->json([
            'sensor_id' => $sensorId,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'interval' => $interval,
            ],
            'metrics' => $metrics,
            'data' => $data,
        ]);
    }

    /**
     * Calibrate sensor
     */
    public function calibrate(Request $request, string $sensorId)
    {
        $sensor = Sensor::findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'temperature_offset' => 'sometimes|numeric|between:-10,10',
            'temperature_multiplier' => 'sometimes|numeric|between:0.5,2.0',
            'humidity_offset' => 'sometimes|numeric|between:-20,20',
            'humidity_multiplier' => 'sometimes|numeric|between:0.5,2.0',
        ]);

        $calibrationData = array_merge($sensor->calibration_data ?? [], $validated);
        $sensor->update(['calibration_data' => $calibrationData]);

        return response()->json([
            'message' => 'Sensor calibrated successfully',
            'calibration_data' => $calibrationData,
        ]);
    }

    /**
     * Reset door detection for sensor
     */
    public function resetDetection(Request $request, string $sensorId)
    {
        $sensor = Sensor::findOrFail($sensorId);
        
        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($sensor->room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->doorDetectionService->resetSensorState($sensorId);

        return response()->json([
            'message' => 'Door detection state reset successfully',
        ]);
    }

    /**
     * Get sensor statistics
     */
    private function getSensorStatistics(Sensor $sensor): array
    {
        $cacheKey = "sensor_stats_{$sensor->id}";
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($sensor) {
            $stats = [
                'total_data_points' => $sensor->sensorData()->count(),
                'data_points_24h' => $sensor->sensorData()
                    ->where('timestamp', '>=', now()->subDay())
                    ->count(),
                'avg_temperature_24h' => $sensor->sensorData()
                    ->where('timestamp', '>=', now()->subDay())
                    ->whereNotNull('temperature')
                    ->avg('temperature'),
                'avg_humidity_24h' => $sensor->sensorData()
                    ->where('timestamp', '>=', now()->subDay())
                    ->whereNotNull('humidity')
                    ->avg('humidity'),
                'door_open_events_24h' => $sensor->sensorData()
                    ->where('timestamp', '>=', now()->subDay())
                    ->where('door_state', true)
                    ->count(),
                'total_energy_loss_24h' => $sensor->sensorData()
                    ->where('timestamp', '>=', now()->subDay())
                    ->sum('energy_loss_watts'),
                'uptime_percentage' => $this->calculateSensorUptime($sensor),
            ];

            // Round numeric values
            foreach ($stats as $key => $value) {
                if (is_numeric($value)) {
                    $stats[$key] = round($value, 2);
                }
            }

            return $stats;
        });
    }

    /**
     * Calculate sensor uptime percentage
     */
    private function calculateSensorUptime(Sensor $sensor): float
    {
        $hours24 = 24 * 60; // 24 hours in minutes
        $expectedDataPoints = $hours24 / 1; // Expecting data every minute
        
        $actualDataPoints = $sensor->sensorData()
            ->where('timestamp', '>=', now()->subDay())
            ->count();
        
        return min(100, ($actualDataPoints / $expectedDataPoints) * 100);
    }
}