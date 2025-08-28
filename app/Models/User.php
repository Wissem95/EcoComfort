<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'role',
        'points',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
        ];
    }
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    
    public function gamificationHistory(): HasMany
    {
        return $this->hasMany(Gamification::class);
    }
    
    public function acknowledgedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'acknowledged_by');
    }
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }
    
    public function addPoints(int $points, string $action, ?string $description = null): void
    {
        $this->increment('points', $points);
        
        $this->gamificationHistory()->create([
            'action' => $action,
            'points' => $points,
            'description' => $description,
        ]);
    }
    
    /**
     * Get the current gamification record
     */
    public function gamification(): HasMany
    {
        return $this->hasMany(Gamification::class)->latest();
    }
    
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'user_id' => $this->id,
            'organization_id' => $this->organization_id,
            'role' => $this->role,
            'permissions' => $this->getPermissions(),
        ];
    }
    
    /**
     * Get user permissions based on role
     *
     * @return array
     */
    public function getPermissions(): array
    {
        $basePermissions = [
            'dashboard.view',
            'sensors.view',
            'profile.edit',
        ];
        
        $rolePermissions = match ($this->role) {
            'admin' => [
                'users.manage',
                'organizations.manage',
                'sensors.manage',
                'settings.manage',
                'reports.export',
                'alerts.manage',
                'dashboard.admin',
            ],
            'manager' => [
                'users.view',
                'sensors.edit',
                'reports.view',
                'alerts.view',
                'dashboard.manager',
            ],
            default => [],
        };
        
        return array_merge($basePermissions, $rolePermissions);
    }
}
