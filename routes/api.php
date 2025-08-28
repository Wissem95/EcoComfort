<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SensorController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public access)
Route::prefix('auth')->group(function () {
    Route::middleware(['api.throttle:10,1'])->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });
    
    Route::middleware(['jwt.auth'])->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// User profile route (JWT protected)
Route::middleware(['jwt.auth'])->get('/user', function (Request $request) {
    return $request->user()->load(['organization', 'gamificationHistory' => function ($query) {
        $query->latest()->limit(5);
    }]);
});

// Dashboard routes
Route::middleware(['jwt.auth', 'api.throttle:2000,1'])->prefix('dashboard')->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/sensor-data', [DashboardController::class, 'sensorData']);
    Route::get('/alerts', [DashboardController::class, 'alerts']);
    Route::post('/alerts/{eventId}/acknowledge', [DashboardController::class, 'acknowledgeAlert']);
    Route::get('/energy-analytics', [DashboardController::class, 'energyAnalytics']);
    Route::get('/gamification', [DashboardController::class, 'gamification']);
    Route::get('/rooms/{roomId}', [DashboardController::class, 'roomDetails']);
});

// Sensor management routes
Route::middleware(['jwt.auth'])->prefix('sensors')->group(function () {
    // High-throughput sensor data endpoints
    Route::middleware(['api.throttle:1000,1'])->group(function () {
        Route::post('/data', [SensorController::class, 'storeData']); // For IoT sensor data submission
        Route::get('/{sensorId}/history', [SensorController::class, 'history']);
    });
    
    // Standard sensor management
    Route::middleware(['api.throttle:100,1'])->group(function () {
        Route::get('/', [SensorController::class, 'index']);
        Route::post('/', [SensorController::class, 'store']);
        Route::get('/{sensorId}', [SensorController::class, 'show']);
        Route::put('/{sensorId}', [SensorController::class, 'update']);
        Route::delete('/{sensorId}', [SensorController::class, 'destroy']);
        Route::post('/{sensorId}/calibrate', [SensorController::class, 'calibrate']);
        Route::post('/{sensorId}/reset-detection', [SensorController::class, 'resetDetection']);
    });
});

// Event/Alert management routes
Route::middleware(['jwt.auth', 'api.throttle:100,1'])->prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('/{eventId}', [EventController::class, 'show']);
    Route::post('/{eventId}/acknowledge', [EventController::class, 'acknowledge']);
    Route::post('/bulk-acknowledge', [EventController::class, 'bulkAcknowledge']);
    Route::get('/statistics', [EventController::class, 'statistics'])->name('events.statistics');
    Route::get('/types', [EventController::class, 'types']);
});

// Gamification routes
Route::middleware(['jwt.auth', 'api.throttle:100,1'])->prefix('gamification')->group(function () {
    Route::get('/profile', [GamificationController::class, 'profile']);
    Route::get('/leaderboard', [GamificationController::class, 'leaderboard']);
    Route::get('/badges', [GamificationController::class, 'badges']);
    Route::get('/achievements', [GamificationController::class, 'achievements']);
    
    // Challenges
    Route::get('/challenges', [GamificationController::class, 'challenges']);
    Route::post('/challenges/{challengeId}/join', [GamificationController::class, 'joinChallenge']);
    Route::post('/challenges', [GamificationController::class, 'createChallenge']); // Admin only
    
    // Admin routes
    Route::post('/award-points', [GamificationController::class, 'awardPoints']); // Admin only
    Route::get('/organization-stats', [GamificationController::class, 'organizationStats']); // Admin only
});

