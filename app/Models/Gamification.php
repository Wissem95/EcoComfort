<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gamification extends Model
{
    use HasFactory, HasUuid;
    
    protected $table = 'gamification';
    
    protected $fillable = [
        'user_id',
        'action',
        'points',
        'description',
        'metadata',
    ];
    
    protected $casts = [
        'points' => 'integer',
        'metadata' => 'array',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public static function getPointsForAction(string $action): int
    {
        return match($action) {
            'close_door' => 5,
            'close_window' => 5,
            'acknowledge_alert' => 3,
            'daily_login' => 2,
            'weekly_streak' => 20,
            'monthly_champion' => 100,
            'energy_saved' => 10,
            'quick_response' => 8,
            'team_goal' => 50,
            default => 1,
        };
    }
    
    public static function getDescriptionForAction(string $action): string
    {
        return match($action) {
            'close_door' => 'Closed a door to save energy',
            'close_window' => 'Closed a window to save energy',
            'acknowledge_alert' => 'Acknowledged an alert',
            'daily_login' => 'Daily login bonus',
            'weekly_streak' => 'Completed a weekly streak',
            'monthly_champion' => 'Monthly champion reward',
            'energy_saved' => 'Saved energy',
            'quick_response' => 'Quick response to alert',
            'team_goal' => 'Achieved team goal',
            default => 'Action completed',
        };
    }
    
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
    
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
    
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }
    
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }
}