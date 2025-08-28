<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory, HasUuid;
    
    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'floors_count',
    ];
    
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'floors_count' => 'integer',
    ];
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
    
    public function getTotalSurfaceAttribute(): float
    {
        return $this->rooms->sum('surface_m2');
    }
    
    public function getActiveSensorsCountAttribute(): int
    {
        return $this->rooms->sum(function ($room) {
            return $room->sensors()->where('is_active', true)->count();
        });
    }
}