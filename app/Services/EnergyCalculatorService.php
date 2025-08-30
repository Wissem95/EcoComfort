<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnergyCalculatorService
{
    // Physical constants
    private const AIR_SPECIFIC_HEAT = 1005; // J/(kg·K)
    private const AIR_DENSITY = 1.225; // kg/m³ at sea level
    private const DOOR_OPENING_AREA = 2.0; // m² (average door)
    private const WINDOW_OPENING_AREA = 1.5; // m² (average window)
    private const AIR_EXCHANGE_RATE_DOOR = 3.0; // air changes per hour when door is open
    private const AIR_EXCHANGE_RATE_WINDOW = 2.0; // air changes per hour when window is open
    
    // EcoComfort specific constants as per requirements
    private const THERMAL_TRANSMITTANCE_DOOR = 3.5; // U coefficient W/(m²·K) for typical door opening
    private const THERMAL_TRANSMITTANCE_WINDOW = 2.8; // U coefficient W/(m²·K) for typical window opening
    private const EDF_TARIFF_PER_KWH = 0.1740; // €/kWh - EDF tariff 2024 (regulated blue tariff)
    
    /**
     * Calculate energy loss using EcoComfort specification formula
     * Formula: Watts perdus = ΔT × Surface × Coefficient U
     * Returns: energy_loss_watts + cost_impact as specified
     */
    public function calculateEnergyLossEcoComfort(
        float $indoorTemp,
        float $outdoorTemp,
        float $roomSurface,
        string $openingType = 'door'
    ): array {
        $startTime = microtime(true); // Performance tracking <25ms requirement
        
        // ΔT = difference between indoor and outdoor temperature
        $deltaT = abs($indoorTemp - $outdoorTemp);
        
        // If no significant temperature difference, no energy loss
        if ($deltaT < 0.1) {
            return [
                'energy_loss_watts' => 0.0,
                'cost_impact_euro_per_hour' => 0.0,
                'deltaT' => $deltaT,
                'surface_m2' => $roomSurface,
                'u_coefficient' => 0,
                'opening_type' => $openingType,
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
            ];
        }
        
        // U coefficient based on opening type
        $uCoefficient = match($openingType) {
            'door' => self::THERMAL_TRANSMITTANCE_DOOR,
            'window' => self::THERMAL_TRANSMITTANCE_WINDOW,
            default => self::THERMAL_TRANSMITTANCE_DOOR,
        };
        
        // Core formula as specified: Watts perdus = ΔT × Surface × Coefficient U
        $energyLossWatts = $deltaT * $roomSurface * $uCoefficient;
        
        // Convert to €/hour based on EDF tariff as specified
        $energyLossKwh = $energyLossWatts / 1000; // Convert W to kW
        $costImpactEuroPerHour = $energyLossKwh * self::EDF_TARIFF_PER_KWH;
        
        // Performance check - must be <25ms as specified
        $processingTime = (microtime(true) - $startTime) * 1000;
        if ($processingTime > 25) {
            Log::warning("Energy calculation exceeded 25ms performance target", [
                'processing_time_ms' => $processingTime,
                'deltaT' => $deltaT,
                'surface' => $roomSurface,
            ]);
        }
        
        return [
            'energy_loss_watts' => round($energyLossWatts, 2),
            'cost_impact_euro_per_hour' => round($costImpactEuroPerHour, 4),
            'deltaT' => $deltaT,
            'surface_m2' => $roomSurface,
            'u_coefficient' => $uCoefficient,
            'opening_type' => $openingType,
            'processing_time_ms' => $processingTime,
            'yearly_cost_projection' => round($costImpactEuroPerHour * 8760, 2), // €/year if always open
            'daily_cost_projection' => round($costImpactEuroPerHour * 24, 2), // €/day if always open
        ];
    }
    
    /**
     * Calculate energy loss when door/window is open (Advanced thermodynamic method)
     * Based on heat transfer equation: Q = m × c × ΔT
     */
    public function calculateEnergyLoss(
        float $indoorTemp,
        float $outdoorTemp,
        float $roomVolume,
        string $openingType = 'door'
    ): float {
        // Temperature difference
        $deltaT = abs($indoorTemp - $outdoorTemp);
        
        // If no temperature difference, no energy loss
        if ($deltaT < 0.5) {
            return 0;
        }
        
        // Air exchange rate based on opening type
        $airExchangeRate = match($openingType) {
            'door' => self::AIR_EXCHANGE_RATE_DOOR,
            'window' => self::AIR_EXCHANGE_RATE_WINDOW,
            default => 1.0,
        };
        
        // Volume of air exchanged per hour (m³/h)
        $volumeFlowRate = $roomVolume * $airExchangeRate;
        
        // Mass flow rate of air (kg/h)
        $massFlowRate = $volumeFlowRate * self::AIR_DENSITY;
        
        // Energy loss rate (J/h)
        $energyLossJoules = $massFlowRate * self::AIR_SPECIFIC_HEAT * $deltaT;
        
        // Convert to Watts (J/s)
        $energyLossWatts = $energyLossJoules / 3600;
        
        // Apply correction factors based on real-world conditions
        $energyLossWatts = $this->applyEnvironmentalFactors($energyLossWatts, $deltaT, $openingType);
        
        return round($energyLossWatts, 2);
    }
    
    /**
     * Apply environmental correction factors
     */
    private function applyEnvironmentalFactors(
        float $baseEnergyLoss,
        float $deltaT,
        string $openingType
    ): float {
        $correctionFactor = 1.0;
        
        // Wind factor (increases air exchange)
        $windSpeed = $this->getWindSpeed();
        if ($windSpeed > 5) { // m/s
            $correctionFactor *= 1 + ($windSpeed - 5) * 0.1;
        }
        
        // Stack effect factor (temperature-driven air movement)
        if ($deltaT > 10) {
            $correctionFactor *= 1 + ($deltaT - 10) * 0.02;
        }
        
        // Opening type factor
        if ($openingType === 'door') {
            $correctionFactor *= 1.2; // Doors typically have higher air exchange
        }
        
        // Humidity factor (affects heat capacity)
        $humidity = $this->getIndoorHumidity();
        if ($humidity > 60) {
            $correctionFactor *= 1 + ($humidity - 60) * 0.002;
        }
        
        return $baseEnergyLoss * min($correctionFactor, 2.0); // Cap at 2x base loss
    }
    
    /**
     * Calculate cumulative energy loss for a room over time
     */
    public function calculateCumulativeEnergyLoss(Room $room, int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        // Get ALL sensor data for the room in the time period (not just opened doors)
        $allSensorData = SensorData::whereIn('sensor_id', $room->sensors->pluck('id'))
            ->where('timestamp', '>=', $startTime)
            ->orderBy('sensor_id', 'asc')
            ->orderBy('timestamp', 'asc')
            ->get();
        
        $totalEnergyLoss = 0;
        $totalDuration = 0;
        $events = [];
        $sensorEvents = [];
        
        // Group by sensor and detect state transitions
        $dataBySensor = $allSensorData->groupBy('sensor_id');
        
        foreach ($dataBySensor as $sensorId => $sensorData) {
            $currentEvent = null;
            $previousState = null;
            $energyRates = []; // Track energy rates during open period
            
            foreach ($sensorData as $data) {
                $currentState = (bool)$data->door_state;
                
                // Detect transition from closed to opened
                if ($currentState && ($previousState === false || $previousState === null) && !$currentEvent) {
                    $currentEvent = [
                        'start' => $data->timestamp,
                        'sensor_id' => $sensorId,
                        'ongoing' => true
                    ];
                    $energyRates = []; // Reset energy rates
                }
                
                // Collect energy rates during opened state
                if ($currentState && $currentEvent && $data->energy_loss_watts && $data->energy_loss_watts > 0) {
                    $energyRates[] = $data->energy_loss_watts;
                }
                
                // Detect transition from opened to closed
                if (!$currentState && $previousState === true && $currentEvent) {
                    $duration = $currentEvent['start']->diffInSeconds($data->timestamp);
                    
                    // Calculate average energy loss during the open period
                    $avgEnergyLoss = count($energyRates) > 0 
                        ? array_sum($energyRates) / count($energyRates)
                        : 0;
                    
                    $energyLoss = $avgEnergyLoss * ($duration / 3600); // Convert to Wh
                    
                    $currentEvent['end'] = $data->timestamp;
                    $currentEvent['duration'] = $duration;
                    $currentEvent['total_energy_wh'] = $energyLoss;
                    $currentEvent['avg_power_watts'] = $avgEnergyLoss;
                    $currentEvent['ongoing'] = false;
                    
                    $events[] = $currentEvent;
                    $sensorEvents[$sensorId][] = $currentEvent;
                    
                    $totalEnergyLoss += $energyLoss;
                    $totalDuration += $duration;
                    
                    $currentEvent = null;
                    $energyRates = [];
                }
                
                $previousState = $currentState;
            }
            
            // Handle ongoing events (doors still open)
            if ($currentEvent && $currentEvent['ongoing']) {
                $duration = $currentEvent['start']->diffInSeconds(now());
                
                // Use average energy rate from collected rates or 0
                $avgEnergyLoss = count($energyRates) > 0 
                    ? array_sum($energyRates) / count($energyRates)
                    : 0;
                    
                $energyLoss = $avgEnergyLoss * ($duration / 3600);
                
                $currentEvent['end'] = now();
                $currentEvent['duration'] = $duration;
                $currentEvent['total_energy_wh'] = $energyLoss;
                $currentEvent['avg_power_watts'] = $avgEnergyLoss;
                
                $events[] = $currentEvent;
                $sensorEvents[$sensorId][] = $currentEvent;
                
                $totalEnergyLoss += $energyLoss;
                $totalDuration += $duration;
            }
        }
        
        return [
            'total_energy_loss_wh' => round($totalEnergyLoss, 2),
            'total_energy_loss_kwh' => round($totalEnergyLoss / 1000, 3),
            'total_duration_seconds' => $totalDuration,
            'total_duration_hours' => round($totalDuration / 3600, 2),
            'average_power_loss_watts' => $totalDuration > 0 
                ? round(($totalEnergyLoss / ($totalDuration / 3600)), 2)
                : 0,
            'estimated_cost' => $this->calculateCost($totalEnergyLoss / 1000),
            'events' => $events,
            'event_count' => count($events),
            'sensors_with_events' => count($sensorEvents),
        ];
    }
    
    /**
     * Calculate cost of energy loss
     */
    public function calculateCost(float $energyKwh, float $pricePerKwh = null): float
    {
        if ($pricePerKwh === null) {
            $pricePerKwh = config('energy.price_per_kwh', 0.15);
        }
        
        return round($energyKwh * $pricePerKwh, 2);
    }
    
    /**
     * Calculate potential savings if doors/windows were closed
     */
    public function calculatePotentialSavings(Room $room, int $days = 30): array
    {
        $analysis = $this->calculateCumulativeEnergyLoss($room, $days * 24);
        
        $dailyAverage = $analysis['total_energy_loss_kwh'] / max(1, $days);
        $yearlyProjection = $dailyAverage * 365;
        $yearlyCost = $this->calculateCost($yearlyProjection);
        
        // Calculate CO2 emissions (average: 0.475 kg CO2 per kWh)
        $co2EmissionsKg = $yearlyProjection * 0.475;
        
        return [
            'daily_average_kwh' => round($dailyAverage, 3),
            'monthly_projection_kwh' => round($dailyAverage * 30, 2),
            'yearly_projection_kwh' => round($yearlyProjection, 2),
            'daily_cost' => $this->calculateCost($dailyAverage),
            'monthly_cost' => $this->calculateCost($dailyAverage * 30),
            'yearly_cost' => $yearlyCost,
            'co2_emissions_kg_yearly' => round($co2EmissionsKg, 2),
            'trees_equivalent' => round($co2EmissionsKg / 21.77, 1), // One tree absorbs ~21.77 kg CO2/year
            'improvement_suggestions' => $this->generateImprovementSuggestions($analysis),
        ];
    }
    
    /**
     * Generate improvement suggestions based on energy loss patterns
     */
    private function generateImprovementSuggestions(array $analysis): array
    {
        $suggestions = [];
        
        if ($analysis['event_count'] > 10) {
            $suggestions[] = [
                'priority' => 'high',
                'suggestion' => 'Install automatic door closers',
                'potential_savings' => '30-40%',
                'description' => 'Frequent door opening events detected. Automatic closers can reduce open time.',
            ];
        }
        
        if ($analysis['average_power_loss_watts'] > 500) {
            $suggestions[] = [
                'priority' => 'high',
                'suggestion' => 'Improve insulation around openings',
                'potential_savings' => '20-30%',
                'description' => 'High energy loss rate detected. Better insulation can reduce heat transfer.',
            ];
        }
        
        if ($analysis['total_duration_hours'] > 2) {
            $suggestions[] = [
                'priority' => 'medium',
                'suggestion' => 'Implement door/window monitoring alerts',
                'potential_savings' => '40-50%',
                'description' => 'Long duration open events detected. Real-time alerts can prompt quick action.',
            ];
        }
        
        $suggestions[] = [
            'priority' => 'low',
            'suggestion' => 'Employee awareness training',
            'potential_savings' => '10-20%',
            'description' => 'Regular training can improve energy-conscious behavior.',
        ];
        
        return $suggestions;
    }
    
    /**
     * Compare energy efficiency between rooms
     */
    public function compareRoomEfficiency(array $roomIds, int $days = 7): array
    {
        $comparisons = [];
        
        foreach ($roomIds as $roomId) {
            $room = Room::find($roomId);
            if (!$room) continue;
            
            $analysis = $this->calculateCumulativeEnergyLoss($room, $days * 24);
            $efficiency = $this->calculateEfficiencyScore($analysis, $room->surface_m2);
            
            $comparisons[] = [
                'room_id' => $roomId,
                'room_name' => $room->name,
                'surface_m2' => $room->surface_m2,
                'total_energy_loss_kwh' => $analysis['total_energy_loss_kwh'],
                'energy_loss_per_m2' => round($analysis['total_energy_loss_kwh'] / max(1, $room->surface_m2), 3),
                'efficiency_score' => $efficiency,
                'rating' => $this->getEfficiencyRating($efficiency),
            ];
        }
        
        // Sort by efficiency score
        usort($comparisons, fn($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);
        
        return $comparisons;
    }
    
    /**
     * Calculate efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $analysis, float $surfaceArea): int
    {
        // Base score
        $score = 100;
        
        // Deduct points based on energy loss per m²
        $lossPerM2 = $analysis['total_energy_loss_kwh'] / max(1, $surfaceArea);
        $score -= min(50, $lossPerM2 * 10);
        
        // Deduct points for frequency of events
        $score -= min(30, $analysis['event_count'] * 2);
        
        // Deduct points for duration
        $score -= min(20, $analysis['total_duration_hours'] * 2);
        
        return max(0, round($score));
    }
    
    /**
     * Get efficiency rating based on score
     */
    private function getEfficiencyRating(int $score): string
    {
        return match(true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Poor',
            default => 'Critical',
        };
    }
    
    /**
     * Get current wind speed (placeholder - integrate with weather API)
     */
    private function getWindSpeed(): float
    {
        return Cache::remember('wind_speed', now()->addHours(1), function () {
            // TODO: Integrate with weather API
            return 3.0; // Default wind speed in m/s
        });
    }
    
    /**
     * Get average indoor humidity
     */
    private function getIndoorHumidity(): float
    {
        return Cache::remember('avg_indoor_humidity', now()->addMinutes(30), function () {
            $avgHumidity = SensorData::where('timestamp', '>=', now()->subHours(1))
                ->whereNotNull('humidity')
                ->avg('humidity');
            
            return $avgHumidity ?: 50.0; // Default humidity
        });
    }
}