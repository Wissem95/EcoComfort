<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoorStateConfirmation extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'sensor_id',
        'user_id', 
        'confirmed_state',
        'previous_state',
        'previous_certainty',
        'sensor_position',
        'confidence_before',
        'user_notes'
    ];
    
    protected $casts = [
        'sensor_position' => 'array',
        'confidence_before' => 'float'
    ];
    
    /**
     * Get the sensor that was confirmed
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
    
    /**
     * Get the user who made the confirmation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
