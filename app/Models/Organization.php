<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, HasUuid;
    
    protected $fillable = [
        'name',
        'surface_m2',
        'target_percent',
    ];
    
    protected $casts = [
        'surface_m2' => 'integer',
        'target_percent' => 'decimal:2',
    ];
    
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
    
    public function getTotalRoomsAttribute(): int
    {
        return $this->buildings->sum(function ($building) {
            return $building->rooms->count();
        });
    }
    
    public function getTotalSensorsAttribute(): int
    {
        return $this->buildings->sum(function ($building) {
            return $building->rooms->sum(function ($room) {
                return $room->sensors->count();
            });
        });
    }
}