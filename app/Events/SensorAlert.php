<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $alertData;

    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('alerts'),
        ];

        if (isset($this->alertData['sensor_id'])) {
            $channels[] = new PrivateChannel('sensor.' . $this->alertData['sensor_id']);
        }

        if (isset($this->alertData['room_id'])) {
            $channels[] = new PrivateChannel('room.' . $this->alertData['room_id']);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'sensor.alert';
    }

    public function broadcastWith(): array
    {
        return $this->alertData;
    }
}