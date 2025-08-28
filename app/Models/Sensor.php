<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Sensor extends Model
{
    use HasFactory, HasUuid;
    
    protected $fillable = [
        'room_id',
        'mac_address',
        'source_address',
        'sensor_type_id',
        'name',
        'type',
        'position',
        'battery_level',
        'calibration_data',
        'temperature_offset',
        'humidity_offset',
        'is_active',
        'last_seen_at',
    ];
    
    protected $casts = [
        'battery_level' => 'integer',
        'calibration_data' => 'array',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];
    
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
    
    public function sensorData(): HasMany
    {
        return $this->hasMany(SensorData::class);
    }
    
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
    
    public function latestData(): HasOne
    {
        return $this->hasOne(SensorData::class)->latestOfMany('timestamp');
    }
    
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
    
    public function updateBatteryLevel(int $level): void
    {
        $this->update(['battery_level' => $level]);
        
        if ($level < 20) {
            $this->createBatteryLowEvent();
        }
    }
    
    protected function createBatteryLowEvent(): void
    {
        $this->events()->create([
            'room_id' => $this->room_id,
            'type' => 'battery_low',
            'severity' => $this->battery_level < 10 ? 'critical' : 'warning',
            'message' => "Battery level is low: {$this->battery_level}%",
            'data' => ['battery_level' => $this->battery_level],
        ]);
    }
    
    public function getCachedLatestData()
    {
        return Cache::remember(
            "sensor_{$this->id}_latest_data",
            now()->addMinutes(5),
            fn() => $this->latestData
        );
    }
    
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 10;
    }
    
    public function getCalibrationValue(string $key, $default = null)
    {
        return data_get($this->calibration_data, $key, $default);
    }
    
    public function calibrateTemperature(float $value): float
    {
        // Use dedicated temperature_offset column if available, fallback to calibration_data
        $offset = $this->temperature_offset ?? $this->getCalibrationValue('temperature_offset', 0);
        $multiplier = $this->getCalibrationValue('temperature_multiplier', 1);
        
        return ($value * $multiplier) + $offset;
    }
    
    public function calibrateHumidity(float $value): float
    {
        // Use dedicated humidity_offset column if available, fallback to calibration_data
        $offset = $this->humidity_offset ?? $this->getCalibrationValue('humidity_offset', 0);
        $multiplier = $this->getCalibrationValue('humidity_multiplier', 1);
        
        return ($value * $multiplier) + $offset;
    }
}