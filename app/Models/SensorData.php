<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorData extends Model
{
    use HasFactory;
    
    protected $table = 'sensor_data';
    
    public $timestamps = false;
    
    protected $fillable = [
        'sensor_id',
        'timestamp',
        'temperature',
        'humidity',
        'acceleration_x',
        'acceleration_y',
        'acceleration_z',
        'door_state',
        'energy_loss_watts',
    ];
    
    protected $casts = [
        'timestamp' => 'datetime',
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'acceleration_x' => 'decimal:4',
        'acceleration_y' => 'decimal:4',
        'acceleration_z' => 'decimal:4',
        'door_state' => 'boolean',
        'energy_loss_watts' => 'decimal:2',
    ];
    
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
    
    public function getAccelerationMagnitude(): float
    {
        return sqrt(
            pow($this->acceleration_x ?? 0, 2) +
            pow($this->acceleration_y ?? 0, 2) +
            pow($this->acceleration_z ?? 0, 2)
        );
    }
    
    public function isDoorOpen(): bool
    {
        return $this->door_state === true;
    }
    
    public function hasEnergyLoss(): bool
    {
        return $this->energy_loss_watts > 0;
    }
    
    public function getEnergyLossCost(float $pricePerKwh = 0.15): float
    {
        // Convert watts to kWh (assuming per hour measurement)
        $kwh = $this->energy_loss_watts / 1000;
        return $kwh * $pricePerKwh;
    }
    
    public static function getAverageForSensor(string $sensorId, string $field, int $minutes = 60): ?float
    {
        return self::where('sensor_id', $sensorId)
            ->where('timestamp', '>=', now()->subMinutes($minutes))
            ->avg($field);
    }
    
    public static function getLatestForSensor(string $sensorId): ?self
    {
        return self::where('sensor_id', $sensorId)
            ->orderBy('timestamp', 'desc')
            ->first();
    }
}