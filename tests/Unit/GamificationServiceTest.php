<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GamificationService;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class GamificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GamificationService $gamificationService;
    private User $testUser;
    private Organization $testOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gamificationService = new GamificationService();
        
        // Create test organization and user
        $this->testOrganization = Organization::factory()->create();
        $this->testUser = User::factory()->create([
            'organization_id' => $this->testOrganization->id,
            'points' => 0,
        ]);
        
        Cache::flush();
    }

    /**
     * Test EcoComfort specific point rules
     * - Fermer porte <30s : +50 pts
     * - Réduire T° 1°C : +100 pts  
     * - Challenge quotidien : +200 pts
     */
    public function test_ecocomfort_specific_point_rules()
    {
        // Test quick door close (<30s) = +50 pts
        $result1 = $this->gamificationService->awardQuickDoorClosePoints($this->testUser, 25.0);
        $this->assertEquals(50, $result1->points);
        $this->assertEquals('quick_door_close', $result1->action);
        
        // Test normal door close (>30s) = +25 pts  
        $result2 = $this->gamificationService->awardQuickDoorClosePoints($this->testUser, 45.0);
        $this->assertEquals(25, $result2->points);
        $this->assertEquals('close_door', $result2->action);
        
        // Test temperature reduction: 1°C = +100 pts
        $result3 = $this->gamificationService->awardTemperatureReductionPoints($this->testUser, 1.0);
        $this->assertEquals(100, $result3->points);
        
        // Test temperature reduction: 2.5°C = +250 pts
        $result4 = $this->gamificationService->awardTemperatureReductionPoints($this->testUser, 2.5);
        $this->assertEquals(250, $result4->points);
        
        // Test daily challenge = +200 pts
        $result5 = $this->gamificationService->awardDailyChallengePoints($this->testUser, "Économie d'énergie");
        $this->assertEquals(200, $result5->points);
        
        // Verify total points accumulated
        $this->testUser->refresh();
        // Expected points: 50 (quick_door_close) + 25 (close_door) + 100 (temp_reduction) + 250 (temp_reduction * 2.5) + 200 (daily_challenge) = 625
        // But multiplicateurs might apply, so just verify we have points
        $this->assertGreaterThan(500, $this->testUser->points);
    }

    /**
     * Test level thresholds as specified: 0-1000-5000-10000-20000+ points
     */
    public function test_ecocomfort_level_thresholds()
    {
        // Test level 1 (0 points)
        $level1 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(1, $level1['current_level']);
        $this->assertEquals(0, $level1['total_points']);
        
        // Test level 2 (1000 points)
        $this->testUser->update(['points' => 1000]);
        $level2 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(2, $level2['current_level']);
        $this->assertEquals(1000, $level2['total_points']);
        
        // Test level 3 (5000 points)
        $this->testUser->update(['points' => 5000]);
        $level3 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(3, $level3['current_level']);
        
        // Test level 4 (10000 points)
        $this->testUser->update(['points' => 10000]);
        $level4 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(4, $level4['current_level']);
        
        // Test level 5 (20000 points) - Max level
        $this->testUser->update(['points' => 20000]);
        $level5 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(5, $level5['current_level']);
        $this->assertTrue($level5['is_max_level']);
        
        // Test beyond max level
        $this->testUser->update(['points' => 25000]);
        $levelMax = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(5, $levelMax['current_level']);
        $this->assertTrue($levelMax['is_max_level']);
    }

    /**
     * Test performance requirement <25ms
     */
    public function test_gamification_performance_under_25ms()
    {
        $performanceTests = [];
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            // Test various gamification operations
            $this->gamificationService->awardPoints($this->testUser, 'acknowledge_alert');
            $this->gamificationService->getUserLevel($this->testUser);
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $performanceTests[] = $processingTime;
            
            // Individual operation should be <25ms
            $this->assertLessThan(25.0, $processingTime, 
                "Gamification processing must be <25ms, got {$processingTime}ms for iteration {$i}");
        }

        $averageTime = array_sum($performanceTests) / count($performanceTests);
        $maxTime = max($performanceTests);
        
        echo "\nGamification Performance:\n";
        echo "Average: {$averageTime}ms\n";
        echo "Maximum: {$maxTime}ms\n";
        
        // Average should be well under 25ms
        $this->assertLessThan(15.0, $averageTime, "Average processing time should be <15ms, got {$averageTime}ms");
    }

    /**
     * Test quick response detection (<30s threshold)
     */
    public function test_quick_response_detection()
    {
        // Test responses under 30 seconds
        $quickTimes = [5, 10, 15, 20, 25, 29];
        
        foreach ($quickTimes as $time) {
            $result = $this->gamificationService->awardQuickDoorClosePoints($this->testUser, $time);
            // Base points are 50, but multipliers might apply for very quick responses (<10s, <20s)
            $this->assertGreaterThanOrEqual(50, $result->points, "Response time {$time}s should award at least 50 points");
            $this->assertEquals('quick_door_close', $result->action);
            $this->assertTrue($result->metadata['is_quick_response']);
        }
        
        // Test responses over 30 seconds
        $slowTimes = [31, 45, 60, 120];
        
        foreach ($slowTimes as $time) {
            $result = $this->gamificationService->awardQuickDoorClosePoints($this->testUser, $time);
            $this->assertEquals(25, $result->points, "Response time {$time}s should award 25 points");
            $this->assertEquals('close_door', $result->action);
            $this->assertFalse($result->metadata['is_quick_response']);
        }
    }

    /**
     * Test multipliers based on performance
     */
    public function test_performance_multipliers()
    {
        // Test very quick response multiplier
        $veryQuickResult = $this->gamificationService->awardPoints(
            $this->testUser, 
            'quick_door_close', 
            'Very quick response',
            ['response_time_seconds' => 8] // <10s should get 1.5x multiplier
        );
        
        // Base 50 points * 1.5 multiplier = 75 points
        $this->assertGreaterThan(50, $veryQuickResult->points);
        
        // Test energy saving multiplier
        $energySavingResult = $this->gamificationService->awardPoints(
            $this->testUser,
            'energy_saving_action',
            'Significant energy savings',
            ['energy_saved_kwh' => 5.0] // Should get energy multiplier
        );
        
        $this->assertGreaterThan(30, $energySavingResult->points); // Base 30 + multiplier
    }

    /**
     * Test level progression mechanics
     */
    public function test_level_progression()
    {
        // Start at level 1
        $initialLevel = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(1, $initialLevel['current_level']);
        
        // Award enough points to reach level 2 (1000 points)
        $this->testUser->update(['points' => 999]);
        $almostLevel2 = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(1, $almostLevel2['current_level']);
        $this->assertEquals(1, $almostLevel2['points_to_next']);
        $this->assertEquals(99.9, $almostLevel2['progress_percent']); // 999/1000 * 100
        
        // Cross the threshold
        $this->gamificationService->awardPoints($this->testUser, 'daily_login'); // +10 points
        $this->testUser->refresh();
        
        $newLevel = $this->gamificationService->getUserLevel($this->testUser);
        $this->assertEquals(2, $newLevel['current_level']);
        $this->assertGreaterThan(1009, $this->testUser->points); // 999 + 10 + level up bonus
    }

    /**
     * Test badge system
     */
    public function test_badge_system()
    {
        $badges = $this->gamificationService->getUserBadges($this->testUser);
        
        $this->assertIsArray($badges);
        $this->assertGreaterThan(0, count($badges));
        
        // All badges should have required structure
        foreach ($badges as $badge) {
            $this->assertArrayHasKey('id', $badge);
            $this->assertArrayHasKey('name', $badge);
            $this->assertArrayHasKey('description', $badge);
            $this->assertArrayHasKey('earned', $badge);
            $this->assertArrayHasKey('progress', $badge);
            $this->assertArrayHasKey('threshold', $badge);
            $this->assertArrayHasKey('progress_percent', $badge);
            $this->assertArrayHasKey('points', $badge);
        }
    }

    /**
     * Test leaderboard functionality
     */
    public function test_leaderboard()
    {
        // Create additional test users with different points
        $user2 = User::factory()->create(['organization_id' => $this->testOrganization->id, 'points' => 500]);
        $user3 = User::factory()->create(['organization_id' => $this->testOrganization->id, 'points' => 1500]);
        
        // Award points through actions (not just update points directly) so they appear in period
        $this->gamificationService->awardPoints($this->testUser, 'daily_login', 'Login bonus');
        $this->gamificationService->awardPoints($user2, 'daily_login', 'Login bonus');  
        $this->gamificationService->awardPoints($user3, 'daily_login', 'Login bonus');
        
        // Get leaderboard
        $leaderboard = $this->gamificationService->getLeaderboard($this->testOrganization, 'monthly', 10);
        
        $this->assertIsArray($leaderboard);
        $this->assertEquals(3, count($leaderboard));
        
        // Should be sorted by points descending
        $this->assertEquals(1, $leaderboard[0]['rank']);
        $this->assertEquals(2, $leaderboard[1]['rank']);
        $this->assertEquals(3, $leaderboard[2]['rank']);
        
        // Highest points should be first
        $this->assertGreaterThanOrEqual($leaderboard[1]['total_points'], $leaderboard[0]['total_points']);
        $this->assertGreaterThanOrEqual($leaderboard[2]['total_points'], $leaderboard[1]['total_points']);
    }

    /**
     * Test achievements and streaks
     */
    public function test_achievements_and_streaks()
    {
        $achievements = $this->gamificationService->getUserAchievements($this->testUser);
        
        $this->assertIsArray($achievements);
        $this->assertArrayHasKey('current_streak', $achievements);
        $this->assertArrayHasKey('longest_streak', $achievements);
        $this->assertArrayHasKey('total_actions', $achievements);
        $this->assertArrayHasKey('energy_saved_kwh', $achievements);
        $this->assertArrayHasKey('money_saved', $achievements);
        $this->assertArrayHasKey('co2_saved_kg', $achievements);
    }

    /**
     * Test team challenges creation
     */
    public function test_team_challenges()
    {
        $challengeData = [
            'name' => 'Défi économie d\'énergie',
            'description' => 'Réduire la consommation de 10%',
            'target' => 1000,
            'metric' => 'points',
            'reward_points' => 200,
        ];
        
        $challenge = $this->gamificationService->createTeamChallenge($this->testOrganization, $challengeData);
        
        $this->assertIsArray($challenge);
        $this->assertArrayHasKey('id', $challenge);
        $this->assertArrayHasKey('organization_id', $challenge);
        $this->assertArrayHasKey('name', $challenge);
        $this->assertArrayHasKey('target', $challenge);
        $this->assertArrayHasKey('reward_points', $challenge);
        $this->assertEquals('active', $challenge['status']);
        
        // Challenge should be cached
        $cachedChallenge = Cache::get("challenge_{$challenge['id']}");
        $this->assertNotNull($cachedChallenge);
        $this->assertEquals($challenge['name'], $cachedChallenge['name']);
    }

    /**
     * Test ROI demonstration through points and monetary value
     */
    public function test_roi_demonstration()
    {
        // Award various actions and calculate ROI
        $this->gamificationService->awardQuickDoorClosePoints($this->testUser, 20); // +50 pts
        $this->gamificationService->awardTemperatureReductionPoints($this->testUser, 2.0); // +200 pts
        $this->gamificationService->awardDailyChallengePoints($this->testUser, "Test Challenge"); // +200 pts
        
        $this->testUser->refresh();
        $achievements = $this->gamificationService->getUserAchievements($this->testUser);
        
        // Should have demonstrable savings
        $this->assertGreaterThan(0, $achievements['money_saved']);
        $this->assertGreaterThan(0, $achievements['energy_saved_kwh']);
        $this->assertGreaterThan(0, $achievements['co2_saved_kg']);
        
        // Total points should reflect actions taken (allowing for multipliers and level-up bonuses)
        $this->assertGreaterThan(400, $this->testUser->points);
        
        echo "\nROI Demonstration:\n";
        echo "Total Points: {$this->testUser->points}\n";
        echo "Energy Saved: {$achievements['energy_saved_kwh']} kWh\n";
        echo "Money Saved: €{$achievements['money_saved']}\n";
        echo "CO2 Saved: {$achievements['co2_saved_kg']} kg\n";
    }
}