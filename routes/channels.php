<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Organization;
use App\Models\Room;
use App\Models\Sensor;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Organization-wide channels
Broadcast::channel('organization.{organizationId}', function (User $user, string $organizationId) {
    return $user->organization_id === $organizationId;
});

// Room-specific channels
Broadcast::channel('room.{roomId}', function (User $user, string $roomId) {
    $room = Room::find($roomId);
    
    if (!$room) {
        return false;
    }
    
    return $user->organization->buildings->contains($room->building);
});

// Sensor-specific channels
Broadcast::channel('sensor.{sensorId}', function (User $user, string $sensorId) {
    $sensor = Sensor::with('room.building.organization')->find($sensorId);
    
    if (!$sensor) {
        return false;
    }
    
    return $user->organization_id === $sensor->room->building->organization_id;
});

// Global alerts channel (for admin users)
Broadcast::channel('alerts', function (User $user) {
    return $user->isManager(); // Only managers and admins can listen to all alerts
});

// Critical alerts channel (for all users)
Broadcast::channel('critical-alerts.{organizationId}', function (User $user, string $organizationId) {
    return $user->organization_id === $organizationId;
});

// Leaderboard updates
Broadcast::channel('leaderboard.{organizationId}', function (User $user, string $organizationId) {
    return $user->organization_id === $organizationId;
});

// User-specific notifications
Broadcast::channel('notifications.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

// Energy efficiency updates for organization
Broadcast::channel('energy.{organizationId}', function (User $user, string $organizationId) {
    return $user->organization_id === $organizationId;
});

// System status channel (admin only)
Broadcast::channel('system.status', function (User $user) {
    return $user->isAdmin();
});
