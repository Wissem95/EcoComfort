<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalibrationFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sensorId;
    public string $error;
    public string $message;
    public ?string $retrySuggestedAt;

    public function __construct(string $sensorId, string $error, string $message, ?string $retrySuggestedAt = null)
    {
        $this->sensorId = $sensorId;
        $this->error = $error;
        $this->message = $message;
        $this->retrySuggestedAt = $retrySuggestedAt ?? now()->addMinutes(1)->toISOString();
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
            'event' => 'calibration.failed',
            'sensor_id' => $this->sensorId,
            'error' => $this->error,
            'message' => $this->message,
            'retry_suggested_at' => $this->retrySuggestedAt
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'calibration.failed';
    }
}