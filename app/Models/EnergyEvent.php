<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergyEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sensor_id',
        'start_time',
        'end_time',
        'total_energy_kwh',
        'total_cost_euros',
        'average_power_watts',
        'duration_seconds',
        'avg_indoor_temp',
        'outdoor_temp',
        'delta_temp',
        'is_ongoing',
        'detection_method',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_energy_kwh' => 'decimal:3',
        'total_cost_euros' => 'decimal:2',
        'average_power_watts' => 'decimal:2',
        'avg_indoor_temp' => 'decimal:2',
        'outdoor_temp' => 'decimal:2',
        'delta_temp' => 'decimal:2',
        'is_ongoing' => 'boolean',
    ];

    /**
     * Get the sensor that owns the energy event
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Check if the event is still ongoing
     */
    public function isOngoing(): bool
    {
        return $this->is_ongoing && $this->end_time === null;
    }

    /**
     * Get the actual duration (real-time if ongoing)
     */
    public function getCurrentDuration(): int
    {
        if ($this->isOngoing()) {
            return $this->start_time->diffInSeconds(now());
        }

        return $this->duration_seconds;
    }

    /**
     * Get current energy loss (real-time if ongoing)
     */
    public function getCurrentEnergyLoss(): float
    {
        if ($this->isOngoing()) {
            $durationHours = $this->getCurrentDuration() / 3600;
            return $this->average_power_watts * $durationHours / 1000; // Convert to kWh
        }

        return (float) $this->total_energy_kwh;
    }

    /**
     * Get current cost (real-time if ongoing)
     */
    public function getCurrentCost(): float
    {
        if ($this->isOngoing()) {
            return $this->getCurrentEnergyLoss() * 0.1740; // EDF tariff
        }

        return (float) $this->total_cost_euros;
    }

    /**
     * Scope for ongoing events
     */
    public function scopeOngoing($query)
    {
        return $query->where('is_ongoing', true)->whereNull('end_time');
    }

    /**
     * Scope for completed events
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_ongoing', false)->whereNotNull('end_time');
    }
}