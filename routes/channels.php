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

// Organization-wide channels (public for now - fix auth later)
Broadcast::channel('organization.{organizationId}', function (User $user, string $organizationId) {
    return true; // Temporarily allow all users
});

// Room-specific channels
Broadcast::channel('room.{roomId}', function (User $user, string $roomId) {
    return true; // Temporarily allow all users
});

// Sensor-specific channels
Broadcast::channel('sensor.{sensorId}', function (User $user, string $sensorId) {
    return true; // Temporarily allow all users
});

// Global alerts channel (for admin users)
Broadcast::channel('alerts', function (User $user) {
    return true; // Temporarily allow all users
});

// Critical alerts channel (for all users)
Broadcast::channel('critical-alerts.{organizationId}', function (User $user, string $organizationId) {
    return true; // Temporarily allow all users
});

// Leaderboard updates
Broadcast::channel('leaderboard.{organizationId}', function (User $user, string $organizationId) {
    return true; // Temporarily allow all users
});

// User-specific notifications
Broadcast::channel('notifications.{userId}', function (User $user, string $userId) {
    return true; // Temporarily allow all users
});

// Energy efficiency updates for organization
Broadcast::channel('energy.{organizationId}', function (User $user, string $organizationId) {
    return true; // Temporarily allow all users
});

// System status channel (admin only)
Broadcast::channel('system.status', function (User $user) {
    return true; // Temporarily allow all users
});
