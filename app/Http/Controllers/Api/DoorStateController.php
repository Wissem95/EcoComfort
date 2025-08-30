<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoorStateConfirmation;
use App\Models\Sensor;
use App\Models\SensorData;
use App\Services\GamificationService;
use App\Services\EnergyCalculatorService;
use App\Events\DoorStateCertaintyChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DoorStateController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService,
        private EnergyCalculatorService $energyCalculatorService
    ) {}
    
    /**
     * Confirm the door state manually
     */
    public function confirmState(Request $request, string $sensorId)
    {
        $request->validate([
            'state' => 'required|in:closed,opened',
            'notes' => 'nullable|string|max:500'
        ]);
        
        try {
            $sensor = Sensor::findOrFail($sensorId);
            $user = auth()->user();
            
            // For testing purposes, allow operation without authentication
            if (!$user) {
                // Create a mock user for testing with real UUID from database
                $user = (object) [
                    'id' => '38a27be1-4432-4e6a-a671-a20b9125be80', // Real admin user UUID
                    'name' => 'Test User'
                ];
            }
            
            // Get current sensor data
            $sensorData = SensorData::where('sensor_id', $sensorId)
                ->orderBy('timestamp', 'desc')
                ->first();
                
            if (!$sensorData) {
                return response()->json(['error' => 'No sensor data found'], 404);
            }
            
            $previousState = $sensorData->door_state ? 'opened' : 'closed';
            $previousCertainty = $sensorData->door_state_certainty;
            $confidenceBefore = null; // Could be extracted from latest detection
            
            // Store previous state for event
            $previousStateForEvent = [
                'door_state' => $previousState,
                'certainty' => $previousCertainty,
                'needs_confirmation' => $sensorData->needs_confirmation ?? false
            ];
            
            // Create confirmation record for audit
            $confirmation = DoorStateConfirmation::create([
                'sensor_id' => $sensorId,
                'user_id' => is_object($user) && isset($user->id) ? $user->id : '38a27be1-4432-4e6a-a671-a20b9125be80', // Use test user UUID if not authenticated
                'confirmed_state' => $request->state,
                'previous_state' => $previousState,
                'previous_certainty' => $previousCertainty,
                'sensor_position' => [
                    'x' => $sensorData->acceleration_x,
                    'y' => $sensorData->acceleration_y,
                    'z' => $sensorData->acceleration_z
                ],
                'confidence_before' => $confidenceBefore,
                'user_notes' => $request->notes
            ]);
            
            // Update sensor data with confirmed state
            $sensorData->update([
                'door_state' => $request->state === 'opened',
                'door_state_certainty' => 'CERTAIN',
                'needs_confirmation' => false
            ]);
            
            // Calculate energy loss based on confirmed state
            $energyLossWatts = 0.0;
            if ($request->state === 'opened' && $sensor->room) {
                // Get temperature from current data or recent data
                $temperature = $sensorData->temperature;
                
                if (!$temperature) {
                    // Get most recent temperature reading for this sensor
                    $recentTempData = SensorData::where('sensor_id', $sensorId)
                        ->whereNotNull('temperature')
                        ->orderBy('timestamp', 'desc')
                        ->first();
                    
                    $temperature = $recentTempData?->temperature;
                }
                
                if ($temperature) {
                    // Get outdoor temperature (using same method as MQTT service)
                    $outdoorTemp = 12.0; // Hardcoded as per current system
                    
                    $energyCalc = $this->energyCalculatorService->calculateEnergyLossEcoComfort(
                        $temperature,
                        $outdoorTemp,
                        $sensor->room->surface_m2,
                        'door'
                    );
                    
                    $energyLossWatts = $energyCalc['energy_loss_watts'];
                    
                    Log::info("Energy calculation from manual confirmation", [
                        'sensor_id' => $sensorId,
                        'door_state' => $request->state,
                        'indoor_temp' => $temperature,
                        'outdoor_temp' => $outdoorTemp,
                        'surface_m2' => $sensor->room->surface_m2,
                        'energy_loss_watts' => $energyLossWatts,
                        'temp_from_current' => $sensorData->temperature !== null
                    ]);
                } else {
                    Log::warning("No temperature data available for energy calculation", [
                        'sensor_id' => $sensorId,
                        'door_state' => $request->state
                    ]);
                }
            }
            
            // Update energy loss and cumulative tracking
            $this->updateEnergyCumulative($sensorData, $energyLossWatts, $request->state, $previousState);
            
            // Award gamification points
            $pointsAwarded = 0;
            if (is_object($user) && property_exists($user, 'id') && auth()->user()) {
                // Only award points for real authenticated users
                if ($previousState !== $request->state) {
                    // User corrected the state - award points for quick response
                    $gamification = $this->gamificationService->awardPoints($user, 'quick_response', 'Door state corrected manually');
                    $pointsAwarded = $gamification->points;
                    
                    Log::info("User corrected door state", [
                        'user_id' => $user->id,
                        'sensor_id' => $sensorId,
                        'from' => $previousState,
                        'to' => $request->state,
                        'points_awarded' => $pointsAwarded
                    ]);
                } else {
                    // User confirmed existing state - award points for acknowledging alert
                    $gamification = $this->gamificationService->awardPoints($user, 'acknowledge_alert', 'Door state confirmed');
                    $pointsAwarded = $gamification->points;
                    
                    Log::info("User confirmed door state", [
                        'user_id' => $user->id,
                        'sensor_id' => $sensorId,
                        'state' => $request->state,
                        'points_awarded' => $pointsAwarded
                    ]);
                }
            } else {
                // Testing mode - no gamification
                Log::info("Door state changed in test mode", [
                    'sensor_id' => $sensorId,
                    'from' => $previousState,
                    'to' => $request->state,
                    'test_mode' => true
                ]);
            }
            
            // If confirmed closed, update dynamic calibration
            if ($request->state === 'closed' && $sensorData->acceleration_x && $sensorData->acceleration_y && $sensorData->acceleration_z) {
                $this->updateDynamicCalibrationFromConfirmation($sensor, [
                    'x' => $sensorData->acceleration_x,
                    'y' => $sensorData->acceleration_y,
                    'z' => $sensorData->acceleration_z
                ]);
            }
            
            // Refresh sensor data to get latest state
            $sensorData->refresh();
            
            // Broadcast certainty change event from user confirmation
            broadcast(new DoorStateCertaintyChanged($sensorData, $previousStateForEvent, 'user_confirmation'));
            
            return response()->json([
                'success' => true,
                'message' => 'Door state confirmed successfully',
                'data' => [
                    'confirmed_state' => $request->state,
                    'previous_state' => $previousState,
                    'points_awarded' => $pointsAwarded,
                    'confirmation_id' => $confirmation->id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to confirm door state', [
                'sensor_id' => $sensorId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Failed to confirm door state'], 500);
        }
    }
    
    /**
     * Get door state confirmation history for a sensor
     */
    public function getConfirmationHistory(string $sensorId)
    {
        try {
            $confirmations = DoorStateConfirmation::where('sensor_id', $sensorId)
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $confirmations
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get confirmation history'], 500);
        }
    }
    
    /**
     * Update dynamic calibration from user confirmation
     */
    private function updateDynamicCalibrationFromConfirmation(Sensor $sensor, array $position): void
    {
        try {
            if (!$sensor->calibration_data) {
                return;
            }
            
            $calibrationData = $sensor->calibration_data;
            $oldRef = $calibrationData['door_position']['closed_reference'] ?? null;
            
            if (!$oldRef) {
                return;
            }
            
            // More aggressive update from user confirmation (20% new, 80% old)
            $newRef = [
                'x' => round($oldRef['x'] * 0.8 + $position['x'] * 0.2, 2),
                'y' => round($oldRef['y'] * 0.8 + $position['y'] * 0.2, 2),
                'z' => round($oldRef['z'] * 0.8 + $position['z'] * 0.2, 2)
            ];
            
            $calibrationData['door_position']['closed_reference'] = $newRef;
            $calibrationData['door_position']['last_user_confirmation'] = now()->toISOString();
            
            $sensor->calibration_data = $calibrationData;
            $sensor->save();
            
            Log::info("Dynamic calibration updated from user confirmation", [
                'sensor_id' => $sensor->id,
                'old_reference' => $oldRef,
                'new_reference' => $newRef
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to update calibration from user confirmation", [
                'sensor_id' => $sensor->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update cumulative energy tracking fields (similar to MQTT service)
     */
    private function updateEnergyCumulative(
        SensorData $sensorData, 
        float $energyLossWatts, 
        string $newState,
        string $previousState
    ): void {
        try {
            // Store the current energy rate before any updates for calculations
            $currentEnergyRate = $sensorData->energy_loss_watts;
            
            if ($newState === 'opened') {
                // Update the instant energy loss rate
                $sensorData->energy_loss_watts = $energyLossWatts;
                // Door is opening
                if ($previousState !== 'opened') {
                    // Transition from closed to opened - start new tracking
                    $sensorData->door_open_since = now();
                    $sensorData->cumulative_energy_kwh = 0.0;
                    $sensorData->energy_cost_euros = 0.0;
                    $sensorData->door_open_duration_seconds = 0;
                    
                    // Create new EnergyEvent
                    $this->createEnergyEvent($sensorData, 'manual_confirmation');
                    
                    Log::info("Started cumulative energy tracking from manual confirmation", [
                        'sensor_id' => $sensorData->sensor_id,
                        'door_open_since' => $sensorData->door_open_since,
                        'energy_rate_watts' => $energyLossWatts
                    ]);
                }
            } elseif ($newState === 'closed') {
                // Door is closing
                if ($previousState === 'opened' && $sensorData->door_open_since) {
                    // Transition from opened to closed - finalize tracking
                    $durationSeconds = (int) round($sensorData->door_open_since->diffInSeconds(now()));
                    
                    // Calculate final energy using the stored rate BEFORE setting it to 0
                    $durationHours = $durationSeconds / 3600;
                    $finalEnergyKwh = ($currentEnergyRate / 1000) * $durationHours;
                    $finalCostEuros = $finalEnergyKwh * 0.1740;
                    
                    // Update final values
                    $sensorData->cumulative_energy_kwh = $finalEnergyKwh;
                    $sensorData->energy_cost_euros = $finalCostEuros;
                    $sensorData->door_open_duration_seconds = $durationSeconds;
                    $sensorData->door_open_since = null; // Clear since door is closed
                    
                    // Finalize EnergyEvent
                    $this->finalizeEnergyEvent($sensorData, $finalEnergyKwh, $finalCostEuros, $durationSeconds);
                    
                    Log::info("Finalized cumulative energy tracking from manual confirmation", [
                        'sensor_id' => $sensorData->sensor_id,
                        'total_energy_kwh' => $finalEnergyKwh,
                        'total_cost_euros' => $finalCostEuros,
                        'duration_seconds' => $durationSeconds,
                        'energy_rate_used' => $currentEnergyRate
                    ]);
                } else {
                    // Door was already closed - reset to ensure clean state
                    $sensorData->cumulative_energy_kwh = 0.0;
                    $sensorData->energy_cost_euros = 0.0;
                    $sensorData->door_open_duration_seconds = 0;
                    $sensorData->door_open_since = null;
                }
                
                // Set energy loss to 0 for closed door (after calculations)
                $sensorData->energy_loss_watts = 0.0;
            }
            
            $sensorData->save();
            
        } catch (\Exception $e) {
            Log::error("Failed to update energy cumulative from manual confirmation", [
                'sensor_id' => $sensorData->sensor_id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create new EnergyEvent for opened door (manual confirmation)
     */
    private function createEnergyEvent(SensorData $sensorData, string $detectionMethod): void
    {
        try {
            \App\Models\EnergyEvent::create([
                'sensor_id' => $sensorData->sensor_id,
                'start_time' => $sensorData->door_open_since,
                'average_power_watts' => $sensorData->energy_loss_watts,
                'avg_indoor_temp' => $sensorData->temperature,
                'outdoor_temp' => 12.0, // Hardcoded as per current system
                'delta_temp' => abs(($sensorData->temperature ?? 0) - 12.0),
                'is_ongoing' => true,
                'detection_method' => $detectionMethod,
                'total_energy_kwh' => 0.0,
                'total_cost_euros' => 0.0,
                'duration_seconds' => 0,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to create EnergyEvent from manual confirmation", [
                'sensor_id' => $sensorData->sensor_id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Finalize EnergyEvent for closed door (manual confirmation)
     */
    private function finalizeEnergyEvent(
        SensorData $sensorData, 
        float $totalEnergyKwh, 
        float $totalCostEuros, 
        int $durationSeconds
    ): void {
        try {
            $ongoingEvent = \App\Models\EnergyEvent::where('sensor_id', $sensorData->sensor_id)
                ->where('is_ongoing', true)
                ->first();
                
            if ($ongoingEvent) {
                $ongoingEvent->update([
                    'end_time' => now(),
                    'total_energy_kwh' => $totalEnergyKwh,
                    'total_cost_euros' => $totalCostEuros,
                    'duration_seconds' => $durationSeconds,
                    'is_ongoing' => false,
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to finalize EnergyEvent from manual confirmation", [
                'sensor_id' => $sensorData->sensor_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
