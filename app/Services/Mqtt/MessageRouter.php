<?php

namespace App\Services\Mqtt;

use App\Data\MqttMessageData;
use App\Services\Mqtt\Processors\TemperatureProcessor;
use App\Services\Mqtt\Processors\HumidityProcessor;
use App\Services\Mqtt\Processors\MovementProcessor;
use App\Services\Mqtt\Processors\BatteryProcessor;
use App\Services\Mqtt\Processors\PressureProcessor;
use Illuminate\Support\Facades\Log;

class MessageRouter
{
    public function __construct(
        private TemperatureProcessor $temperatureProcessor,
        private HumidityProcessor $humidityProcessor,
        private MovementProcessor $movementProcessor,
        private BatteryProcessor $batteryProcessor,
        private PressureProcessor $pressureProcessor,
    ) {}

    public function route(MqttMessageData $message): void
    {
        $startTime = microtime(true);
        
        try {
            match($message->getDataType()) {
                'temperature' => $this->temperatureProcessor->process($message),
                'humidity' => $this->humidityProcessor->process($message),
                'movement' => $this->movementProcessor->process($message),
                'battery' => $this->batteryProcessor->process($message),
                'pressure' => $this->pressureProcessor->process($message),
                'neighbors' => $this->handleNeighbors($message),
                default => $this->handleUnknown($message),
            };
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            
            Log::debug("MQTT message routed and processed", [
                'data_type' => $message->getDataType(),
                'source_address' => $message->sourceAddress,
                'processing_time_ms' => round($processingTime, 2)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error routing MQTT message", [
                'data_type' => $message->getDataType(),
                'source_address' => $message->sourceAddress,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function handleNeighbors(MqttMessageData $message): void
    {
        // Handle neighbor discovery data
        $payload = $message->payload;
        $neighborData = $payload['data'] ?? $payload;
        
        Log::debug("Neighbor data received", [
            'source_address' => $message->sourceAddress,
            'neighbors' => $neighborData
        ]);
        
        // Could store neighbor information for network topology analysis
        // This is useful for understanding sensor network connectivity
    }

    private function handleUnknown(MqttMessageData $message): void
    {
        Log::warning("Unknown MQTT message type received", [
            'topic' => $message->topic,
            'source_address' => $message->sourceAddress,
            'sensor_type_id' => $message->sensorTypeId,
            'payload_preview' => array_slice($message->payload, 0, 3, true)
        ]);
    }

    public function getProcessingStats(): array
    {
        return [
            'temperature' => $this->temperatureProcessor->getStats(),
            'humidity' => $this->humidityProcessor->getStats(),
            'movement' => $this->movementProcessor->getStats(),
            'battery' => $this->batteryProcessor->getStats(),
            'pressure' => $this->pressureProcessor->getStats(),
        ];
    }

    public function resetStats(): void
    {
        $this->temperatureProcessor->resetStats();
        $this->humidityProcessor->resetStats();
        $this->movementProcessor->resetStats();
        $this->batteryProcessor->resetStats();
        $this->pressureProcessor->resetStats();
    }
}