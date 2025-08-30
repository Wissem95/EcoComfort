<?php

namespace App\Services\Mqtt\Processors;

abstract class BaseProcessor
{
    private array $stats = [
        'processed_count' => 0,
        'error_count' => 0,
        'total_processing_time_ms' => 0,
        'average_processing_time_ms' => 0,
        'last_processed_at' => null,
        'last_error_at' => null,
        'last_error_message' => null,
    ];

    abstract public function process(\App\Data\MqttMessageData $message): void;

    protected function recordProcessingSuccess(float $processingTimeMs): void
    {
        $this->stats['processed_count']++;
        $this->stats['total_processing_time_ms'] += $processingTimeMs;
        $this->stats['average_processing_time_ms'] = $this->stats['total_processing_time_ms'] / $this->stats['processed_count'];
        $this->stats['last_processed_at'] = now()->toISOString();
    }

    protected function recordProcessingError(\Exception $e): void
    {
        $this->stats['error_count']++;
        $this->stats['last_error_at'] = now()->toISOString();
        $this->stats['last_error_message'] = $e->getMessage();
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function resetStats(): void
    {
        $this->stats = [
            'processed_count' => 0,
            'error_count' => 0,
            'total_processing_time_ms' => 0,
            'average_processing_time_ms' => 0,
            'last_processed_at' => null,
            'last_error_at' => null,
            'last_error_message' => null,
        ];
    }

    public function getSuccessRate(): float
    {
        $total = $this->stats['processed_count'] + $this->stats['error_count'];
        return $total > 0 ? ($this->stats['processed_count'] / $total) * 100 : 100.0;
    }

    public function getAverageProcessingTime(): float
    {
        return $this->stats['average_processing_time_ms'];
    }
}