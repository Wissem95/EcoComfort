<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Event $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts'),
            new PrivateChannel('room.' . $this->event->room_id),
            new PrivateChannel('organization.' . $this->event->room->building->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alert.created';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'sensor_id' => $this->event->sensor_id,
            'room_id' => $this->event->room_id,
            'building_id' => $this->event->room->building_id,
            'organization_id' => $this->event->room->building->organization_id,
            'type' => $this->event->type,
            'severity' => $this->event->severity,
            'message' => $this->event->message,
            'cost_impact' => $this->event->cost_impact,
            'data' => $this->event->data,
            'room_name' => $this->event->room->name,
            'sensor_name' => $this->event->sensor->name,
            'created_at' => $this->event->created_at->toISOString(),
        ];
    }
}