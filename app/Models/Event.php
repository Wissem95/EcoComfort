<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory, HasUuid;
    
    protected $fillable = [
        'sensor_id',
        'room_id',
        'type',
        'severity',
        'message',
        'data',
        'cost_impact',
        'acknowledged',
        'acknowledged_by',
        'acknowledged_at',
    ];
    
    protected $casts = [
        'data' => 'array',
        'cost_impact' => 'decimal:2',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];
    
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
    
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
    
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
    
    public function acknowledge(User $user): void
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);
        
        // Reward user with points for acknowledging the alert
        $points = match($this->severity) {
            'critical' => 10,
            'warning' => 5,
            'info' => 2,
            default => 1,
        };
        
        $user->addPoints($points, 'acknowledge_alert', "Acknowledged {$this->type} alert");
    }
    
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
    
    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }
    
    public function isInfo(): bool
    {
        return $this->severity === 'info';
    }
    
    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }
    
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
    
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}