<?php

namespace App\Events;

use App\Data\DoorStateData;
use App\Data\EnergyImpactData;
use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DoorStateData $doorState,
        public Sensor $sensor,
        public ?SensorData $sensorData = null,
        public ?array $previousState = null,
        public ?string $reason = 'detection',
        public ?EnergyImpactData $energyImpact = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sensor.{$this->sensor->id}"),
            new PrivateChannel("organization.{$this->sensor->room->building->organization_id}"),
            new Channel("door.state.changes"), // Public channel for general monitoring
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sensor_id' => $this->sensor->id,
            'sensor' => [
                'id' => $this->sensor->id,
                'name' => $this->sensor->name,
                'position' => $this->sensor->position,
                'room' => [
                    'id' => $this->sensor->room->id,
                    'name' => $this->sensor->room->name,
                    'building_name' => $this->sensor->room->building->name,
                ]
            ],
            'door_state' => $this->doorState->toBroadcast(),
            'previous_state' => $this->previousState,
            'reason' => $this->reason,
            'energy_impact' => $this->energyImpact ? $this->energyImpact->toArray() : $this->calculateEnergyImpact(),
            'timestamp' => $this->doorState->timestamp->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'door.state.changed';
    }

    /**
     * Calculate estimated energy loss per hour for this door state
     */
    private function calculateEnergyImpact(): ?array
    {
        if (!$this->doorState->isOpen()) {
            return [
                'loss_rate_watts' => 0.0,
                'cost_per_hour' => 0.0,
                'co2_per_hour' => 0.0
            ];
        }

        $room = $this->sensor->room;
        
        // Conservative estimation for energy loss
        $surfaceM2 = $room->surface_m2 ?? 20; // Default room size
        $thermalCoefficient = 2.5; // W/m²°C (window/door opening)
        $temperatureDiff = 8; // °C (conservative estimate)
        
        $lossRateWatts = $surfaceM2 * $thermalCoefficient * $temperatureDiff;
        $kwhPrice = 0.174; // €/kWh (EDF tariff)
        $co2Factor = 0.056; // kg CO2/kWh
        
        return [
            'loss_rate_watts' => round($lossRateWatts, 2),
            'cost_per_hour' => round(($lossRateWatts / 1000) * $kwhPrice, 4),
            'co2_per_hour' => round(($lossRateWatts / 1000) * $co2Factor, 4) // kg CO2
        ];
    }

    /**
     * Determine if this is a state change event
     */
    public function isStateChange(): bool
    {
        if (!$this->previousState) {
            return true; // First detection counts as a change
        }

        return $this->previousState['door_state'] !== $this->doorState->state;
    }

    /**
     * Determine if this is a certainty change event
     */
    public function isCertaintyChange(): bool
    {
        if (!$this->previousState) {
            return true;
        }

        return $this->previousState['certainty'] !== $this->doorState->certainty ||
               $this->previousState['needs_confirmation'] !== $this->doorState->needsConfirmation;
    }

    /**
     * Create event from detection
     */
    public static function fromDetection(
        DoorStateData $doorState,
        Sensor $sensor,
        ?array $previousState = null,
        ?EnergyImpactData $energyImpact = null
    ): self {
        return new self(
            doorState: $doorState,
            sensor: $sensor,
            previousState: $previousState,
            reason: 'detection',
            energyImpact: $energyImpact
        );
    }

    /**
     * Create event from user confirmation
     */
    public static function fromUserConfirmation(
        DoorStateData $doorState,
        Sensor $sensor,
        SensorData $sensorData,
        array $previousState
    ): self {
        return new self(
            doorState: $doorState,
            sensor: $sensor,
            sensorData: $sensorData,
            previousState: $previousState,
            reason: 'user_confirmation'
        );
    }

    /**
     * Create event from calibration
     */
    public static function fromCalibration(
        DoorStateData $doorState,
        Sensor $sensor,
        ?array $previousState = null
    ): self {
        return new self(
            doorState: $doorState,
            sensor: $sensor,
            previousState: $previousState,
            reason: 'calibration'
        );
    }
}