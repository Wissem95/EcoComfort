<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Boolean;
use Carbon\Carbon;

class DoorStateData extends Data
{
    public function __construct(
        #[StringType, In(['closed', 'opened', 'probably_opened'])]
        public string $state,
        
        #[StringType, In(['CERTAIN', 'PROBABLE', 'UNCERTAIN'])]
        public string $certainty,
        
        #[Numeric, Between(0.0, 100.0)]
        public float $confidence,
        
        #[Boolean]
        public bool $needsConfirmation,
        
        #[StringType, In(['door', 'window'])]
        public string $openingType,
        
        #[Numeric, Between(0.0, 90.0)]
        public float $angle,
        
        #[Numeric, Between(0.0, 3.0)]
        public float $magnitude,
        
        #[Numeric, Between(0.0, 1000.0)]
        public float $processingTimeMs,
        
        public AccelerometerData $accelerometer,
        public ?array $movementContext = null,
        public ?Carbon $timestamp = null,
    ) {
        $this->timestamp = $this->timestamp ?? now();
    }

    public function isOpen(): bool
    {
        return in_array($this->state, ['opened', 'probably_opened']);
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    public function isCertain(): bool
    {
        return $this->certainty === 'CERTAIN';
    }

    public function isProbable(): bool
    {
        return $this->certainty === 'PROBABLE';
    }

    public function isUncertain(): bool
    {
        return $this->certainty === 'UNCERTAIN';
    }

    public function toDatabase(): array
    {
        return [
            'door_state' => $this->isOpen(),
            'door_state_certainty' => $this->certainty,
            'needs_confirmation' => $this->needsConfirmation,
            'acceleration_x' => $this->accelerometer->toWirepasScale()['x'],
            'acceleration_y' => $this->accelerometer->toWirepasScale()['y'],
            'acceleration_z' => $this->accelerometer->toWirepasScale()['z'],
        ];
    }

    public function toBroadcast(): array
    {
        return [
            'door_state' => $this->state,
            'certainty' => $this->certainty,
            'confidence' => $this->confidence,
            'needs_confirmation' => $this->needsConfirmation,
            'opening_type' => $this->openingType,
            'angle' => $this->angle,
            'magnitude' => $this->magnitude,
            'processing_time_ms' => $this->processingTimeMs,
            'timestamp' => $this->timestamp->toISOString(),
        ];
    }
}