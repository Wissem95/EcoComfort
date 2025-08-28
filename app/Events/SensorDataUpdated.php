<?php

namespace App\Events;

use App\Models\Sensor;
use App\Models\SensorData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Sensor $sensor;
    public SensorData $data;

    public function __construct(Sensor $sensor, SensorData $data)
    {
        $this->sensor = $sensor;
        $this->data = $data;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('sensor.' . $this->sensor->id),
            new PrivateChannel('room.' . $this->sensor->room_id),
            new PrivateChannel('organization.' . $this->sensor->room->building->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sensor.data.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'sensor_id' => $this->sensor->id,
            'room_id' => $this->sensor->room_id,
            'building_id' => $this->sensor->room->building_id,
            'organization_id' => $this->sensor->room->building->organization_id,
            'sensor_name' => $this->sensor->name,
            'room_name' => $this->sensor->room->name,
            'data' => [
                'timestamp' => $this->data->timestamp->toISOString(),
                'temperature' => $this->data->temperature,
                'humidity' => $this->data->humidity,
                'acceleration_x' => $this->data->acceleration_x,
                'acceleration_y' => $this->data->acceleration_y,
                'acceleration_z' => $this->data->acceleration_z,
                'door_state' => $this->data->door_state,
                'energy_loss_watts' => $this->data->energy_loss_watts,
            ],
            'updated_at' => now()->toISOString(),
        ];
    }
}