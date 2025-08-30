<?php

namespace App\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        
        try {
            // Check door detection performance from cache
            $detectionMetrics = $this->getDetectionMetrics();
            
            // Check database query performance
            $dbPerformance = $this->checkDatabasePerformance();
            
            // Check cache performance
            $cachePerformance = $this->checkCachePerformance();
            
            $avgDetectionTime = $detectionMetrics['average_processing_time'] ?? 0;
            $detectionAccuracy = $detectionMetrics['accuracy'] ?? 100;
            
            $overallScore = $this->calculateOverallScore(
                $avgDetectionTime,
                $detectionAccuracy,
                $dbPerformance['avg_query_time'],
                $cachePerformance['avg_response_time']
            );
            
            if ($overallScore >= 90) {
                return $result->ok("System performance is excellent")
                    ->shortSummary("All systems performing optimally")
                    ->meta([
                        'overall_score' => $overallScore,
                        'detection_time_ms' => round($avgDetectionTime, 2),
                        'detection_accuracy' => round($detectionAccuracy, 1),
                        'db_performance' => $dbPerformance,
                        'cache_performance' => $cachePerformance,
                    ]);
            } elseif ($overallScore >= 70) {
                return $result->warning("Performance is acceptable but could be improved")
                    ->shortSummary("Some performance metrics need attention")
                    ->meta([
                        'overall_score' => $overallScore,
                        'detection_time_ms' => round($avgDetectionTime, 2),
                        'detection_accuracy' => round($detectionAccuracy, 1),
                        'issues' => $this->getPerformanceIssues($avgDetectionTime, $detectionAccuracy, $dbPerformance, $cachePerformance),
                    ]);
            } else {
                return $result->failed("Performance is poor")
                    ->shortSummary("Multiple performance issues detected")
                    ->meta([
                        'overall_score' => $overallScore,
                        'detection_time_ms' => round($avgDetectionTime, 2),
                        'detection_accuracy' => round($detectionAccuracy, 1),
                        'critical_issues' => $this->getPerformanceIssues($avgDetectionTime, $detectionAccuracy, $dbPerformance, $cachePerformance),
                    ]);
            }
            
        } catch (\Exception $e) {
            return $result->failed("Error checking system performance: {$e->getMessage()}")
                ->shortSummary("Performance check failed")
                ->meta(['error' => $e->getMessage()]);
        }
    }
    
    private function getDetectionMetrics(): array
    {
        // Get aggregated metrics from all sensors
        $keys = Cache::getRedis()->keys('detection_metrics:*');
        
        $totalDetections = 0;
        $totalProcessingTime = 0;
        $totalAccuracy = 0;
        $sensorCount = 0;
        
        foreach ($keys as $key) {
            $metrics = Cache::get($key, []);
            if (!empty($metrics)) {
                $totalDetections += $metrics['total_detections'] ?? 0;
                $totalProcessingTime += ($metrics['average_processing_time'] ?? 0) * ($metrics['total_detections'] ?? 1);
                $totalAccuracy += $metrics['average_confidence'] ?? 0;
                $sensorCount++;
            }
        }
        
        return [
            'total_detections' => $totalDetections,
            'average_processing_time' => $totalDetections > 0 ? $totalProcessingTime / $totalDetections : 0,
            'accuracy' => $sensorCount > 0 ? $totalAccuracy / $sensorCount : 100,
            'active_sensors' => $sensorCount,
        ];
    }
    
    private function checkDatabasePerformance(): array
    {
        $startTime = microtime(true);
        
        // Simple query to test DB responsiveness
        DB::select('SELECT COUNT(*) as count FROM sensors');
        
        $queryTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'avg_query_time' => $queryTime,
            'status' => $queryTime < 100 ? 'excellent' : ($queryTime < 500 ? 'good' : 'poor'),
        ];
    }
    
    private function checkCachePerformance(): array
    {
        $startTime = microtime(true);
        
        // Test cache read/write
        $testKey = 'health_check_' . time();
        Cache::put($testKey, 'test_value', 10);
        Cache::get($testKey);
        Cache::forget($testKey);
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'avg_response_time' => $responseTime,
            'status' => $responseTime < 10 ? 'excellent' : ($responseTime < 50 ? 'good' : 'poor'),
        ];
    }
    
    private function calculateOverallScore(float $detectionTime, float $accuracy, float $dbTime, float $cacheTime): float
    {
        $detectionScore = max(0, 100 - ($detectionTime / 25) * 50); // 25ms = target
        $accuracyScore = $accuracy;
        $dbScore = max(0, 100 - ($dbTime / 100) * 50); // 100ms = target
        $cacheScore = max(0, 100 - ($cacheTime / 10) * 50); // 10ms = target
        
        // Weighted average
        return ($detectionScore * 0.4 + $accuracyScore * 0.3 + $dbScore * 0.2 + $cacheScore * 0.1);
    }
    
    private function getPerformanceIssues(float $detectionTime, float $accuracy, array $dbPerf, array $cachePerf): array
    {
        $issues = [];
        
        if ($detectionTime > 25) {
            $issues[] = "Door detection too slow: {$detectionTime}ms (target: <25ms)";
        }
        
        if ($accuracy < 90) {
            $issues[] = "Detection accuracy too low: {$accuracy}% (target: >90%)";
        }
        
        if ($dbPerf['avg_query_time'] > 100) {
            $issues[] = "Database queries slow: {$dbPerf['avg_query_time']}ms (target: <100ms)";
        }
        
        if ($cachePerf['avg_response_time'] > 10) {
            $issues[] = "Cache response slow: {$cachePerf['avg_response_time']}ms (target: <10ms)";
        }
        
        return $issues;
    }
}