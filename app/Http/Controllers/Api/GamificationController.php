<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GamificationController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService
    ) {}

    /**
     * Get user's gamification profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // For testing without auth, return mock data if user is null
        if (!$user) {
            return response()->json([
                'user' => [
                    'id' => 'demo-user',
                    'name' => 'Demo User',
                    'total_points' => 0,
                    'role' => 'employee',
                ],
                'level' => ['name' => 'Novice', 'level' => 1, 'points_required' => 100],
                'badges' => [],
                'achievements' => [],
                'recent_activities' => [],
            ]);
        }
        
        $profile = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'total_points' => $user->points,
                'role' => $user->role,
            ],
            'level' => $this->gamificationService->getUserLevel($user),
            'badges' => $this->gamificationService->getUserBadges($user),
            'achievements' => $this->gamificationService->getUserAchievements($user),
            'recent_activities' => $this->getRecentActivities($user),
        ];

        return response()->json($profile);
    }

    /**
     * Get leaderboards
     */
    public function leaderboard(Request $request)
    {
        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'period' => 'sometimes|in:daily,weekly,monthly,yearly,all_time',
            'limit' => 'sometimes|integer|min:5|max:50',
        ]);

        $period = $validated['period'] ?? 'monthly';
        $limit = $validated['limit'] ?? 10;

        $leaderboard = [
            'current_period' => [
                'period' => $period,
                'rankings' => $this->gamificationService->getLeaderboard($organization, $period, $limit),
            ],
            'user_ranking' => $this->getUserRanking($request->user(), $organization, $period),
            'organization_stats' => $this->getOrganizationStats($organization),
        ];

        // Add other periods for comparison
        if ($period !== 'weekly') {
            $leaderboard['weekly'] = $this->gamificationService->getLeaderboard($organization, 'weekly', 5);
        }
        
        if ($period !== 'monthly') {
            $leaderboard['monthly'] = $this->gamificationService->getLeaderboard($organization, 'monthly', 5);
        }

        return response()->json($leaderboard);
    }

    /**
     * Get available badges
     */
    public function badges(Request $request)
    {
        $user = $request->user();
        $userBadges = $this->gamificationService->getUserBadges($user);
        
        $allBadges = [
            'earned' => array_filter($userBadges, fn($badge) => $badge['earned']),
            'available' => array_filter($userBadges, fn($badge) => !$badge['earned']),
            'statistics' => [
                'total_badges' => count($userBadges),
                'earned_count' => count(array_filter($userBadges, fn($badge) => $badge['earned'])),
                'completion_rate' => count($userBadges) > 0 
                    ? round((count(array_filter($userBadges, fn($badge) => $badge['earned'])) / count($userBadges)) * 100, 1)
                    : 0,
            ],
        ];

        return response()->json($allBadges);
    }

    /**
     * Get user achievements and statistics
     */
    public function achievements(Request $request)
    {
        $user = $request->user();
        $achievements = $this->gamificationService->getUserAchievements($user);
        
        return response()->json($achievements);
    }

    /**
     * Get organization challenges
     */
    public function challenges(Request $request)
    {
        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'status' => 'sometimes|in:active,completed,expired',
        ]);

        $status = $validated['status'] ?? 'active';
        
        $challenges = $this->getOrganizationChallenges($organization, $status);
        
        return response()->json([
            'challenges' => $challenges,
            'user_participation' => $this->getUserChallengeParticipation($request->user()),
        ]);
    }

    /**
     * Join a challenge
     */
    public function joinChallenge(Request $request, string $challengeId)
    {
        $user = $request->user();
        $challenge = Cache::get("challenge_{$challengeId}");
        
        if (!$challenge) {
            return response()->json(['message' => 'Challenge not found'], 404);
        }

        if ($challenge['organization_id'] !== $user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($challenge['status'] !== 'active') {
            return response()->json(['message' => 'Challenge is not active'], 400);
        }

        // Add user to participants
        $participants = $challenge['participants'] ?? [];
        if (!in_array($user->id, $participants)) {
            $participants[] = $user->id;
            $challenge['participants'] = $participants;
            
            Cache::put("challenge_{$challengeId}", $challenge, now()->addDays(30));
        }

        return response()->json([
            'message' => 'Successfully joined challenge',
            'challenge' => $challenge,
        ]);
    }

    /**
     * Create organization challenge (admin only)
     */
    public function createChallenge(Request $request)
    {
        if (!$request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'target' => 'required|integer|min:1',
            'metric' => 'required|in:points,actions,energy_saved',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'reward_points' => 'required|integer|min:1|max:1000',
        ]);

        $organization = $request->user()->organization;
        $challenge = $this->gamificationService->createTeamChallenge($organization, $validated);

        return response()->json([
            'message' => 'Challenge created successfully',
            'challenge' => $challenge,
        ], 201);
    }

    /**
     * Award manual points (admin only)
     */
    public function awardPoints(Request $request)
    {
        if (!$request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'points' => 'required|integer|min:1|max:500',
            'reason' => 'required|string|max:255',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        
        // Verify user is in same organization
        if ($targetUser->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'User not in your organization'], 403);
        }

        $gamification = $this->gamificationService->awardPoints(
            $targetUser,
            'manual_award',
            $validated['reason'],
            [
                'awarded_by' => $request->user()->id,
                'manual_points' => $validated['points'],
            ]
        );

        return response()->json([
            'message' => 'Points awarded successfully',
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'total_points' => $targetUser->fresh()->points,
            ],
            'award' => [
                'points' => $validated['points'],
                'reason' => $validated['reason'],
                'awarded_at' => $gamification->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get gamification statistics for organization (admin only)
     */
    public function organizationStats(Request $request)
    {
        if (!$request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organization = $request->user()->organization;
        
        $validated = $request->validate([
            'period' => 'sometimes|in:7d,30d,90d,365d',
        ]);

        $period = $validated['period'] ?? '30d';
        
        $cacheKey = "org_gamification_stats_{$organization->id}_{$period}";
        
        $stats = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($organization, $period) {
            $days = (int) str_replace('d', '', $period);
            $startDate = now()->subDays($days);

            $users = $organization->users;
            $totalUsers = $users->count();
            $activeUsers = $users->filter(function ($user) use ($startDate) {
                return $user->gamificationHistory()
                    ->where('created_at', '>=', $startDate)
                    ->exists();
            });

            return [
                'engagement' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers->count(),
                    'engagement_rate' => $totalUsers > 0 
                        ? round(($activeUsers->count() / $totalUsers) * 100, 1)
                        : 0,
                ],
                'points' => [
                    'total_points_awarded' => $organization->users()
                        ->join('gamification', 'users.id', '=', 'gamification.user_id')
                        ->where('gamification.created_at', '>=', $startDate)
                        ->sum('gamification.points'),
                    'average_per_user' => $activeUsers->count() > 0
                        ? round($activeUsers->avg('points'), 0)
                        : 0,
                    'top_earner_points' => $users->max('points'),
                ],
                'actions' => [
                    'total_actions' => $organization->users()
                        ->join('gamification', 'users.id', '=', 'gamification.user_id')
                        ->where('gamification.created_at', '>=', $startDate)
                        ->count(),
                    'most_common_action' => $this->getMostCommonAction($organization, $startDate),
                ],
                'badges' => [
                    'total_badges_earned' => $this->getTotalBadgesEarned($organization),
                    'badge_completion_rate' => $this->getBadgeCompletionRate($organization),
                ],
                'trends' => $this->getEngagementTrends($organization, $days),
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get recent activities for user
     */
    private function getRecentActivities(User $user, int $limit = 10): array
    {
        return $user->gamificationHistory()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'points' => $activity->points,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get user's ranking in organization
     */
    private function getUserRanking(User $user, Organization $organization, string $period): array
    {
        $leaderboard = $this->gamificationService->getLeaderboard($organization, $period, 1000);
        
        $userRank = null;
        foreach ($leaderboard as $index => $entry) {
            if ($entry['user_id'] === $user->id) {
                $userRank = $index + 1;
                break;
            }
        }

        return [
            'rank' => $userRank,
            'total_participants' => count($leaderboard),
            'percentile' => $userRank && count($leaderboard) > 0
                ? round((1 - ($userRank - 1) / count($leaderboard)) * 100, 1)
                : null,
        ];
    }

    /**
     * Get organization statistics
     */
    private function getOrganizationStats(Organization $organization): array
    {
        return Cache::remember("org_stats_{$organization->id}", now()->addMinutes(30), function () use ($organization) {
            $users = $organization->users;
            
            return [
                'total_members' => $users->count(),
                'average_points' => round($users->avg('points'), 0),
                'total_points' => $users->sum('points'),
                'most_active_today' => $users->whereHas('gamificationHistory', function ($query) {
                    $query->whereDate('created_at', today());
                })->count(),
            ];
        });
    }

    /**
     * Get organization challenges by status
     */
    private function getOrganizationChallenges(Organization $organization, string $status): array
    {
        $challengeIds = Cache::get("org_challenges_{$organization->id}", []);
        $challenges = [];
        
        foreach ($challengeIds as $challengeId) {
            $challenge = Cache::get("challenge_{$challengeId}");
            if ($challenge && $challenge['status'] === $status) {
                $challenges[] = $challenge;
            }
        }
        
        return $challenges;
    }

    /**
     * Get user's challenge participation
     */
    private function getUserChallengeParticipation(User $user): array
    {
        $challengeIds = Cache::get("org_challenges_{$user->organization_id}", []);
        $participation = [];
        
        foreach ($challengeIds as $challengeId) {
            $challenge = Cache::get("challenge_{$challengeId}");
            if ($challenge && in_array($user->id, $challenge['participants'] ?? [])) {
                $participation[] = [
                    'challenge_id' => $challengeId,
                    'challenge_name' => $challenge['name'],
                    'status' => $challenge['status'],
                    'progress' => $challenge['progress_percent'] ?? 0,
                ];
            }
        }
        
        return $participation;
    }

    /**
     * Get most common action for organization
     */
    private function getMostCommonAction(Organization $organization, $startDate): ?string
    {
        return $organization->users()
            ->join('gamification', 'users.id', '=', 'gamification.user_id')
            ->where('gamification.created_at', '>=', $startDate)
            ->groupBy('gamification.action')
            ->orderByRaw('COUNT(*) DESC')
            ->value('gamification.action');
    }

    /**
     * Get total badges earned in organization
     */
    private function getTotalBadgesEarned(Organization $organization): int
    {
        $totalBadges = 0;
        
        foreach ($organization->users as $user) {
            $badges = $this->gamificationService->getUserBadges($user);
            $totalBadges += count(array_filter($badges, fn($badge) => $badge['earned']));
        }
        
        return $totalBadges;
    }

    /**
     * Get badge completion rate for organization
     */
    private function getBadgeCompletionRate(Organization $organization): float
    {
        $users = $organization->users;
        if ($users->count() === 0) return 0;
        
        $totalCompletionRate = 0;
        
        foreach ($users as $user) {
            $badges = $this->gamificationService->getUserBadges($user);
            $earnedCount = count(array_filter($badges, fn($badge) => $badge['earned']));
            $totalBadges = count($badges);
            
            if ($totalBadges > 0) {
                $totalCompletionRate += ($earnedCount / $totalBadges);
            }
        }
        
        return round(($totalCompletionRate / $users->count()) * 100, 1);
    }

    /**
     * Get engagement trends
     */
    private function getEngagementTrends(Organization $organization, int $days): array
    {
        $trends = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            
            $dailyActions = $organization->users()
                ->join('gamification', 'users.id', '=', 'gamification.user_id')
                ->whereBetween('gamification.created_at', [$dayStart, $dayEnd])
                ->count();
            
            $dailyActiveUsers = $organization->users()
                ->join('gamification', 'users.id', '=', 'gamification.user_id')
                ->whereBetween('gamification.created_at', [$dayStart, $dayEnd])
                ->distinct('users.id')
                ->count();
            
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'actions' => $dailyActions,
                'active_users' => $dailyActiveUsers,
            ];
        }
        
        return $trends;
    }
}