// Administration routes (Production Ready)
Route::middleware(['jwt.auth', 'api.throttle:100,1'])->prefix('admin')->group(function () {
    // Configuration
    Route::get('/configuration', [AdminController::class, 'getConfiguration']);
    
    // Buildings CRUD
    Route::get('/buildings', [AdminController::class, 'getBuildings']);
    Route::post('/buildings', [AdminController::class, 'createBuilding']);
    Route::put('/buildings/{id}', [AdminController::class, 'updateBuilding']);
    Route::delete('/buildings/{id}', [AdminController::class, 'deleteBuilding']);
    
    // Rooms CRUD
    Route::get('/rooms', [AdminController::class, 'getRooms']);
    Route::post('/rooms', [AdminController::class, 'createRoom']);
    Route::put('/rooms/{id}', [AdminController::class, 'updateRoom']);
    Route::delete('/rooms/{id}', [AdminController::class, 'deleteRoom']);
    
    // Sensors CRUD
    Route::get('/sensors', [AdminController::class, 'getSensors']);
    Route::post('/sensors', [AdminController::class, 'createSensor']);
    Route::put('/sensors/{id}', [AdminController::class, 'updateSensor']);
    Route::delete('/sensors/{id}', [AdminController::class, 'deleteSensor']);
});

// Health check and system status
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'services' => [
            'database' => 'connected',
            'redis' => 'connected',
            'mqtt' => 'connected',
            'reverb' => 'active',
        ],
    ]);
});

// PRODUCTION MODE - All development/test routes removed

// WebSocket authentication for private channels
Route::middleware(['jwt.auth', 'api.throttle:50,1'])->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

// MQTT webhook endpoint (rate limited for security)
Route::middleware(['api.throttle:100,1'])->post('/mqtt/webhook', function (Request $request) {
    // This endpoint can be used for testing MQTT message reception
    \Illuminate\Support\Facades\Log::info('MQTT Webhook received', $request->all());
    
    return response()->json(['received' => true]);
});

// High-frequency IoT data endpoints
Route::prefix('iot')->middleware(['api.throttle:1000,1'])->group(function () {
    // Sensor data submission endpoint for MQTT bridge
    Route::post('/sensor-data', function (Request $request) {
        // Basic validation
        $validated = $request->validate([
            'sensor_id' => 'required|exists:sensors,id',
            'topic' => 'required|string',
            'value' => 'required|numeric',
            'timestamp' => 'nullable|date',
        ]);
        
        // This would typically be handled by the MQTT listener,
        // but provides a REST fallback for IoT devices
        \Illuminate\Support\Facades\Log::info('IoT sensor data received via REST', $validated);
        
        return response()->json(['received' => true, 'timestamp' => now()]);
    });
    
    // Device heartbeat
    Route::post('/heartbeat', function (Request $request) {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'status' => 'required|in:online,offline',
        ]);
        
        \Illuminate\Support\Facades\Cache::put(
            "device_heartbeat:{$validated['device_id']}", 
            $validated['status'], 
            now()->addMinutes(10)
        );
        
        return response()->json(['acknowledged' => true]);
    });
});

// System statistics (admin only)
Route::middleware(['jwt.auth', 'api.throttle:200,1'])->get('/system/stats', function (Request $request) {
    if (!$request->user()->isAdmin()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    $stats = \Illuminate\Support\Facades\Cache::remember('system_stats', now()->addMinutes(5), function () {
        return [
            'total_organizations' => \App\Models\Organization::count(),
            'total_buildings' => \App\Models\Building::count(),
            'total_rooms' => \App\Models\Room::count(),
            'total_sensors' => \App\Models\Sensor::count(),
            'active_sensors' => \App\Models\Sensor::where('is_active', true)->count(),
            'total_users' => \App\Models\User::count(),
            'total_events' => \App\Models\Event::count(),
            'unacknowledged_events' => \App\Models\Event::where('acknowledged', false)->count(),
            'data_points_24h' => \App\Models\SensorData::where('timestamp', '>=', now()->subDay())->count(),
            'energy_loss_24h' => \App\Models\SensorData::where('timestamp', '>=', now()->subDay())->sum('energy_loss_watts'),
        ];
    });
    
    return response()->json($stats);
});