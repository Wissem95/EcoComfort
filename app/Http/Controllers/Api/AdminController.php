<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Room;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // ========== BUILDINGS ==========
    
    public function getBuildings(Request $request)
    {
        $buildings = Building::where('organization_id', $request->user()->organization_id)
            ->with(['rooms.sensors'])
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $buildings
        ]);
    }
    
    public function createBuilding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $building = Building::create([
            'name' => $request->name,
            'address' => $request->address,
            'organization_id' => $request->user()->organization_id
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $building->load('rooms')
        ], Response::HTTP_CREATED);
    }
    
    public function updateBuilding(Request $request, $id)
    {
        $building = Building::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $building->update($request->only(['name', 'address']));
        
        return response()->json([
            'success' => true,
            'data' => $building->load('rooms')
        ]);
    }
    
    public function deleteBuilding(Request $request, $id)
    {
        $building = Building::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        
        // Check if building has rooms
        if ($building->rooms()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete building with rooms',
                'message' => 'Please delete all rooms first'
            ], Response::HTTP_CONFLICT);
        }
        
        $building->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Building deleted successfully'
        ]);
    }
    
    // ========== ROOMS ==========
    
    public function getRooms(Request $request)
    {
        $rooms = Room::whereHas('building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->with(['building', 'sensors'])->get();
        
        return response()->json([
            'success' => true,
            'data' => $rooms
        ]);
    }
    
    public function createRoom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'building_id' => 'required|exists:buildings,id',
            'floor' => 'required|integer',
            'surface_m2' => 'required|numeric|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Verify building belongs to user's organization
        $building = Building::where('id', $request->building_id)
            ->where('organization_id', $request->user()->organization_id)
            ->first();
            
        if (!$building) {
            return response()->json([
                'error' => 'Building not found or unauthorized'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $room = Room::create([
            'name' => $request->name,
            'building_id' => $request->building_id,
            'floor' => $request->floor,
            'surface_m2' => $request->surface_m2
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $room->load(['building', 'sensors'])
        ], Response::HTTP_CREATED);
    }
    
    public function updateRoom(Request $request, $id)
    {
        $room = Room::whereHas('building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'floor' => 'sometimes|integer',
            'surface_m2' => 'sometimes|numeric|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $room->update($request->only(['name', 'floor', 'surface_m2']));
        
        return response()->json([
            'success' => true,
            'data' => $room->load(['building', 'sensors'])
        ]);
    }
    
    public function deleteRoom(Request $request, $id)
    {
        $room = Room::whereHas('building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->findOrFail($id);
        
        // Check if room has sensors
        if ($room->sensors()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete room with sensors',
                'message' => 'Please delete or reassign all sensors first'
            ], Response::HTTP_CONFLICT);
        }
        
        $room->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully'
        ]);
    }
    
    // ========== SENSORS ==========
    
    public function getSensors(Request $request)
    {
        $sensors = Sensor::whereHas('room.building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->with(['room.building'])->get();
        
        return response()->json([
            'success' => true,
            'data' => $sensors
        ]);
    }
    
    public function createSensor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'room_id' => 'required|exists:rooms,id',
            'type' => 'required|in:ruuvitag,temperature,humidity,movement',
            'mqtt_topic' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'mac_address' => 'nullable|string|max:17'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Verify room belongs to user's organization
        $room = Room::whereHas('building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->find($request->room_id);
        
        if (!$room) {
            return response()->json([
                'error' => 'Room not found or unauthorized'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $sensor = Sensor::create([
            'name' => $request->name,
            'room_id' => $request->room_id,
            'type' => $request->type,
            'mqtt_topic' => $request->mqtt_topic,
            'position' => $request->position,
            'mac_address' => $request->mac_address,
            'battery_level' => 100,
            'is_active' => true
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $sensor->load('room.building')
        ], Response::HTTP_CREATED);
    }
    
    public function updateSensor(Request $request, $id)
    {
        $sensor = Sensor::whereHas('room.building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'room_id' => 'sometimes|exists:rooms,id',
            'position' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'mqtt_topic' => 'sometimes|string|max:255',
            'mac_address' => 'sometimes|nullable|string|max:17'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // If changing room, verify new room belongs to organization
        if ($request->has('room_id')) {
            $room = Room::whereHas('building', function ($query) use ($request) {
                $query->where('organization_id', $request->user()->organization_id);
            })->find($request->room_id);
            
            if (!$room) {
                return response()->json([
                    'error' => 'Room not found or unauthorized'
                ], Response::HTTP_FORBIDDEN);
            }
        }
        
        $sensor->update($request->only([
            'name', 'room_id', 'position', 'is_active', 'mqtt_topic', 'mac_address'
        ]));
        
        return response()->json([
            'success' => true,
            'data' => $sensor->load('room.building')
        ]);
    }
    
    public function deleteSensor(Request $request, $id)
    {
        $sensor = Sensor::whereHas('room.building', function ($query) use ($request) {
            $query->where('organization_id', $request->user()->organization_id);
        })->findOrFail($id);
        
        // Keep historical data, just mark as inactive
        $sensor->update(['is_active' => false]);
        
        return response()->json([
            'success' => true,
            'message' => 'Sensor deactivated successfully'
        ]);
    }
    
    // ========== CONFIGURATION ==========
    
    public function getConfiguration(Request $request)
    {
        $org = $request->user()->organization;
        
        return response()->json([
            'success' => true,
            'data' => [
                'organization' => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'surface_m2' => $org->surface_m2,
                    'target_percent' => $org->target_percent,
                    'timezone' => $org->timezone
                ],
                'mqtt' => [
                    'topics' => [
                        'temperature' => '112',
                        'humidity' => '114',
                        'movement' => '127'
                    ],
                    'status' => 'connected'
                ],
                'stats' => [
                    'buildings' => Building::where('organization_id', $org->id)->count(),
                    'rooms' => Room::whereHas('building', function ($q) use ($org) {
                        $q->where('organization_id', $org->id);
                    })->count(),
                    'sensors' => Sensor::whereHas('room.building', function ($q) use ($org) {
                        $q->where('organization_id', $org->id);
                    })->count(),
                    'active_sensors' => Sensor::whereHas('room.building', function ($q) use ($org) {
                        $q->where('organization_id', $org->id);
                    })->where('is_active', true)->count()
                ]
            ]
        ]);
    }
}