<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class MqttMessageData extends Data
{
    public function __construct(
        public string $topic,
        public array $payload,
        public int $sourceAddress,
        public int $sensorTypeId,
        public ?int $txTimeMs = null,
        public ?int $eventId = null,
        public ?Carbon $receivedAt = null,
    ) {
        $this->receivedAt = $this->receivedAt ?? now();
    }

    public static function fromRuuviTag(string $topic, array $data): self
    {
        // Parse topic: pws-packet/202481601481463/422801533/112
        $topicParts = explode('/', $topic);
        
        return new self(
            topic: $topic,
            payload: $data,
            sourceAddress: (int) $topicParts[2],
            sensorTypeId: (int) $topicParts[3],
            txTimeMs: $data['tx_time_ms_epoch'] ?? null,
            eventId: $data['event_id'] ?? null,
        );
    }

    public function isTemperature(): bool
    {
        return $this->sensorTypeId === 112;
    }

    public function isHumidity(): bool
    {
        return $this->sensorTypeId === 114;
    }

    public function isPressure(): bool
    {
        return $this->sensorTypeId === 116;
    }

    public function isMovement(): bool
    {
        return $this->sensorTypeId === 127;
    }

    public function isBattery(): bool
    {
        return $this->sensorTypeId === 142;
    }

    public function isNeighbors(): bool
    {
        return $this->sensorTypeId === 193;
    }

    public function getDataType(): string
    {
        return match($this->sensorTypeId) {
            112 => 'temperature',
            114 => 'humidity',
            116 => 'pressure', 
            127 => 'movement',
            142 => 'battery',
            193 => 'neighbors',
            default => 'unknown'
        };
    }

    public function extractTemperature(): ?float
    {
        if (!$this->isTemperature()) {
            return null;
        }

        return $this->payload['data']['temperature'] ?? 
               $this->payload['temperature'] ?? null;
    }

    public function extractHumidity(): ?float
    {
        if (!$this->isHumidity()) {
            return null;
        }

        return $this->payload['data']['humidity'] ?? 
               $this->payload['humidity'] ?? null;
    }

    public function extractMovementData(): ?array
    {
        if (!$this->isMovement()) {
            return null;
        }

        $data = $this->payload['data'] ?? $this->payload;
        
        return [
            'state' => $data['state'] ?? null,
            'x_axis' => $data['x_axis'] ?? null,
            'y_axis' => $data['y_axis'] ?? null,
            'z_axis' => $data['z_axis'] ?? null,
            'move_duration' => $data['move_duration'] ?? null,
            'move_number' => $data['move_number'] ?? null,
        ];
    }

    public function toSensorReading(): ?SensorReadingData
    {
        $temperature = $this->extractTemperature();
        $humidity = $this->extractHumidity();
        $movementData = $this->extractMovementData();

        if ($temperature !== null || $humidity !== null || $movementData !== null) {
            return new SensorReadingData(
                accelerationX: $movementData['x_axis'] ?? 0,
                accelerationY: $movementData['y_axis'] ?? 0,
                accelerationZ: $movementData['z_axis'] ?? 0,
                temperature: $temperature,
                humidity: $humidity,
                timestamp: $this->receivedAt
            );
        }

        return null;
    }
}