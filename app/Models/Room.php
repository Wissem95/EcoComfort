<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, HasUuid;
    
    protected $fillable = [
        'building_id',
        'name',
        'floor',
        'surface_m2',
        'type',
        'target_temperature',
        'target_humidity',
    ];
    
    protected $casts = [
        'floor' => 'integer',
        'surface_m2' => 'decimal:2',
        'target_temperature' => 'decimal:1',
        'target_humidity' => 'decimal:1',
    ];
    
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
    
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }
    
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
    
    public function getLatestTemperatureAttribute(): ?float
    {
        $latestData = $this->sensors()
            ->where('is_active', true)
            ->with(['latestData'])
            ->get()
            ->pluck('latestData')
            ->filter()
            ->sortByDesc('timestamp')
            ->first();
            
        return $latestData?->temperature;
    }
    
    public function getLatestHumidityAttribute(): ?float
    {
        $latestData = $this->sensors()
            ->where('is_active', true)
            ->with(['latestData'])
            ->get()
            ->pluck('latestData')
            ->filter()
            ->sortByDesc('timestamp')
            ->first();
            
        return $latestData?->humidity;
    }
    
    public function hasOpenDoor(): bool
    {
        return $this->sensors()
            ->where('position', 'door')
            ->where('is_active', true)
            ->whereHas('latestData', function ($query) {
                $query->where('door_state', true);
            })
            ->exists();
    }
    
    public function hasOpenWindow(): bool
    {
        return $this->sensors()
            ->where('position', 'window')
            ->where('is_active', true)
            ->whereHas('latestData', function ($query) {
                $query->where('door_state', true);
            })
            ->exists();
    }
}