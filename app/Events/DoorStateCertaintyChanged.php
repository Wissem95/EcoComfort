<?php

namespace App\Events;

use App\Models\SensorData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorStateCertaintyChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SensorData $sensorData,
        public array $previousState,
        public string $reason = 'detection'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sensor.{$this->sensorData->sensor_id}"),
            new Channel("door.certainty.changes"), // Public channel for general monitoring
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sensor_id' => $this->sensorData->sensor_id,
            'door_state' => $this->sensorData->door_state ? 'opened' : 'closed',
            'certainty' => $this->sensorData->door_state_certainty,
            'needs_confirmation' => $this->sensorData->needs_confirmation,
            'energy_loss_watts' => $this->sensorData->energy_loss_watts,
            'previous_state' => $this->previousState,
            'reason' => $this->reason,
            'timestamp' => $this->sensorData->updated_at->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'door.certainty.changed';
    }
}