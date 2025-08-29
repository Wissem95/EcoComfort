<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'event_type',
        'position_x',
        'position_y',
        'position_z',
        'move_duration',
        'move_number',
        'door_state',
        'tx_time_ms_epoch',
        'event_id',
    ];

    protected $casts = [
        'position_x' => 'integer',
        'position_y' => 'integer',
        'position_z' => 'integer',
        'move_duration' => 'integer',
        'move_number' => 'integer',
        'tx_time_ms_epoch' => 'integer',
        'event_id' => 'integer',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Get position as array
     */
    public function getPositionAttribute(): array
    {
        return [
            'x' => $this->position_x,
            'y' => $this->position_y,
            'z' => $this->position_z,
        ];
    }

    /**
     * Scope for movement events
     */
    public function scopeMovementEvents($query)
    {
        return $query->whereIn('event_type', ['start-moving', 'stop-moving']);
    }

    /**
     * Scope for a specific sensor
     */
    public function scopeForSensor($query, $sensorId)
    {
        return $query->where('sensor_id', $sensorId);
    }
}