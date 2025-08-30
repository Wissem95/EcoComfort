<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class CalibrationData extends Data
{
    public function __construct(
        public array $closedReference, // ['x' => int, 'y' => int, 'z' => int]
        public float $tolerance,
        public float $dataStability,
        public ?Carbon $calibratedAt = null,
        public ?int $calibratedBy = null,
        public ?array $history = null,
    ) {
        $this->calibratedAt = $this->calibratedAt ?? now();
        $this->history = $this->history ?? [];
    }

    public function isWithinTolerance(AccelerometerData $current): bool
    {
        $wirepasData = $current->toWirepasScale();
        
        $dx = abs($wirepasData['x'] - $this->closedReference['x']);
        $dy = abs($wirepasData['y'] - $this->closedReference['y']);
        $dz = abs($wirepasData['z'] - $this->closedReference['z']);
        
        return $dx <= $this->tolerance && 
               $dy <= $this->tolerance && 
               $dz <= $this->tolerance;
    }

    public function calculateMaxDifference(AccelerometerData $current): float
    {
        $wirepasData = $current->toWirepasScale();
        
        $dx = abs($wirepasData['x'] - $this->closedReference['x']);
        $dy = abs($wirepasData['y'] - $this->closedReference['y']);
        $dz = abs($wirepasData['z'] - $this->closedReference['z']);
        
        return max($dx, $dy, $dz);
    }

    public function updateDynamicReference(AccelerometerData $newPosition, float $weight = 0.1): self
    {
        $newWirepasData = $newPosition->toWirepasScale();
        
        // Weighted average: 90% old, 10% new (or custom weight)
        $newReference = [
            'x' => round($this->closedReference['x'] * (1 - $weight) + $newWirepasData['x'] * $weight, 2),
            'y' => round($this->closedReference['y'] * (1 - $weight) + $newWirepasData['y'] * $weight, 2),
            'z' => round($this->closedReference['z'] * (1 - $weight) + $newWirepasData['z'] * $weight, 2)
        ];

        // Only update if change is small enough (prevent sudden jumps)
        $maxChange = max(
            abs($newReference['x'] - $this->closedReference['x']),
            abs($newReference['y'] - $this->closedReference['y']),
            abs($newReference['z'] - $this->closedReference['z'])
        );

        if ($maxChange <= 0.5) {
            return new self(
                closedReference: $newReference,
                tolerance: $this->tolerance,
                dataStability: $this->dataStability,
                calibratedAt: $this->calibratedAt,
                calibratedBy: $this->calibratedBy,
                history: array_merge($this->history, [
                    [
                        'type' => 'dynamic_update',
                        'old_reference' => $this->closedReference,
                        'new_reference' => $newReference,
                        'max_change' => $maxChange,
                        'timestamp' => now()->toISOString()
                    ]
                ])
            );
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'door_position' => [
                'closed_reference' => $this->closedReference,
                'tolerance' => $this->tolerance,
                'calibrated_at' => $this->calibratedAt->toISOString(),
                'calibrated_by' => $this->calibratedBy,
                'data_stability' => $this->dataStability,
                'last_updated' => now()->toISOString()
            ],
            'history' => $this->history
        ];
    }
}