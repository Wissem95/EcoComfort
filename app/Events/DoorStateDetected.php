<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorStateDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sensorId;
    public string $doorState;
    public array $position;
    public float $confidence;
    public ?array $energyImpact;

    public function __construct(
        int $sensorId,
        string $doorState,
        array $position,
        float $confidence,
        ?array $energyImpact = null
    ) {
        $this->sensorId = $sensorId;
        $this->doorState = $doorState;
        $this->position = $position;
        $this->confidence = $confidence;
        $this->energyImpact = $energyImpact;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("sensor.{$this->sensorId}");
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'door.state_detected',
            'sensor_id' => $this->sensorId,
            'door_state' => $this->doorState,
            'position' => $this->position,
            'confidence' => $this->confidence,
            'energy_impact' => $this->energyImpact
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'door.state_detected';
    }
}