<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Room;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Get events/alerts with filtering
     */
    public function index(Request $request)
    {
        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'severity' => 'sometimes|in:info,warning,critical',
            'type' => 'sometimes|string',
            'acknowledged' => 'sometimes|boolean',
            'room_id' => 'sometimes|uuid',
            'sensor_id' => 'sometimes|uuid',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        $query = Event::with(['sensor', 'room.building', 'acknowledgedBy'])
            ->whereIn('room_id', 
                Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id')
            )
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['acknowledged'])) {
            $query->where('acknowledged', $validated['acknowledged']);
        }

        if (isset($validated['room_id'])) {
            $query->where('room_id', $validated['room_id']);
        }

        if (isset($validated['sensor_id'])) {
            $query->where('sensor_id', $validated['sensor_id']);
        }

        if (isset($validated['start_date'])) {
            $query->where('created_at', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->where('created_at', '<=', $validated['end_date']);
        }

        $events = $query->paginate($limit, ['*'], 'page', $page);

        $eventData = $events->getCollection()->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->type,
                'severity' => $event->severity,
                'message' => $event->message,
                'cost_impact' => $event->cost_impact,
                'acknowledged' => $event->acknowledged,
                'acknowledged_at' => $event->acknowledged_at?->toISOString(),
                'acknowledged_by' => $event->acknowledgedBy ? [
                    'id' => $event->acknowledgedBy->id,
                    'name' => $event->acknowledgedBy->name,
                ] : null,
                'data' => $event->data,
                'sensor' => [
                    'id' => $event->sensor->id,
                    'name' => $event->sensor->name,
                    'position' => $event->sensor->position,
                ],
                'room' => [
                    'id' => $event->room->id,
                    'name' => $event->room->name,
                    'building_name' => $event->room->building->name,
                    'floor' => $event->room->floor,
                ],
                'created_at' => $event->created_at->toISOString(),
            ];
        });

        return response()->json([
            'events' => $eventData,
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'statistics' => $this->getEventStatistics($organization),
        ]);
    }

    /**
     * Get event details
     */
    public function show(Request $request, string $eventId)
    {
        $event = Event::with(['sensor', 'room.building', 'acknowledgedBy'])->findOrFail($eventId);
        
        // Verify access
        $organization = $request->user()->organization;
        $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');
        
        if (!$roomIds->contains($event->room_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $eventData = [
            'id' => $event->id,
            'type' => $event->type,
            'severity' => $event->severity,
            'message' => $event->message,
            'cost_impact' => $event->cost_impact,
            'acknowledged' => $event->acknowledged,
            'acknowledged_at' => $event->acknowledged_at?->toISOString(),
            'acknowledged_by' => $event->acknowledgedBy ? [
                'id' => $event->acknowledgedBy->id,
                'name' => $event->acknowledgedBy->name,
                'email' => $event->acknowledgedBy->email,
            ] : null,
            'data' => $event->data,
            'sensor' => [
                'id' => $event->sensor->id,
                'name' => $event->sensor->name,
                'mac_address' => $event->sensor->mac_address,
                'position' => $event->sensor->position,
                'battery_level' => $event->sensor->battery_level,
                'is_active' => $event->sensor->is_active,
            ],
            'room' => [
                'id' => $event->room->id,
                'name' => $event->room->name,
                'type' => $event->room->type,
                'floor' => $event->room->floor,
                'surface_m2' => $event->room->surface_m2,
                'building' => [
                    'id' => $event->room->building->id,
                    'name' => $event->room->building->name,
                ],
            ],
            'created_at' => $event->created_at->toISOString(),
            'updated_at' => $event->updated_at->toISOString(),
        ];

        return response()->json($eventData);
    }

    /**
     * Acknowledge an event
     */
    public function acknowledge(Request $request, string $eventId)
    {
        $event = Event::findOrFail($eventId);
        
        // Verify access
        $organization = $request->user()->organization;
        $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');
        
        if (!$roomIds->contains($event->room_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($event->acknowledged) {
            return response()->json(['message' => 'Event already acknowledged'], 400);
        }

        $event->acknowledge($request->user());

        return response()->json([
            'message' => 'Event acknowledged successfully',
            'event' => [
                'id' => $event->id,
                'acknowledged' => $event->acknowledged,
                'acknowledged_at' => $event->acknowledged_at->toISOString(),
                'acknowledged_by' => [
                    'id' => $event->acknowledgedBy->id,
                    'name' => $event->acknowledgedBy->name,
                ],
            ],
        ]);
    }

    /**
     * Bulk acknowledge events
     */
    public function bulkAcknowledge(Request $request)
    {
        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:100',
            'event_ids.*' => 'uuid|exists:events,id',
        ]);

        $organization = $request->user()->organization;
        $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');

        $events = Event::whereIn('id', $validated['event_ids'])
            ->whereIn('room_id', $roomIds)
            ->where('acknowledged', false)
            ->get();

        $acknowledgedCount = 0;
        $totalPoints = 0;

        foreach ($events as $event) {
            $event->acknowledge($request->user());
            $acknowledgedCount++;
            
            // Points are awarded in the Event model's acknowledge method
            $points = match($event->severity) {
                'critical' => 10,
                'warning' => 5,
                'info' => 2,
                default => 1,
            };
            $totalPoints += $points;
        }

        return response()->json([
            'message' => "Successfully acknowledged {$acknowledgedCount} events",
            'acknowledged_count' => $acknowledgedCount,
            'points_earned' => $totalPoints,
        ]);
    }

    /**
     * Get event statistics and analytics
     */
    public function statistics(Request $request)
    {
        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'period' => 'sometimes|in:24h,7d,30d,90d',
            'group_by' => 'sometimes|in:hour,day,week,month',
        ]);

        $period = $validated['period'] ?? '24h';
        $groupBy = $validated['group_by'] ?? 'hour';

        $cacheKey = "event_statistics_{$organization->id}_{$period}_{$groupBy}";
        
        $statistics = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($organization, $period, $groupBy) {
            $startDate = match($period) {
                '24h' => now()->subDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
            };

            $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');
            
            // Basic statistics
            $basicStats = Event::whereIn('room_id', $roomIds)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('
                    COUNT(*) as total_events,
                    SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN severity = "warning" THEN 1 ELSE 0 END) as warning_count,
                    SUM(CASE WHEN severity = "info" THEN 1 ELSE 0 END) as info_count,
                    SUM(CASE WHEN acknowledged = true THEN 1 ELSE 0 END) as acknowledged_count,
                    SUM(COALESCE(cost_impact, 0)) as total_cost_impact,
                    AVG(COALESCE(cost_impact, 0)) as avg_cost_impact
                ')
                ->first();

            // Trends over time
            $timeFormat = match($groupBy) {
                'hour' => '%Y-%m-%d %H:00:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
            };

            $trends = Event::whereIn('room_id', $roomIds)
                ->where('created_at', '>=', $startDate)
                ->selectRaw("
                    DATE_FORMAT(created_at, '{$timeFormat}') as period,
                    COUNT(*) as count,
                    severity,
                    SUM(COALESCE(cost_impact, 0)) as cost_impact
                ")
                ->groupBy('period', 'severity')
                ->orderBy('period')
                ->get()
                ->groupBy('period')
                ->map(function ($periodEvents) {
                    return [
                        'total' => $periodEvents->sum('count'),
                        'critical' => $periodEvents->where('severity', 'critical')->sum('count'),
                        'warning' => $periodEvents->where('severity', 'warning')->sum('count'),
                        'info' => $periodEvents->where('severity', 'info')->sum('count'),
                        'cost_impact' => $periodEvents->sum('cost_impact'),
                    ];
                });

            // Events by type
            $eventsByType = Event::whereIn('room_id', $roomIds)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('
                    type,
                    COUNT(*) as count,
                    SUM(COALESCE(cost_impact, 0)) as cost_impact,
                    AVG(CASE WHEN acknowledged = true THEN 1 ELSE 0 END) * 100 as acknowledgment_rate
                ')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();

            // Events by room
            $eventsByRoom = Event::with('room')
                ->whereIn('room_id', $roomIds)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('
                    room_id,
                    COUNT(*) as count,
                    SUM(COALESCE(cost_impact, 0)) as cost_impact
                ')
                ->groupBy('room_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($event) {
                    return [
                        'room_id' => $event->room_id,
                        'room_name' => $event->room->name,
                        'building_name' => $event->room->building->name,
                        'count' => $event->count,
                        'cost_impact' => $event->cost_impact,
                    ];
                });

            // Response times (time to acknowledgment)
            $responseTimes = Event::whereIn('room_id', $roomIds)
                ->where('created_at', '>=', $startDate)
                ->where('acknowledged', true)
                ->selectRaw('
                    AVG(TIMESTAMPDIFF(SECOND, created_at, acknowledged_at)) as avg_response_seconds,
                    MIN(TIMESTAMPDIFF(SECOND, created_at, acknowledged_at)) as min_response_seconds,
                    MAX(TIMESTAMPDIFF(SECOND, created_at, acknowledged_at)) as max_response_seconds
                ')
                ->first();

            return [
                'period' => $period,
                'basic_stats' => [
                    'total_events' => $basicStats->total_events ?? 0,
                    'critical_count' => $basicStats->critical_count ?? 0,
                    'warning_count' => $basicStats->warning_count ?? 0,
                    'info_count' => $basicStats->info_count ?? 0,
                    'acknowledged_count' => $basicStats->acknowledged_count ?? 0,
                    'acknowledgment_rate' => $basicStats->total_events > 0 
                        ? round(($basicStats->acknowledged_count / $basicStats->total_events) * 100, 1)
                        : 0,
                    'total_cost_impact' => round($basicStats->total_cost_impact ?? 0, 2),
                    'avg_cost_impact' => round($basicStats->avg_cost_impact ?? 0, 2),
                ],
                'trends' => $trends,
                'events_by_type' => $eventsByType,
                'events_by_room' => $eventsByRoom,
                'response_times' => [
                    'avg_seconds' => round($responseTimes->avg_response_seconds ?? 0),
                    'min_seconds' => $responseTimes->min_response_seconds ?? 0,
                    'max_seconds' => $responseTimes->max_response_seconds ?? 0,
                    'avg_minutes' => round(($responseTimes->avg_response_seconds ?? 0) / 60, 1),
                ],
            ];
        });

        return response()->json($statistics);
    }

    /**
     * Get event types and their descriptions
     */
    public function types()
    {
        $eventTypes = [
            'door_open' => 'Door opened - potential energy loss',
            'window_open' => 'Window opened - potential energy loss',
            'temperature_high' => 'Temperature above target range',
            'temperature_low' => 'Temperature below target range',
            'humidity_high' => 'Humidity above target range',
            'humidity_low' => 'Humidity below target range',
            'energy_loss' => 'Significant energy loss detected',
            'battery_low' => 'Sensor battery level is low',
        ];

        $severityLevels = [
            'info' => 'Information - minor deviation or notification',
            'warning' => 'Warning - attention required',
            'critical' => 'Critical - immediate action required',
        ];

        return response()->json([
            'event_types' => $eventTypes,
            'severity_levels' => $severityLevels,
        ]);
    }

    /**
     * Get basic event statistics for organization
     */
    private function getEventStatistics($organization): array
    {
        $roomIds = Room::whereIn('building_id', $organization->buildings->pluck('id'))->pluck('id');
        
        return [
            'unacknowledged_count' => Event::whereIn('room_id', $roomIds)
                ->where('acknowledged', false)->count(),
            'critical_count' => Event::whereIn('room_id', $roomIds)
                ->where('severity', 'critical')
                ->where('acknowledged', false)->count(),
            'last_24h_count' => Event::whereIn('room_id', $roomIds)
                ->where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}