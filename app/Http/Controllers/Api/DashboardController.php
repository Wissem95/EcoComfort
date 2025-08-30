<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnergyCalculatorService;
use App\Services\GamificationService;
use App\Services\NotificationService;
use App\Models\Organization;
use App\Models\Building;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\Event;
use App\Models\SensorData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private EnergyCalculatorService $energyCalculatorService,
        private GamificationService $gamificationService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get organization overview
     */
    public function overview(Request $request)
    {
        // For testing without auth, use first organization if user is null
        $user = $request->user();
        if (!$user) {
            $orgCount = \App\Models\Organization::count();
            $organization = \App\Models\Organization::first();
            if (!$organization) {
                return response()->json([
                    'error' => 'No organization found for testing', 
                    'debug' => ['org_count' => $orgCount, 'db_connection' => \DB::connection()->getDatabaseName()]
                ], 404);
            }
        } else {
            $organization = $user->organization;
            if (!$organization) {
                return response()->json(['error' => 'User has no associated organization'], 400);
            }
        }
        
        $overview = Cache::remember(
            "dashboard_overview_{$organization->id}",
            now()->addMinutes(5),
            function () use ($organization) {
                // Basic statistics
                $totalBuildings = $organization->buildings()->count();
                $totalRooms = Room::whereIn('building_id', $organization->buildings->pluck('id'))->count();
                $totalSensors = Sensor::whereIn('room_id', 
                    Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
                )->count();
                $activeSensors = Sensor::whereIn('room_id', 
                    Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
                )->where('is_active', true)->count();

                // Energy statistics (last 24h)
                $rooms = Room::whereIn('building_id', $organization->buildings->pluck('id'))->get();
                $energyStats = [
                    'total_energy_loss_kwh' => 0,
                    'total_cost' => 0,
                    'rooms_with_open_doors' => 0,
                ];

                foreach ($rooms as $room) {
                    $roomEnergyLoss = $this->energyCalculatorService->calculateCumulativeEnergyLoss($room, 24);
                    $energyStats['total_energy_loss_kwh'] += $roomEnergyLoss['total_energy_loss_kwh'];
                    $energyStats['total_cost'] += $roomEnergyLoss['estimated_cost'];
                    
                    if ($room->hasOpenDoor()) {
                        $energyStats['rooms_with_open_doors']++;
                    }
                }

                // Alert statistics
                $alertStats = $this->notificationService->getAlertStatistics(24);

                return [
                    'organization' => [
                        'name' => $organization->name,
                        'surface_m2' => $organization->surface_m2,
                        'target_percent' => $organization->target_percent,
                    ],
                    'infrastructure' => [
                        'total_buildings' => $totalBuildings,
                        'total_rooms' => $totalRooms,
                        'total_sensors' => $totalSensors,
                        'active_sensors' => $activeSensors,
                        'sensor_uptime' => $totalSensors > 0 ? round(($activeSensors / $totalSensors) * 100, 1) : 0,
                    ],
                    'energy' => $energyStats,
                    'alerts' => $alertStats,
                ];
            }
        );

        return response()->json($overview);
    }

    /**
     * Get real-time sensor data
     */
    public function sensorData(Request $request)
    {
        // For testing without auth, use first organization if user is null
        $user = $request->user();
        if (!$user) {
            $organization = \App\Models\Organization::first();
            if (!$organization) {
                return response()->json([
                    'error' => 'No organization found for testing',
                    'debug' => ['org_count' => \App\Models\Organization::count()]
                ], 404);
            }
        } else {
            $organization = $user->organization;
            if (!$organization) {
                return response()->json(['error' => 'User has no associated organization'], 400);
            }
        }
        
        $sensorData = Cache::remember(
            "sensor_data_{$organization->id}",
            now()->addMinutes(1),
            function () use ($organization) {
                $sensors = Sensor::with(['room.building', 'latestData', 'latestUsableData'])
                    ->whereIn('room_id', 
                        Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
                    )
                    ->where('is_active', true)
                    ->get();

                return $sensors->map(function ($sensor) {
                    // Use latest data if available, otherwise fall back to latest usable data
                    $data = $sensor->latestData ?: $sensor->latestUsableData;
                    
                    return [
                        'sensor_id' => $sensor->id,
                        'name' => $sensor->name,
                        'position' => $sensor->position,
                        'room' => [
                            'id' => $sensor->room->id,
                            'name' => $sensor->room->name,
                            'building_name' => $sensor->room->building->name,
                        ],
                        'battery_level' => $sensor->battery_level,
                        'is_online' => $sensor->isOnline(),
                        'has_usable_data' => $sensor->hasUsableData(),
                        'last_seen' => $sensor->last_seen_at?->toISOString(),
                        'data' => $data ? [
                            'timestamp' => $data->timestamp->toISOString(),
                            'temperature' => $data->temperature,
                            'humidity' => $data->humidity,
                            'door_state' => $data->door_state,
                            'energy_loss_watts' => $data->energy_loss_watts,
                        ] : null,
                    ];
                });
            }
        );

        return response()->json(['sensors' => $sensorData]);
    }

    /**
     * Get alerts and notifications
     */
    public function alerts(Request $request)
    {
        // For testing without auth, use first organization if user is null
        $user = $request->user();
        if (!$user) {
            $organization = \App\Models\Organization::first();
            if (!$organization) {
                return response()->json([
                    'error' => 'No organization found for testing',
                    'debug' => ['org_count' => \App\Models\Organization::count()]
                ], 404);
            }
        } else {
            $organization = $user->organization;
            if (!$organization) {
                return response()->json(['error' => 'User has no associated organization'], 400);
            }
        }
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 20), 100);
        $severity = $request->get('severity');
        $acknowledged = $request->get('acknowledged');

        $query = Event::with(['sensor', 'room', 'acknowledgedBy'])
            ->whereIn('room_id', 
                Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
            )
            ->orderBy('created_at', 'desc');

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($acknowledged !== null) {
            $query->where('acknowledged', $acknowledged === 'true');
        }

        $alerts = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'alerts' => $alerts->items(),
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
            'stats' => [
                'unacknowledged' => Event::whereIn('room_id', 
                    Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
                )->where('acknowledged', false)->count(),
                'critical' => Event::whereIn('room_id', 
                    Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
                )->where('severity', 'critical')->where('acknowledged', false)->count(),
            ],
        ]);
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert(Request $request, string $eventId)
    {
        $event = Event::findOrFail($eventId);
        
        // Verify user has access to this alert
        $organization = $request->user()->organization;
        $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');
        
        if (!$roomIds->contains($event->room_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $event->acknowledge($request->user());

        return response()->json([
            'message' => 'Alert acknowledged successfully',
            'event' => $event->fresh(),
        ]);
    }

    /**
     * Get energy efficiency analytics
     */
    public function energyAnalytics(Request $request)
    {
        // For testing without auth, use first organization if user is null
        $user = $request->user();
        if (!$user) {
            $organization = \App\Models\Organization::first();
            if (!$organization) {
                return response()->json([
                    'error' => 'No organization found for testing',
                    'debug' => ['org_count' => \App\Models\Organization::count()]
                ], 404);
            }
        } else {
            $organization = $user->organization;
            if (!$organization) {
                return response()->json(['error' => 'User has no associated organization'], 400);
            }
        }
        $days = min($request->get('days', 7), 30);

        $analytics = Cache::remember(
            "energy_analytics_{$organization->id}_{$days}",
            now()->addMinutes(10),
            function () use ($organization, $days) {
                $rooms = Room::whereIn('building_id', $organization->buildings->pluck('id'))->get();
                $totalAnalysis = [
                    'total_energy_loss_kwh' => 0,
                    'total_cost' => 0,
                    'room_analytics' => [],
                ];

                foreach ($rooms as $room) {
                    $roomAnalysis = $this->energyCalculatorService->calculateCumulativeEnergyLoss($room, $days * 24);
                    $potential = $this->energyCalculatorService->calculatePotentialSavings($room, $days);
                    
                    $totalAnalysis['total_energy_loss_kwh'] += $roomAnalysis['total_energy_loss_kwh'];
                    $totalAnalysis['total_cost'] += $roomAnalysis['estimated_cost'];
                    
                    $totalAnalysis['room_analytics'][] = [
                        'room_id' => $room->id,
                        'room_name' => $room->name,
                        'building_name' => $room->building->name,
                        'energy_loss_kwh' => $roomAnalysis['total_energy_loss_kwh'],
                        'cost' => $roomAnalysis['estimated_cost'],
                        'events_count' => $roomAnalysis['event_count'],
                        'average_duration' => $roomAnalysis['total_duration_hours'] / max(1, $roomAnalysis['event_count']),
                        'efficiency_score' => $this->calculateRoomEfficiencyScore($roomAnalysis, $room->surface_m2),
                        'potential_savings' => $potential,
                    ];
                }

                // Sort rooms by energy loss
                usort($totalAnalysis['room_analytics'], fn($a, $b) => $b['energy_loss_kwh'] <=> $a['energy_loss_kwh']);

                // Calculate organization efficiency
                $targetSavings = $organization->target_percent / 100;
                $actualSavings = $totalAnalysis['total_energy_loss_kwh'] > 0 
                    ? 1 - ($totalAnalysis['total_energy_loss_kwh'] / ($totalAnalysis['total_energy_loss_kwh'] * 1.5))
                    : 0;

                $totalAnalysis['efficiency'] = [
                    'target_percent' => $organization->target_percent,
                    'actual_percent' => round($actualSavings * 100, 1),
                    'goal_achieved' => $actualSavings >= $targetSavings,
                    'improvement_needed' => max(0, ($targetSavings - $actualSavings) * 100),
                ];

                return $totalAnalysis;
            }
        );

        return response()->json($analytics);
    }

    /**
     * Get gamification data for user
     */
    public function gamification(Request $request)
    {
        // For testing without auth, use first user if user is null
        $user = $request->user();
        if (!$user) {
            $user = \App\Models\User::first();
            if (!$user) {
                return response()->json([
                    'error' => 'No user found for testing',
                    'debug' => ['user_count' => \App\Models\User::count()]
                ], 404);
            }
        }
        $organization = $user->organization;

        $gamificationData = [
            'user' => [
                'level' => $this->gamificationService->getUserLevel($user),
                'badges' => $this->gamificationService->getUserBadges($user),
                'achievements' => $this->gamificationService->getUserAchievements($user),
                'total_points' => $user->points,
            ],
            'leaderboard' => [
                'daily' => $this->gamificationService->getLeaderboard($organization, 'daily', 5),
                'weekly' => $this->gamificationService->getLeaderboard($organization, 'weekly', 5),
                'monthly' => $this->gamificationService->getLeaderboard($organization, 'monthly', 10),
            ],
            'challenges' => $this->getActiveChallenges($organization),
        ];

        return response()->json($gamificationData);
    }

    /**
     * Get room details with sensors
     */
    public function roomDetails(Request $request, string $roomId)
    {
        $room = Room::with(['building', 'sensors.latestData', 'events' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail($roomId);

        // Verify access
        $organization = $request->user()->organization;
        if (!$organization->buildings->contains($room->building)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $energyAnalysis = $this->energyCalculatorService->calculateCumulativeEnergyLoss($room, 24);

        $roomData = [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
                'floor' => $room->floor,
                'surface_m2' => $room->surface_m2,
                'target_temperature' => $room->target_temperature,
                'target_humidity' => $room->target_humidity,
                'building' => [
                    'id' => $room->building->id,
                    'name' => $room->building->name,
                ],
            ],
            'sensors' => $room->sensors->map(function ($sensor) {
                return [
                    'id' => $sensor->id,
                    'name' => $sensor->name,
                    'position' => $sensor->position,
                    'battery_level' => $sensor->battery_level,
                    'is_active' => $sensor->is_active,
                    'is_online' => $sensor->isOnline(),
                    'last_seen' => $sensor->last_seen_at?->toISOString(),
                    'latest_data' => $sensor->latestData ? [
                        'timestamp' => $sensor->latestData->timestamp->toISOString(),
                        'temperature' => $sensor->latestData->temperature,
                        'humidity' => $sensor->latestData->humidity,
                        'door_state' => $sensor->latestData->door_state,
                        'energy_loss_watts' => $sensor->latestData->energy_loss_watts,
                    ] : null,
                ];
            }),
            'energy_analysis' => $energyAnalysis,
            'current_status' => [
                'temperature' => $room->latest_temperature,
                'humidity' => $room->latest_humidity,
                'has_open_door' => $room->hasOpenDoor(),
                'has_open_window' => $room->hasOpenWindow(),
            ],
            'recent_events' => $room->events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'severity' => $event->severity,
                    'message' => $event->message,
                    'acknowledged' => $event->acknowledged,
                    'cost_impact' => $event->cost_impact,
                    'created_at' => $event->created_at->toISOString(),
                ];
            }),
        ];

        return response()->json($roomData);
    }

    /**
     * Calculate room efficiency score
     */
    private function calculateRoomEfficiencyScore(array $analysis, float $surfaceM2): int
    {
        $score = 100;
        
        // Deduct points for energy loss per m²
        $lossPerM2 = $analysis['total_energy_loss_kwh'] / max(1, $surfaceM2);
        $score -= min(50, $lossPerM2 * 20);
        
        // Deduct points for frequency
        $score -= min(30, $analysis['event_count'] * 2);
        
        // Deduct points for duration
        $score -= min(20, $analysis['total_duration_hours']);
        
        return max(0, round($score));
    }

    /**
     * Get active challenges for organization
     */
    private function getActiveChallenges(Organization $organization): array
    {
        // This would fetch from cache - simplified implementation
        $challengeIds = Cache::get("org_challenges_{$organization->id}", []);
        $challenges = [];
        
        foreach ($challengeIds as $challengeId) {
            $challenge = Cache::get("challenge_{$challengeId}");
            if ($challenge && $challenge['status'] === 'active') {
                $challenges[] = $challenge;
            }
        }
        
        return $challenges;
    }
}