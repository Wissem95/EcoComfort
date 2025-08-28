<?php

namespace App\Services;

use App\Models\Gamification;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class GamificationService
{
    // Level thresholds - Updated per EcoComfort specifications
    private const LEVEL_THRESHOLDS = [
        1 => 0,        // Débutant 🌱
        2 => 1000,     // Apprenti 🌿  
        3 => 5000,     // Expert 🌳
        4 => 10000,    // Maître 🏆
        5 => 20000,    // Légende 👑
    ];
    
    // EcoComfort specific point rules
    private const ECOCOMFORT_POINTS = [
        'quick_door_close' => 50,    // Fermer porte <30s : +50 pts
        'temperature_reduction' => 100,  // Réduire T° 1°C : +100 pts
        'daily_challenge' => 200,    // Challenge quotidien : +200 pts
        'close_door' => 25,          // Fermer porte normale
        'acknowledge_alert' => 15,   // Acquitter alerte
        'energy_saved' => 30,        // Action d'économie d'énergie
        'energy_saving_action' => 30, // Alias pour energy_saved
        'weekly_streak' => 150,      // Série hebdomadaire
        'monthly_champion' => 500,   // Champion mensuel
        'daily_login' => 10,         // Connexion quotidienne
        'milestone' => 50,           // Jalon atteint
    ];
    
    // Badge requirements
    private const BADGES = [
        'energy_saver' => [
            'name' => 'Energy Saver',
            'description' => 'Saved 100 kWh of energy',
            'requirement' => 'energy_saved',
            'threshold' => 100,
            'points' => 500,
        ],
        'door_guardian' => [
            'name' => 'Door Guardian',
            'description' => 'Closed 50 doors quickly',
            'requirement' => 'close_door',
            'threshold' => 50,
            'points' => 200,
        ],
        'alert_master' => [
            'name' => 'Alert Master',
            'description' => 'Acknowledged 100 alerts',
            'requirement' => 'acknowledge_alert',
            'threshold' => 100,
            'points' => 300,
        ],
        'streak_legend' => [
            'name' => 'Streak Legend',
            'description' => '30-day login streak',
            'requirement' => 'daily_login',
            'threshold' => 30,
            'points' => 1000,
        ],
        'team_player' => [
            'name' => 'Team Player',
            'description' => 'Achieved 10 team goals',
            'requirement' => 'team_goal',
            'threshold' => 10,
            'points' => 750,
        ],
    ];
    
    /**
     * Award points to a user for an action using EcoComfort specific rules
     */
    public function awardPoints(User $user, string $action, string $description = null, array $metadata = null): Gamification
    {
        $startTime = microtime(true); // Performance tracking <25ms requirement
        
        // Use EcoComfort specific points if available, otherwise fallback
        $points = self::ECOCOMFORT_POINTS[$action] ?? $this->getDefaultPointsForAction($action);
        
        // Apply multipliers based on action specifics
        $points = $this->applyActionMultipliers($points, $action, $metadata);
        
        $finalDescription = $description ?: $this->getDescriptionForAction($action);
        
        // Create gamification record
        $gamification = Gamification::create([
            'user_id' => $user->id,
            'action' => $action,
            'points' => $points,
            'description' => $finalDescription,
            'metadata' => $metadata,
        ]);
        
        // Update user total points
        $user->increment('points', $points);
        
        // Check for level up
        $this->checkLevelUp($user);
        
        // Check for badges
        $this->checkBadges($user, $action);
        
        // Check for streaks and achievements
        $this->checkAchievements($user, $action);
        
        // Clear user caches
        $this->clearUserCaches($user);
        
        // Performance check - must be <25ms as specified
        $processingTime = (microtime(true) - $startTime) * 1000;
        if ($processingTime > 25) {
            Log::warning("Gamification processing exceeded 25ms performance target", [
                'user_id' => $user->id,
                'action' => $action,
                'processing_time_ms' => $processingTime
            ]);
        }
        
        return $gamification;
    }
    
    /**
     * Award points for quick door closing (<30s as specified)
     */
    public function awardQuickDoorClosePoints(User $user, float $responseTimeSeconds, array $metadata = []): Gamification
    {
        $action = $responseTimeSeconds <= 30 ? 'quick_door_close' : 'close_door';
        $description = $responseTimeSeconds <= 30 
            ? "Porte fermée rapidement en {$responseTimeSeconds}s (+50 pts)" 
            : "Porte fermée en {$responseTimeSeconds}s (+25 pts)";
        
        $metadata['response_time_seconds'] = $responseTimeSeconds;
        $metadata['is_quick_response'] = $responseTimeSeconds <= 30;
        
        return $this->awardPoints($user, $action, $description, $metadata);
    }
    
    /**
     * Award points for temperature reduction (100 pts per 1°C as specified)
     */
    public function awardTemperatureReductionPoints(User $user, float $temperatureReduction, array $metadata = []): Gamification
    {
        // Calculate points: 100 pts per 1°C reduced
        $points = (int) ($temperatureReduction * 100);
        $description = "Température réduite de {$temperatureReduction}°C (+{$points} pts)";
        
        $metadata['temperature_reduction'] = $temperatureReduction;
        $metadata['calculated_points'] = $points;
        
        // Create custom gamification record with calculated points
        $gamification = Gamification::create([
            'user_id' => $user->id,
            'action' => 'temperature_reduction',
            'points' => $points,
            'description' => $description,
            'metadata' => $metadata,
        ]);
        
        $user->increment('points', $points);
        $this->checkLevelUp($user);
        $this->clearUserCaches($user);
        
        return $gamification;
    }
    
    /**
     * Award daily challenge completion points (200 pts as specified)
     */
    public function awardDailyChallengePoints(User $user, string $challengeName, array $metadata = []): Gamification
    {
        $description = "Challenge quotidien complété : {$challengeName} (+200 pts)";
        $metadata['challenge_name'] = $challengeName;
        $metadata['completion_date'] = now()->toDateString();
        
        return $this->awardPoints($user, 'daily_challenge', $description, $metadata);
    }
    
    /**
     * Apply multipliers based on action specifics
     */
    private function applyActionMultipliers(int $basePoints, string $action, ?array $metadata): int
    {
        $multiplier = 1.0;
        
        // Quick response multiplier
        if ($action === 'quick_door_close' && isset($metadata['response_time_seconds'])) {
            $responseTime = $metadata['response_time_seconds'];
            if ($responseTime <= 10) {
                $multiplier *= 1.5; // 50% bonus for very quick response (<10s)
            } elseif ($responseTime <= 20) {
                $multiplier *= 1.2; // 20% bonus for quick response (<20s)
            }
        }
        
        // Energy savings multiplier
        if (isset($metadata['energy_saved_kwh']) && $metadata['energy_saved_kwh'] > 0) {
            $energySaved = $metadata['energy_saved_kwh'];
            $multiplier *= (1 + min(0.5, $energySaved / 10)); // Max 50% bonus
        }
        
        // Streak multiplier
        if (isset($metadata['streak_days']) && $metadata['streak_days'] > 7) {
            $streakDays = $metadata['streak_days'];
            $multiplier *= (1 + min(0.3, $streakDays / 100)); // Max 30% bonus for long streaks
        }
        
        return (int) ($basePoints * $multiplier);
    }
    
    /**
     * Get default points for actions not in ECOCOMFORT_POINTS
     */
    private function getDefaultPointsForAction(string $action): int
    {
        return match($action) {
            'daily_login' => 10,
            'acknowledge_alert' => 15,
            'energy_saved' => 20,
            'close_door' => 25,
            'level_up' => 0, // Calculated dynamically
            'badge_earned' => 0, // Calculated dynamically
            'milestone' => 0, // Calculated dynamically
            'energy_negotiation_accepted' => 30,
            'energy_negotiation_rejected' => -5,
            'weekly_streak' => 100,
            'team_goal' => 150,
            default => 10, // Default points
        };
    }
    
    /**
     * Get description for action
     */
    private function getDescriptionForAction(string $action): string
    {
        return match($action) {
            'daily_login' => 'Connexion quotidienne',
            'acknowledge_alert' => 'Alerte acquittée',
            'energy_saved' => 'Énergie économisée',
            'close_door' => 'Porte fermée',
            'quick_door_close' => 'Porte fermée rapidement',
            'temperature_reduction' => 'Température réduite',
            'daily_challenge' => 'Challenge quotidien complété',
            'level_up' => 'Niveau supérieur atteint',
            'badge_earned' => 'Badge obtenu',
            'milestone' => 'Étape franchie',
            'energy_negotiation_accepted' => 'Négociation énergétique acceptée',
            'energy_negotiation_rejected' => 'Négociation énergétique refusée',
            'weekly_streak' => 'Série hebdomadaire',
            'team_goal' => 'Objectif équipe atteint',
            default => 'Action écoresponsable',
        };
    }
    
    /**
     * Get user's current level and progress
     */
    public function getUserLevel(User $user): array
    {
        $totalPoints = $user->points;
        $currentLevel = 1;
        $nextLevel = 2;
        $pointsForNext = self::LEVEL_THRESHOLDS[2];
        $pointsForCurrent = 0;
        
        // Find current level
        foreach (self::LEVEL_THRESHOLDS as $level => $requiredPoints) {
            if ($totalPoints >= $requiredPoints) {
                $currentLevel = $level;
                $pointsForCurrent = $requiredPoints;
            } else {
                $nextLevel = $level;
                $pointsForNext = $requiredPoints;
                break;
            }
        }
        
        // Calculate progress to next level
        $isMaxLevel = $currentLevel >= 5; // Level 5 is max (20000 points)
        
        if ($isMaxLevel) {
            $progressPercent = 100;
            $pointsToNext = 0;
        } else {
            $pointsInLevel = $totalPoints - $pointsForCurrent;
            $pointsNeededForLevel = $pointsForNext - $pointsForCurrent;
            $progressPercent = min(100, round(($pointsInLevel / max(1, $pointsNeededForLevel)) * 100, 1));
            $pointsToNext = max(0, $pointsForNext - $totalPoints);
        }
        
        return [
            'current_level' => $currentLevel,
            'next_level' => $nextLevel,
            'total_points' => $totalPoints,
            'points_for_current' => $pointsForCurrent,
            'points_for_next' => $pointsForNext,
            'points_to_next' => $pointsToNext,
            'progress_percent' => $progressPercent,
            'is_max_level' => $isMaxLevel,
        ];
    }
    
    /**
     * Get user's badges
     */
    public function getUserBadges(User $user): array
    {
        $cacheKey = "user_badges_{$user->id}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($user) {
            $badges = [];
            
            foreach (self::BADGES as $badgeId => $badge) {
                $count = $this->getActionCount($user, $badge['requirement']);
                $earned = $count >= $badge['threshold'];
                
                $badges[] = [
                    'id' => $badgeId,
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'earned' => $earned,
                    'progress' => min($count, $badge['threshold']),
                    'threshold' => $badge['threshold'],
                    'progress_percent' => round(($count / $badge['threshold']) * 100),
                    'points' => $badge['points'],
                    'earned_at' => $earned ? $this->getBadgeEarnedDate($user, $badge['requirement'], $badge['threshold']) : null,
                ];
            }
            
            return $badges;
        });
    }
    
    /**
     * Get leaderboard for organization
     */
    public function getLeaderboard(Organization $organization, string $period = 'monthly', int $limit = 10): array
    {
        $cacheKey = "leaderboard_{$organization->id}_{$period}_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($organization, $period, $limit) {
            $query = $organization->users()
                ->with('gamificationHistory')
                ->select([
                    'users.*',
                    DB::raw('COALESCE(SUM(gamification.points), 0) as period_points'),
                    DB::raw('COUNT(gamification.id) as period_actions'),
                ])
                ->leftJoin('gamification', 'users.id', '=', 'gamification.user_id');
            
            // Apply period filter
            match($period) {
                'daily' => $query->where('gamification.created_at', '>=', now()->startOfDay()),
                'weekly' => $query->where('gamification.created_at', '>=', now()->startOfWeek()),
                'monthly' => $query->where('gamification.created_at', '>=', now()->startOfMonth()),
                'yearly' => $query->where('gamification.created_at', '>=', now()->startOfYear()),
                default => null,
            };
            
            $users = $query->groupBy('users.id')
                ->orderBy('period_points', 'desc')
                ->orderBy('users.points', 'desc')
                ->limit($limit)
                ->get();
            
            return $users->map(function ($user, $index) {
                $level = $this->getUserLevel($user);
                
                return [
                    'rank' => $index + 1,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'total_points' => $user->points,
                    'period_points' => $user->period_points,
                    'period_actions' => $user->period_actions,
                    'level' => $level['current_level'],
                    'badges_count' => collect($this->getUserBadges($user))->where('earned', true)->count(),
                ];
            })->toArray();
        });
    }
    
    /**
     * Get user achievements and streaks
     */
    public function getUserAchievements(User $user): array
    {
        $cacheKey = "user_achievements_{$user->id}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user) {
            return [
                'current_streak' => $this->getCurrentStreak($user),
                'longest_streak' => $this->getLongestStreak($user),
                'total_actions' => $user->gamificationHistory()->count(),
                'favorite_action' => $this->getFavoriteAction($user),
                'first_action_date' => $user->gamificationHistory()->min('created_at'),
                'last_action_date' => $user->gamificationHistory()->max('created_at'),
                'actions_this_week' => $user->gamificationHistory()->thisWeek()->count(),
                'actions_this_month' => $user->gamificationHistory()->thisMonth()->count(),
                'energy_saved_kwh' => $this->getEnergySaved($user),
                'money_saved' => $this->getMoneySaved($user),
                'co2_saved_kg' => $this->getCO2Saved($user),
            ];
        });
    }
    
    /**
     * Create team challenges
     */
    public function createTeamChallenge(Organization $organization, array $challengeData): array
    {
        $challenge = [
            'id' => uniqid('challenge_'),
            'organization_id' => $organization->id,
            'name' => $challengeData['name'],
            'description' => $challengeData['description'],
            'target' => $challengeData['target'],
            'metric' => $challengeData['metric'], // points, actions, energy_saved
            'start_date' => $challengeData['start_date'] ?? now(),
            'end_date' => $challengeData['end_date'] ?? now()->addDays(7),
            'reward_points' => $challengeData['reward_points'] ?? 100,
            'status' => 'active',
            'progress' => 0,
            'participants' => [],
        ];
        
        // Store challenge in cache
        Cache::put("challenge_{$challenge['id']}", $challenge, now()->addDays(30));
        
        // Add to organization challenges list
        $orgChallenges = Cache::get("org_challenges_{$organization->id}", []);
        $orgChallenges[] = $challenge['id'];
        Cache::put("org_challenges_{$organization->id}", $orgChallenges, now()->addDays(30));
        
        return $challenge;
    }
    
    /**
     * Update challenge progress
     */
    public function updateChallengeProgress(string $challengeId): void
    {
        $challenge = Cache::get("challenge_{$challengeId}");
        
        if (!$challenge || $challenge['status'] !== 'active') {
            return;
        }
        
        $organization = Organization::find($challenge['organization_id']);
        if (!$organization) {
            return;
        }
        
        // Calculate current progress based on metric
        $progress = match($challenge['metric']) {
            'points' => $this->getOrganizationPoints($organization, $challenge['start_date']),
            'actions' => $this->getOrganizationActions($organization, $challenge['start_date']),
            'energy_saved' => $this->getOrganizationEnergySaved($organization, $challenge['start_date']),
            default => 0,
        };
        
        $challenge['progress'] = $progress;
        $challenge['progress_percent'] = min(100, round(($progress / $challenge['target']) * 100));
        
        // Check if challenge is completed
        if ($progress >= $challenge['target']) {
            $challenge['status'] = 'completed';
            $this->rewardChallengeCompletion($organization, $challenge);
        } elseif (now() > $challenge['end_date']) {
            $challenge['status'] = 'expired';
        }
        
        Cache::put("challenge_{$challengeId}", $challenge, now()->addDays(30));
    }
    
    /**
     * Check if user leveled up
     */
    private function checkLevelUp(User $user): void
    {
        $levelInfo = $this->getUserLevel($user);
        $previousLevel = $levelInfo['current_level'] - 1;
        
        // Check if user just reached a new level
        if ($user->points >= self::LEVEL_THRESHOLDS[$levelInfo['current_level']] && 
            $previousLevel > 0 && 
            $user->points - $user->getOriginal('points') >= 0) {
            
            // Award level up bonus
            $bonusPoints = $levelInfo['current_level'] * 50;
            
            Gamification::create([
                'user_id' => $user->id,
                'action' => 'level_up',
                'points' => $bonusPoints,
                'description' => "Reached level {$levelInfo['current_level']}!",
                'metadata' => [
                    'level' => $levelInfo['current_level'],
                    'total_points' => $user->points,
                ],
            ]);
            
            $user->increment('points', $bonusPoints);
        }
    }
    
    /**
     * Check for badge achievements
     */
    private function checkBadges(User $user, string $action): void
    {
        foreach (self::BADGES as $badgeId => $badge) {
            if ($badge['requirement'] === $action) {
                $count = $this->getActionCount($user, $action);
                
                if ($count === $badge['threshold']) {
                    // User just earned this badge
                    $this->awardBadge($user, $badgeId, $badge);
                }
            }
        }
    }
    
    /**
     * Award badge to user
     */
    private function awardBadge(User $user, string $badgeId, array $badge): void
    {
        Gamification::create([
            'user_id' => $user->id,
            'action' => 'badge_earned',
            'points' => $badge['points'],
            'description' => "Earned badge: {$badge['name']}",
            'metadata' => [
                'badge_id' => $badgeId,
                'badge_name' => $badge['name'],
            ],
        ]);
        
        $user->increment('points', $badge['points']);
    }
    
    /**
     * Check for various achievements
     */
    private function checkAchievements(User $user, string $action): void
    {
        if ($action === 'daily_login') {
            $this->checkStreakAchievements($user);
        }
        
        if ($action === 'close_door' && $this->isQuickResponse($user)) {
            $this->awardPoints($user, 'quick_response', 'Quick door closure');
        }
        
        // Check for milestone achievements
        $this->checkMilestoneAchievements($user);
    }
    
    /**
     * Check streak achievements
     */
    private function checkStreakAchievements(User $user): void
    {
        $currentStreak = $this->getCurrentStreak($user);
        
        // Award streak bonuses at milestones
        $milestones = [7, 14, 30, 60, 100];
        
        foreach ($milestones as $milestone) {
            if ($currentStreak === $milestone) {
                $bonusPoints = $milestone * 5;
                
                $this->awardPoints($user, 'weekly_streak', "Achieved {$milestone}-day streak!", [
                    'streak_days' => $milestone,
                ]);
            }
        }
    }
    
    /**
     * Get action count for user
     */
    private function getActionCount(User $user, string $action): int
    {
        return $user->gamificationHistory()->where('action', $action)->count();
    }
    
    /**
     * Get when badge was earned
     */
    private function getBadgeEarnedDate(User $user, string $action, int $threshold): ?string
    {
        $records = $user->gamificationHistory()
            ->where('action', $action)
            ->orderBy('created_at')
            ->limit($threshold)
            ->get();
        
        return $records->count() >= $threshold ? $records->last()->created_at->toISOString() : null;
    }
    
    /**
     * Get current login streak
     */
    private function getCurrentStreak(User $user): int
    {
        $streak = 0;
        $date = now()->startOfDay();
        
        while ($date->greaterThan(now()->subDays(365))) {
            if ($user->gamificationHistory()
                ->where('action', 'daily_login')
                ->whereDate('created_at', $date)
                ->exists()) {
                $streak++;
                $date->subDay();
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * Get longest streak
     */
    private function getLongestStreak(User $user): int
    {
        // This is a simplified implementation
        // In production, you might want to cache this value
        return Cache::remember("longest_streak_{$user->id}", now()->addHours(6), function () use ($user) {
            $maxStreak = 0;
            $currentStreak = 0;
            
            $loginDates = $user->gamificationHistory()
                ->where('action', 'daily_login')
                ->orderBy('created_at')
                ->pluck('created_at')
                ->map(fn($date) => $date->format('Y-m-d'))
                ->unique()
                ->values();
            
            for ($i = 1; $i < $loginDates->count(); $i++) {
                $prevDate = \Carbon\Carbon::parse($loginDates[$i - 1]);
                $currentDate = \Carbon\Carbon::parse($loginDates[$i]);
                
                if ($currentDate->diffInDays($prevDate) === 1) {
                    $currentStreak++;
                } else {
                    $maxStreak = max($maxStreak, $currentStreak);
                    $currentStreak = 1;
                }
            }
            
            return max($maxStreak, $currentStreak);
        });
    }
    
    /**
     * Get favorite action
     */
    private function getFavoriteAction(User $user): ?string
    {
        return $user->gamificationHistory()
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->first()?->action;
    }
    
    /**
     * Calculate energy saved (placeholder)
     */
    private function getEnergySaved(User $user): float
    {
        // Calculate energy saved based on user actions
        $energyActions = $user->gamificationHistory()
            ->whereIn('action', ['quick_door_close', 'close_door', 'temperature_reduction'])
            ->get();
            
        $totalEnergySaved = 0;
        
        foreach ($energyActions as $action) {
            switch ($action->action) {
                case 'quick_door_close':
                case 'close_door':
                    // Each door close saves ~0.5 kWh
                    $totalEnergySaved += 0.5;
                    break;
                case 'temperature_reduction':
                    // Temperature reduction saves more energy (2-3 kWh per degree)
                    $totalEnergySaved += 2.5;
                    break;
            }
        }
        
        return $totalEnergySaved;
    }
    
    /**
     * Calculate money saved
     */
    private function getMoneySaved(User $user): float
    {
        $energySaved = $this->getEnergySaved($user);
        return $energySaved * config('energy.price_per_kwh', 0.15);
    }
    
    /**
     * Calculate CO2 saved
     */
    private function getCO2Saved(User $user): float
    {
        $energySaved = $this->getEnergySaved($user);
        return $energySaved * 0.475; // kg CO2 per kWh
    }
    
    /**
     * Clear user caches
     */
    private function clearUserCaches(User $user): void
    {
        Cache::forget("user_badges_{$user->id}");
        Cache::forget("user_achievements_{$user->id}");
        Cache::forget("longest_streak_{$user->id}");
    }
    
    /**
     * Check if action was a quick response
     */
    private function isQuickResponse(User $user): bool
    {
        // Check if last action was within 30 seconds of an alert
        $lastAlert = Cache::get("last_alert_time_{$user->id}");
        return $lastAlert && (time() - $lastAlert) < 30;
    }
    
    /**
     * Check milestone achievements
     */
    private function checkMilestoneAchievements(User $user): void
    {
        $totalPoints = $user->points;
        $milestones = [500, 1000, 2500, 5000, 10000];
        
        foreach ($milestones as $milestone) {
            if ($totalPoints >= $milestone && 
                !$user->gamificationHistory()
                    ->where('action', 'milestone')
                    ->where('metadata->milestone', $milestone)
                    ->exists()) {
                
                $bonusPoints = $milestone / 10;
                
                Gamification::create([
                    'user_id' => $user->id,
                    'action' => 'milestone',
                    'points' => $bonusPoints,
                    'description' => "Reached {$milestone} points milestone!",
                    'metadata' => ['milestone' => $milestone],
                ]);
                
                $user->increment('points', $bonusPoints);
            }
        }
    }
    
    /**
     * Get organization total points for period
     */
    private function getOrganizationPoints(Organization $organization, $startDate): int
    {
        return $organization->users()
            ->join('gamification', 'users.id', '=', 'gamification.user_id')
            ->where('gamification.created_at', '>=', $startDate)
            ->sum('gamification.points');
    }
    
    /**
     * Get organization total actions for period
     */
    private function getOrganizationActions(Organization $organization, $startDate): int
    {
        return $organization->users()
            ->join('gamification', 'users.id', '=', 'gamification.user_id')
            ->where('gamification.created_at', '>=', $startDate)
            ->count();
    }
    
    /**
     * Get organization energy saved for period
     */
    private function getOrganizationEnergySaved(Organization $organization, $startDate): float
    {
        return $organization->users()
            ->join('gamification', 'users.id', '=', 'gamification.user_id')
            ->where('gamification.created_at', '>=', $startDate)
            ->where('gamification.action', 'energy_saved')
            ->sum('gamification.points') / 10; // Rough conversion
    }
    
    /**
     * Reward challenge completion
     */
    private function rewardChallengeCompletion(Organization $organization, array $challenge): void
    {
        $users = $organization->users;
        
        foreach ($users as $user) {
            $this->awardPoints($user, 'team_goal', $challenge['name'], [
                'challenge_id' => $challenge['id'],
                'challenge_name' => $challenge['name'],
            ]);
        }
    }
}