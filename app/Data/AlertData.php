<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Carbon\Carbon;

class AlertData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $title,
        
        #[Required, StringType]
        public string $message,
        
        #[StringType, In(['info', 'warning', 'error', 'success'])]
        public string $severity,
        
        #[StringType, In(['door_state', 'energy_loss', 'battery_low', 'temperature_alert', 'humidity_alert'])]
        public string $type,
        
        public int $sensorId,
        public ?int $userId = null,
        public ?array $metadata = null,
        public ?EnergyImpactData $energyImpact = null,
        public Carbon $createdAt,
        public bool $isRead = false,
        public ?Carbon $readAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'type' => $this->type,
            'sensor_id' => $this->sensorId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'energy_impact' => $this->energyImpact?->toArray(),
            'is_read' => $this->isRead,
            'created_at' => $this->createdAt->toISOString(),
            'read_at' => $this->readAt?->toISOString(),
        ];
    }

    public function toDatabase(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'type' => $this->type,
            'sensor_id' => $this->sensorId,
            'user_id' => $this->userId,
            'metadata' => json_encode($this->metadata),
            'is_read' => $this->isRead,
            'created_at' => $this->createdAt,
            'read_at' => $this->readAt,
        ];
    }

    public function toBroadcast(): array
    {
        return [
            'id' => $this->sensorId . '_' . $this->createdAt->timestamp,
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'type' => $this->type,
            'timestamp' => $this->createdAt->toISOString(),
            'energy_impact' => $this->energyImpact ? [
                'cost' => $this->energyImpact->getFormattedCost(),
                'co2' => $this->energyImpact->getFormattedCO2(),
                'power' => $this->energyImpact->getFormattedPowerLoss(),
            ] : null,
        ];
    }

    public function markAsRead(): self
    {
        return new self(
            title: $this->title,
            message: $this->message,
            severity: $this->severity,
            type: $this->type,
            sensorId: $this->sensorId,
            userId: $this->userId,
            metadata: $this->metadata,
            energyImpact: $this->energyImpact,
            createdAt: $this->createdAt,
            isRead: true,
            readAt: now(),
        );
    }

    public function isUrgent(): bool
    {
        return $this->severity === 'error' || 
               $this->type === 'battery_low' ||
               ($this->type === 'energy_loss' && $this->energyImpact && $this->energyImpact->energyLossWatts > 500);
    }
}