<?php

namespace App\Services;

use App\Events\AlertCreated;
use App\Events\SensorAlert;
use App\Models\Event;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    private GamificationService $gamificationService;
    
    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }
    
    /**
     * Send temperature alert with graduated severity
     */
    public function sendTemperatureAlert(Sensor $sensor, float $temperature, string $alertType): ?Event
    {
        $room = $sensor->room;
        $targetTemp = $room->target_temperature;
        $deviation = abs($temperature - $targetTemp);
        
        // Determine severity based on deviation
        $severity = $this->calculateTemperatureSeverity($deviation);
        
        // Check if we should send alert (avoid spam)
        if (!$this->shouldSendAlert($sensor->id, 'temperature_' . $alertType, $severity)) {
            return null;
        }
        
        // Calculate cost impact
        $costImpact = $this->calculateTemperatureCostImpact($deviation, $room->surface_m2);
        
        $message = $this->generateTemperatureMessage($temperature, $targetTemp, $alertType, $severity);
        
        $event = $this->createEvent($sensor, [
            'type' => "temperature_{$alertType}",
            'severity' => $severity,
            'message' => $message,
            'cost_impact' => $costImpact,
            'data' => [
                'current_temperature' => $temperature,
                'target_temperature' => $targetTemp,
                'deviation' => $deviation,
                'alert_type' => $alertType,
            ],
        ]);
        
        // Broadcast alert
        $this->broadcastAlert($event);
        
        // Send notifications to users
        $this->notifyUsers($event);
        
        return $event;
    }
    
    /**
     * Send humidity alert with graduated severity
     */
    public function sendHumidityAlert(Sensor $sensor, float $humidity, string $alertType): ?Event
    {
        $room = $sensor->room;
        $targetHumidity = $room->target_humidity;
        $deviation = abs($humidity - $targetHumidity);
        
        // Determine severity based on deviation
        $severity = $this->calculateHumiditySeverity($deviation);
        
        // Check if we should send alert (avoid spam)
        if (!$this->shouldSendAlert($sensor->id, 'humidity_' . $alertType, $severity)) {
            return null;
        }
        
        // Calculate cost impact (humidity affects HVAC efficiency)
        $costImpact = $this->calculateHumidityCostImpact($deviation, $room->surface_m2);
        
        $message = $this->generateHumidityMessage($humidity, $targetHumidity, $alertType, $severity);
        
        $event = $this->createEvent($sensor, [
            'type' => "humidity_{$alertType}",
            'severity' => $severity,
            'message' => $message,
            'cost_impact' => $costImpact,
            'data' => [
                'current_humidity' => $humidity,
                'target_humidity' => $targetHumidity,
                'deviation' => $deviation,
                'alert_type' => $alertType,
            ],
        ]);
        
        // Broadcast alert
        $this->broadcastAlert($event);
        
        // Send notifications to users
        $this->notifyUsers($event);
        
        return $event;
    }
    
    /**
     * Send door open alert with energy loss calculation
     */
    public function sendDoorOpenAlert(Sensor $sensor, float $energyLossWatts = 0): ?Event
    {
        // Check if we should send alert (avoid spam)
        if (!$this->shouldSendAlert($sensor->id, 'door_open', 'warning')) {
            return null;
        }
        
        $room = $sensor->room;
        
        // Determine severity based on energy loss and position
        $severity = $this->calculateDoorAlertSeverity($energyLossWatts, $sensor->position);
        
        // Calculate hourly and daily cost impact
        $hourlyCost = ($energyLossWatts / 1000) * config('energy.price_per_kwh', 0.15);
        $dailyCost = $hourlyCost * 24;
        
        $message = $this->generateDoorOpenMessage($sensor, $energyLossWatts, $severity);
        
        $event = $this->createEvent($sensor, [
            'type' => $sensor->position === 'door' ? 'door_open' : 'window_open',
            'severity' => $severity,
            'message' => $message,
            'cost_impact' => $dailyCost,
            'data' => [
                'energy_loss_watts' => $energyLossWatts,
                'hourly_cost' => $hourlyCost,
                'daily_cost' => $dailyCost,
                'position' => $sensor->position,
                'room_name' => $room->name,
            ],
        ]);
        
        // Broadcast alert
        $this->broadcastAlert($event);
        
        // Send notifications to users
        $this->notifyUsers($event);
        
        return $event;
    }
    
    /**
     * Send door closed notification (positive feedback)
     */
    public function sendDoorClosedNotification(Sensor $sensor): void
    {
        $room = $sensor->room;
        
        // Check how long the door was open
        $openDuration = $this->getLastOpenDuration($sensor->id);
        
        if ($openDuration < 30) { // Less than 30 seconds - quick response
            // Reward users in the room's organization for quick action
            $this->rewardQuickResponse($sensor, $openDuration);
            
            // Send positive notification
            $message = "Great! {$sensor->position} in {$room->name} was closed quickly ({$openDuration}s). Energy saved!";
            
            broadcast(new SensorAlert([
                'type' => 'door_closed',
                'severity' => 'info',
                'message' => $message,
                'sensor' => $sensor,
                'data' => [
                    'duration' => $openDuration,
                    'energy_saved' => true,
                ],
            ]));
        }
    }
    
    /**
     * Send energy loss alert when significant waste is detected
     */
    public function sendEnergyLossAlert(Sensor $sensor, float $energyLossWatts, int $durationMinutes): ?Event
    {
        $severity = $this->calculateEnergyLossSeverity($energyLossWatts, $durationMinutes);
        
        // Check if we should send alert
        if (!$this->shouldSendAlert($sensor->id, 'energy_loss', $severity)) {
            return null;
        }
        
        $totalEnergyWh = ($energyLossWatts * $durationMinutes) / 60;
        $cost = ($totalEnergyWh / 1000) * config('energy.price_per_kwh', 0.15);
        
        $message = $this->generateEnergyLossMessage($sensor, $energyLossWatts, $durationMinutes, $cost);
        
        $event = $this->createEvent($sensor, [
            'type' => 'energy_loss',
            'severity' => $severity,
            'message' => $message,
            'cost_impact' => $cost,
            'data' => [
                'energy_loss_watts' => $energyLossWatts,
                'duration_minutes' => $durationMinutes,
                'total_energy_wh' => $totalEnergyWh,
                'cost' => $cost,
            ],
        ]);
        
        $this->broadcastAlert($event);
        $this->notifyUsers($event);
        
        return $event;
    }
    
    /**
     * Calculate temperature alert severity
     */
    private function calculateTemperatureSeverity(float $deviation): string
    {
        return match(true) {
            $deviation >= 8 => 'critical',
            $deviation >= 5 => 'warning',
            default => 'info',
        };
    }
    
    /**
     * Calculate humidity alert severity
     */
    private function calculateHumiditySeverity(float $deviation): string
    {
        return match(true) {
            $deviation >= 30 => 'critical',
            $deviation >= 20 => 'warning',
            default => 'info',
        };
    }
    
    /**
     * Calculate door alert severity
     */
    private function calculateDoorAlertSeverity(float $energyLossWatts, string $position): string
    {
        $threshold = $position === 'door' ? 100 : 50; // Doors typically lose more energy
        
        return match(true) {
            $energyLossWatts >= $threshold * 2 => 'critical',
            $energyLossWatts >= $threshold => 'warning',
            default => 'info',
        };
    }
    
    /**
     * Calculate energy loss alert severity
     */
    private function calculateEnergyLossSeverity(float $watts, int $minutes): string
    {
        $totalWh = ($watts * $minutes) / 60;
        
        return match(true) {
            $totalWh >= 500 => 'critical',
            $totalWh >= 200 => 'warning',
            default => 'info',
        };
    }
    
    /**
     * Check if we should send an alert to avoid spam
     */
    private function shouldSendAlert(string $sensorId, string $alertType, string $severity): bool
    {
        $cacheKey = "alert_throttle_{$sensorId}_{$alertType}_{$severity}";
        
        // Different throttle times based on severity
        $throttleMinutes = match($severity) {
            'critical' => 5,  // Allow critical alerts every 5 minutes
            'warning' => 10,  // Allow warning alerts every 10 minutes
            'info' => 30,     // Allow info alerts every 30 minutes
            default => 15,
        };
        
        if (Cache::has($cacheKey)) {
            return false;
        }
        
        Cache::put($cacheKey, true, now()->addMinutes($throttleMinutes));
        return true;
    }
    
    /**
     * Calculate temperature cost impact
     */
    private function calculateTemperatureCostImpact(float $deviation, float $surfaceM2): float
    {
        // Rough estimation: 50W per m² per degree deviation
        $extraWatts = $deviation * $surfaceM2 * 50;
        $dailyKwh = ($extraWatts * 24) / 1000;
        return $dailyKwh * config('energy.price_per_kwh', 0.15);
    }
    
    /**
     * Calculate humidity cost impact
     */
    private function calculateHumidityCostImpact(float $deviation, float $surfaceM2): float
    {
        // Humidity affects HVAC efficiency - rough estimation
        $efficiencyLoss = min(0.3, $deviation / 100); // Max 30% efficiency loss
        $baseWatts = $surfaceM2 * 30; // 30W per m² base HVAC
        $extraWatts = $baseWatts * $efficiencyLoss;
        $dailyKwh = ($extraWatts * 24) / 1000;
        return $dailyKwh * config('energy.price_per_kwh', 0.15);
    }
    
    /**
     * Generate temperature alert message
     */
    private function generateTemperatureMessage(float $current, float $target, string $type, string $severity): string
    {
        $emoji = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '',
        };
        
        $direction = $type === 'high' ? 'above' : 'below';
        
        return "{$emoji} Temperature is {$current}°C - {$direction} target of {$target}°C";
    }
    
    /**
     * Generate humidity alert message
     */
    private function generateHumidityMessage(float $current, float $target, string $type, string $severity): string
    {
        $emoji = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '',
        };
        
        $direction = $type === 'high' ? 'above' : 'below';
        
        return "{$emoji} Humidity is {$current}% - {$direction} target of {$target}%";
    }
    
    /**
     * Generate door open alert message
     */
    private function generateDoorOpenMessage(Sensor $sensor, float $energyLoss, string $severity): string
    {
        $emoji = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '',
        };
        
        $openingType = $sensor->position === 'door' ? 'Door' : 'Window';
        $room = $sensor->room->name;
        
        if ($energyLoss > 0) {
            return "{$emoji} {$openingType} open in {$room} - Energy loss: {$energyLoss}W";
        }
        
        return "{$emoji} {$openingType} open in {$room}";
    }
    
    /**
     * Generate energy loss alert message
     */
    private function generateEnergyLossMessage(Sensor $sensor, float $watts, int $minutes, float $cost): string
    {
        $room = $sensor->room->name;
        $costFormatted = number_format($cost, 2);
        
        return "🚨 Significant energy loss in {$room}: {$watts}W for {$minutes} minutes. Cost: €{$costFormatted}";
    }
    
    /**
     * Create an event record
     */
    private function createEvent(Sensor $sensor, array $data): Event
    {
        return Event::create([
            'sensor_id' => $sensor->id,
            'room_id' => $sensor->room_id,
            'type' => $data['type'],
            'severity' => $data['severity'],
            'message' => $data['message'],
            'cost_impact' => $data['cost_impact'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }
    
    /**
     * Broadcast alert via WebSocket
     */
    private function broadcastAlert(Event $event): void
    {
        try {
            broadcast(new AlertCreated($event))->toOthers();
            
            // Also broadcast sensor-specific alert
            broadcast(new SensorAlert([
                'event_id' => $event->id,
                'sensor_id' => $event->sensor_id,
                'room_id' => $event->room_id,
                'type' => $event->type,
                'severity' => $event->severity,
                'message' => $event->message,
                'cost_impact' => $event->cost_impact,
                'data' => $event->data,
                'timestamp' => $event->created_at->toISOString(),
            ]))->toOthers();
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast alert: ' . $e->getMessage(), [
                'event_id' => $event->id,
                'error' => $e->getTraceAsString(),
            ]);
        }
    }
    
    /**
     * Send notifications to relevant users
     */
    private function notifyUsers(Event $event): void
    {
        $room = $event->room;
        $organization = $room->building->organization;
        
        // Get users to notify based on severity and role
        $users = $this->getUsersToNotify($organization, $event->severity);
        
        foreach ($users as $user) {
            try {
                // Send notification through various channels
                $this->sendUserNotification($user, $event);
                
            } catch (\Exception $e) {
                Log::error("Failed to send notification to user {$user->id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get users to notify based on alert severity
     */
    private function getUsersToNotify($organization, string $severity): \Illuminate\Database\Eloquent\Collection
    {
        $query = $organization->users();
        
        // Filter by role based on severity
        match($severity) {
            'critical' => $query, // Notify all users for critical alerts
            'warning' => $query->whereIn('role', ['admin', 'manager']),
            'info' => $query->where('role', 'admin'),
            default => $query->where('role', 'admin'),
        };
        
        return $query->get();
    }
    
    /**
     * Send notification to a specific user
     */
    private function sendUserNotification(User $user, Event $event): void
    {
        // TODO: Implement actual notification channels (email, SMS, push)
        // For now, just log the notification
        Log::info("Notification sent to user", [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'event_id' => $event->id,
            'severity' => $event->severity,
            'message' => $event->message,
        ]);
    }
    
    /**
     * Get duration of last door open event
     */
    private function getLastOpenDuration(string $sensorId): int
    {
        // Get from cache or database
        $duration = Cache::get("door_open_start_{$sensorId}");
        
        if ($duration) {
            $openDuration = time() - $duration;
            Cache::forget("door_open_start_{$sensorId}");
            return $openDuration;
        }
        
        return 0;
    }
    
    /**
     * Reward users for quick response to door closing
     */
    private function rewardQuickResponse(Sensor $sensor, int $duration): void
    {
        $room = $sensor->room;
        $organization = $room->building->organization;
        
        // Find users who might have closed the door (in the same organization)
        $users = $organization->users()->where('role', '!=', 'admin')->get();
        
        foreach ($users as $user) {
            // Give quick response points
            $this->gamificationService->awardPoints(
                $user,
                'quick_response',
                "Quick response - closed {$sensor->position} in {$duration}s"
            );
        }
    }
    
    /**
     * Get alert statistics for dashboard
     */
    public function getAlertStatistics(int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        $stats = Event::where('created_at', '>=', $startTime)
            ->select([
                'severity',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(cost_impact) as total_cost'),
                DB::raw('AVG(cost_impact) as avg_cost'),
            ])
            ->groupBy('severity')
            ->get();
        
        $totalAlerts = $stats->sum('count');
        $totalCost = $stats->sum('total_cost');
        
        return [
            'total_alerts' => $totalAlerts,
            'total_cost' => round($totalCost, 2),
            'by_severity' => $stats->keyBy('severity')->toArray(),
            'critical_count' => $stats->where('severity', 'critical')->first()?->count ?? 0,
            'warning_count' => $stats->where('severity', 'warning')->first()?->count ?? 0,
            'info_count' => $stats->where('severity', 'info')->first()?->count ?? 0,
            'average_cost_per_alert' => $totalAlerts > 0 ? round($totalCost / $totalAlerts, 2) : 0,
        ];
    }
}