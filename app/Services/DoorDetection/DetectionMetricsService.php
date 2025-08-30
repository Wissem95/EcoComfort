<?php

namespace App\Services\DoorDetection;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DetectionMetricsService
{
    private const METRICS_CACHE_TTL = 3600; // 1 hour
    
    public function recordDetection(string $sensorId, array $detectionResult): void
    {
        $metricsKey = "detection_metrics:{$sensorId}";
        $metrics = Cache::get($metricsKey, $this->initializeMetrics());
        
        $metrics['total_detections']++;
        $metrics['processing_times'][] = $detectionResult['processing_time_ms'];
        $metrics['confidence_scores'][] = $detectionResult['confidence'];
        
        // Keep only last 100 measurements for performance
        if (count($metrics['processing_times']) > 100) {
            $metrics['processing_times'] = array_slice($metrics['processing_times'], -100);
            $metrics['confidence_scores'] = array_slice($metrics['confidence_scores'], -100);
        }
        
        $metrics['average_processing_time'] = array_sum($metrics['processing_times']) / count($metrics['processing_times']);
        $metrics['average_confidence'] = array_sum($metrics['confidence_scores']) / count($metrics['confidence_scores']);
        
        // Track states
        $state = $detectionResult['state'];
        $metrics['state_counts'][$state] = ($metrics['state_counts'][$state] ?? 0) + 1;
        
        Cache::put($metricsKey, $metrics, self::METRICS_CACHE_TTL);
        
        // Log performance warnings
        if ($detectionResult['processing_time_ms'] > 25) {
            Log::warning("Slow door detection", [
                'sensor_id' => $sensorId,
                'processing_time_ms' => $detectionResult['processing_time_ms'],
                'threshold' => 25
            ]);
        }
    }
    
    public function getMetrics(string $sensorId): array
    {
        $metricsKey = "detection_metrics:{$sensorId}";
        return Cache::get($metricsKey, $this->initializeMetrics());
    }
    
    public function getGlobalMetrics(): array
    {
        // Aggregate metrics across all sensors
        $pattern = "detection_metrics:*";
        $keys = Cache::getRedis()->keys($pattern);
        
        $globalMetrics = $this->initializeMetrics();
        
        foreach ($keys as $key) {
            $sensorMetrics = Cache::get($key, []);
            if (empty($sensorMetrics)) continue;
            
            $globalMetrics['total_detections'] += $sensorMetrics['total_detections'] ?? 0;
            
            if (!empty($sensorMetrics['processing_times'])) {
                $globalMetrics['processing_times'] = array_merge(
                    $globalMetrics['processing_times'],
                    $sensorMetrics['processing_times']
                );
            }
            
            if (!empty($sensorMetrics['confidence_scores'])) {
                $globalMetrics['confidence_scores'] = array_merge(
                    $globalMetrics['confidence_scores'],
                    $sensorMetrics['confidence_scores']
                );
            }
            
            foreach ($sensorMetrics['state_counts'] ?? [] as $state => $count) {
                $globalMetrics['state_counts'][$state] = ($globalMetrics['state_counts'][$state] ?? 0) + $count;
            }
        }
        
        // Recalculate averages
        if (!empty($globalMetrics['processing_times'])) {
            $globalMetrics['average_processing_time'] = array_sum($globalMetrics['processing_times']) / count($globalMetrics['processing_times']);
        }
        
        if (!empty($globalMetrics['confidence_scores'])) {
            $globalMetrics['average_confidence'] = array_sum($globalMetrics['confidence_scores']) / count($globalMetrics['confidence_scores']);
        }
        
        return $globalMetrics;
    }
    
    private function initializeMetrics(): array
    {
        return [
            'total_detections' => 0,
            'processing_times' => [],
            'confidence_scores' => [],
            'average_processing_time' => 0.0,
            'average_confidence' => 0.0,
            'state_counts' => [
                'closed' => 0,
                'opened' => 0,
                'probably_opened' => 0,
            ],
        ];
    }
}