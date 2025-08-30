<?php

namespace App\HealthChecks;

use App\Models\Sensor;
use App\Models\SensorData;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Carbon\Carbon;

class SensorDataFreshnessCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        
        try {
            $activeSensors = Sensor::where('is_active', true)->count();
            $recentDataThreshold = now()->subMinutes(10); // Data should be fresh within 10 minutes
            $veryRecentThreshold = now()->subMinutes(5); // Very fresh data within 5 minutes
            
            // Count sensors with recent data
            $sensorsWithRecentData = Sensor::where('is_active', true)
                ->whereHas('sensorData', function ($query) use ($recentDataThreshold) {
                    $query->where('created_at', '>=', $recentDataThreshold);
                })
                ->count();
                
            // Count sensors with very recent data
            $sensorsWithVeryRecentData = Sensor::where('is_active', true)
                ->whereHas('sensorData', function ($query) use ($veryRecentThreshold) {
                    $query->where('created_at', '>=', $veryRecentThreshold);
                })
                ->count();
            
            // Get oldest sensor data timestamp
            $oldestRecentData = SensorData::where('created_at', '>=', $recentDataThreshold)
                ->min('created_at');
                
            $freshnessPercentage = $activeSensors > 0 ? 
                ($sensorsWithRecentData / $activeSensors) * 100 : 100;
            
            $veryFreshPercentage = $activeSensors > 0 ? 
                ($sensorsWithVeryRecentData / $activeSensors) * 100 : 100;
            
            if ($freshnessPercentage >= 90) {
                return $result->ok("Sensor data is fresh")
                    ->shortSummary("{$sensorsWithRecentData}/{$activeSensors} sensors reporting")
                    ->meta([
                        'active_sensors' => $activeSensors,
                        'sensors_with_recent_data' => $sensorsWithRecentData,
                        'sensors_with_very_recent_data' => $sensorsWithVeryRecentData,
                        'freshness_percentage' => round($freshnessPercentage, 1),
                        'very_fresh_percentage' => round($veryFreshPercentage, 1),
                        'oldest_recent_data' => $oldestRecentData ? Carbon::parse($oldestRecentData)->diffForHumans() : 'No recent data',
                    ]);
            } elseif ($freshnessPercentage >= 70) {
                return $result->warning("Some sensors have stale data")
                    ->shortSummary("{$sensorsWithRecentData}/{$activeSensors} sensors reporting")
                    ->meta([
                        'active_sensors' => $activeSensors,
                        'sensors_with_recent_data' => $sensorsWithRecentData,
                        'freshness_percentage' => round($freshnessPercentage, 1),
                        'stale_sensors' => $activeSensors - $sensorsWithRecentData,
                    ]);
            } else {
                return $result->failed("Many sensors have stale or missing data")
                    ->shortSummary("Only {$sensorsWithRecentData}/{$activeSensors} sensors reporting")
                    ->meta([
                        'active_sensors' => $activeSensors,
                        'sensors_with_recent_data' => $sensorsWithRecentData,
                        'freshness_percentage' => round($freshnessPercentage, 1),
                        'stale_sensors' => $activeSensors - $sensorsWithRecentData,
                    ]);
            }
            
        } catch (\Exception $e) {
            return $result->failed("Error checking sensor data freshness: {$e->getMessage()}")
                ->shortSummary("Health check failed")
                ->meta(['error' => $e->getMessage()]);
        }
    }
}