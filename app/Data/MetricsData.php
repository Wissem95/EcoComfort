<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Between;
use Carbon\Carbon;

class MetricsData extends Data
{
    public function __construct(
        #[Numeric, Between(0.0, 1000.0)]
        public float $averageProcessingTimeMs,
        
        #[Numeric, Between(0.0, 100.0)]
        public float $accuracyPercentage,
        
        #[Numeric, Between(0.0, 100.0)]
        public float $confidenceScore,
        
        #[Numeric, Between(0, 1000000)]
        public int $totalDetections,
        
        #[Numeric, Between(0, 1000000)]
        public int $successfulDetections,
        
        #[Numeric, Between(0, 1000000)]
        public int $errorCount,
        
        public array $stateDistribution,
        public array $performanceStats,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public string $sensorId,
    ) {}

    public function toArray(): array
    {
        return [
            'average_processing_time_ms' => $this->averageProcessingTimeMs,
            'accuracy_percentage' => $this->accuracyPercentage,
            'confidence_score' => $this->confidenceScore,
            'total_detections' => $this->totalDetections,
            'successful_detections' => $this->successfulDetections,
            'error_count' => $this->errorCount,
            'error_rate' => $this->getErrorRate(),
            'success_rate' => $this->getSuccessRate(),
            'state_distribution' => $this->stateDistribution,
            'performance_stats' => $this->performanceStats,
            'period_start' => $this->periodStart->toISOString(),
            'period_end' => $this->periodEnd->toISOString(),
            'sensor_id' => $this->sensorId,
        ];
    }

    public function toDatabase(): array
    {
        return [
            'sensor_id' => $this->sensorId,
            'average_processing_time_ms' => $this->averageProcessingTimeMs,
            'accuracy_percentage' => $this->accuracyPercentage,
            'confidence_score' => $this->confidenceScore,
            'total_detections' => $this->totalDetections,
            'successful_detections' => $this->successfulDetections,
            'error_count' => $this->errorCount,
            'state_distribution' => json_encode($this->stateDistribution),
            'performance_stats' => json_encode($this->performanceStats),
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
        ];
    }

    public function getErrorRate(): float
    {
        if ($this->totalDetections === 0) return 0.0;
        return ($this->errorCount / $this->totalDetections) * 100;
    }

    public function getSuccessRate(): float
    {
        if ($this->totalDetections === 0) return 100.0;
        return ($this->successfulDetections / $this->totalDetections) * 100;
    }

    public function isPerformant(): bool
    {
        return $this->averageProcessingTimeMs < 25.0 && 
               $this->getSuccessRate() > 95.0 &&
               $this->accuracyPercentage > 90.0;
    }

    public function needsAttention(): bool
    {
        return $this->averageProcessingTimeMs > 50.0 || 
               $this->getErrorRate() > 5.0 ||
               $this->accuracyPercentage < 80.0;
    }

    public function getPerformanceGrade(): string
    {
        if ($this->isPerformant()) return 'excellent';
        if ($this->averageProcessingTimeMs < 50.0 && $this->getSuccessRate() > 90.0) return 'good';
        if ($this->averageProcessingTimeMs < 100.0 && $this->getSuccessRate() > 85.0) return 'acceptable';
        return 'poor';
    }
}