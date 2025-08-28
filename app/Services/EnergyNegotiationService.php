<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use App\Models\Organization;
use App\Services\EnergyCalculatorService;
use App\Services\GamificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class EnergyNegotiationService
{
    private EnergyCalculatorService $energyCalculator;
    private GamificationService $gamificationService;
    
    // Reward types
    private const REWARDS = [
        'eco_points' => [
            'name' => 'points éco',
            'multiplier' => 10, // points per euro saved
        ],
        'coffee_voucher' => [
            'name' => 'café offert',
            'threshold' => 0.5, // minimum savings for coffee
            'cost' => 2.50,
        ],
        'lunch_voucher' => [
            'name' => 'repas offert',
            'threshold' => 3.0, // minimum savings for lunch
            'cost' => 12.50,
        ],
        'early_leave' => [
            'name' => 'sortie anticipée',
            'threshold' => 5.0, // minimum savings for early leave
            'value_euro' => 25.0,
        ],
        'team_bonus' => [
            'name' => 'bonus équipe',
            'threshold' => 10.0, // minimum savings for team bonus
            'value_euro' => 50.0,
        ],
    ];

    public function __construct(EnergyCalculatorService $energyCalculator, GamificationService $gamificationService)
    {
        $this->energyCalculator = $energyCalculator;
        $this->gamificationService = $gamificationService;
    }

    /**
     * Create energy-saving negotiation proposal
     * Returns format as specified in prompt
     */
    public function createNegotiationProposal(
        Room $room,
        User $user,
        string $proposalType = 'temperature_reduction'
    ): array {
        $startTime = microtime(true); // Performance tracking <25ms requirement
        
        // Get current room conditions
        $currentTemp = $this->getCurrentTemperature($room);
        $outdoorTemp = $this->getOutdoorTemperature();
        
        // Create proposal based on type
        $proposal = match($proposalType) {
            'temperature_reduction' => $this->createTemperatureReductionProposal($room, $user, $currentTemp, $outdoorTemp),
            'door_closing' => $this->createDoorClosingProposal($room, $user),
            'equipment_shutdown' => $this->createEquipmentShutdownProposal($room, $user),
            'schedule_optimization' => $this->createScheduleOptimizationProposal($room, $user),
            default => $this->createTemperatureReductionProposal($room, $user, $currentTemp, $outdoorTemp),
        };
        
        // Add unique proposal ID
        $proposalId = 'nego_' . uniqid();
        $proposal['id'] = $proposalId;
        $proposal['created_at'] = now()->toISOString();
        $proposal['expires_at'] = now()->addMinutes(15)->toISOString(); // 15 minutes to respond
        $proposal['user_id'] = $user->id;
        $proposal['room_id'] = $room->id;
        
        // Performance check
        $processingTime = (microtime(true) - $startTime) * 1000;
        if ($processingTime > 25) {
            Log::warning("Negotiation proposal creation exceeded 25ms performance target", [
                'proposal_type' => $proposalType,
                'processing_time_ms' => $processingTime
            ]);
        }
        
        // Cache the proposal
        Cache::put("negotiation_proposal_{$proposalId}", $proposal, now()->addMinutes(20));
        
        // Log proposal creation
        Log::info("Energy negotiation proposal created", [
            'proposal_id' => $proposalId,
            'user_id' => $user->id,
            'room_id' => $room->id,
            'type' => $proposalType,
            'savings' => $proposal['savings'],
        ]);
        
        return $proposal;
    }

    /**
     * Create temperature reduction proposal
     */
    private function createTemperatureReductionProposal(Room $room, User $user, float $currentTemp, float $outdoorTemp): array
    {
        // Suggest 1°C reduction as specified in prompt
        $tempReduction = 1.0;
        $proposedTemp = $currentTemp - $tempReduction;
        $duration = 2.0; // 2 hours as in prompt example
        
        // Calculate energy savings
        $energyLoss = $this->energyCalculator->calculateEnergyLossEcoComfort(
            $currentTemp,
            $outdoorTemp,
            $room->surface_m2,
            'room_heating'
        );
        
        $energyLossReduced = $this->energyCalculator->calculateEnergyLossEcoComfort(
            $proposedTemp,
            $outdoorTemp,
            $room->surface_m2,
            'room_heating'
        );
        
        $hourlySavings = $energyLoss['cost_impact_euro_per_hour'] - $energyLossReduced['cost_impact_euro_per_hour'];
        $totalSavings = $hourlySavings * $duration;
        
        // Determine reward
        $reward = $this->calculateReward($totalSavings);
        
        return [
            'type' => 'temperature_reduction',
            'message' => "{$room->name} : Acceptez-vous {$tempReduction}°C de moins pendant {$duration}h ?",
            'details' => [
                'current_temperature' => $currentTemp,
                'proposed_temperature' => $proposedTemp,
                'reduction_celsius' => $tempReduction,
                'duration_hours' => $duration,
            ],
            'reward' => $reward['description'],
            'reward_details' => $reward,
            'savings' => number_format($totalSavings, 2) . "€ économisés",
            'savings_euro' => $totalSavings,
            'actions' => ['Accepter', 'Négocier', 'Refuser'],
            'negotiation_options' => [
                'temperature_options' => [
                    0.5 => ['savings' => $hourlySavings * 0.5 * $duration, 'comfort' => 'high'],
                    1.0 => ['savings' => $totalSavings, 'comfort' => 'medium'],
                    1.5 => ['savings' => $hourlySavings * 1.5 * $duration, 'comfort' => 'low'],
                ],
                'duration_options' => [
                    1 => ['savings' => $hourlySavings, 'impact' => 'minimal'],
                    2 => ['savings' => $totalSavings, 'impact' => 'moderate'],
                    4 => ['savings' => $hourlySavings * 4, 'impact' => 'significant'],
                ],
            ],
        ];
    }

    /**
     * Create door closing proposal
     */
    private function createDoorClosingProposal(Room $room, User $user): array
    {
        // Check if room has open doors
        $openDoors = $this->getOpenDoors($room);
        
        if (empty($openDoors)) {
            return [
                'type' => 'door_closing',
                'message' => "{$room->name} : Toutes les portes sont déjà fermées",
                'savings' => "0€ économisés",
                'actions' => ['OK'],
            ];
        }
        
        $duration = 1.0; // 1 hour
        $currentTemp = $this->getCurrentTemperature($room);
        $outdoorTemp = $this->getOutdoorTemperature();
        
        // Calculate savings from closing doors
        $energyLossOpen = $this->energyCalculator->calculateEnergyLossEcoComfort(
            $currentTemp,
            $outdoorTemp,
            $room->surface_m2 * 1.3, // 30% more loss with open doors
            'door'
        );
        
        $energyLossClosed = $this->energyCalculator->calculateEnergyLossEcoComfort(
            $currentTemp,
            $outdoorTemp,
            $room->surface_m2,
            'room_heating'
        );
        
        $hourlySavings = $energyLossOpen['cost_impact_euro_per_hour'] - $energyLossClosed['cost_impact_euro_per_hour'];
        $totalSavings = $hourlySavings * $duration;
        
        $reward = $this->calculateReward($totalSavings);
        
        return [
            'type' => 'door_closing',
            'message' => "{$room->name} : Fermez les {count($openDoors)} porte(s) ouvertes ?",
            'details' => [
                'open_doors' => $openDoors,
                'duration_hours' => $duration,
            ],
            'reward' => $reward['description'],
            'reward_details' => $reward,
            'savings' => number_format($totalSavings, 2) . "€ économisés",
            'savings_euro' => $totalSavings,
            'actions' => ['Accepter', 'Reporter', 'Refuser'],
        ];
    }

    /**
     * Process user response to negotiation
     */
    public function processNegotiationResponse(string $proposalId, string $action, array $parameters = []): array
    {
        $proposal = Cache::get("negotiation_proposal_{$proposalId}");
        
        if (!$proposal) {
            return [
                'success' => false,
                'message' => 'Proposition expirée ou non trouvée',
            ];
        }
        
        $user = User::find($proposal['user_id']);
        $room = Room::find($proposal['room_id']);
        
        if (!$user || !$room) {
            return [
                'success' => false,
                'message' => 'Utilisateur ou salle introuvable',
            ];
        }
        
        switch ($action) {
            case 'Accepter':
                return $this->processAcceptance($proposal, $user, $room);
                
            case 'Négocier':
                return $this->processNegotiation($proposal, $user, $room, $parameters);
                
            case 'Refuser':
                return $this->processRejection($proposal, $user, $room);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Action non reconnue',
                ];
        }
    }

    /**
     * Process acceptance of proposal
     */
    private function processAcceptance(array $proposal, User $user, Room $room): array
    {
        // Award points for acceptance
        $this->gamificationService->awardPoints(
            $user,
            'energy_negotiation_accepted',
            "Proposition acceptée : {$proposal['savings']}",
            ['proposal_id' => $proposal['id'], 'savings' => $proposal['savings_euro']]
        );
        
        // Award reward points
        if (isset($proposal['reward_details']['points'])) {
            $this->gamificationService->awardPoints(
                $user,
                'eco_points',
                $proposal['reward_details']['description'],
                ['proposal_id' => $proposal['id']]
            );
        }
        
        // Temperature reduction specific handling
        if ($proposal['type'] === 'temperature_reduction') {
            $this->gamificationService->awardTemperatureReductionPoints(
                $user,
                $proposal['details']['reduction_celsius']
            );
        }
        
        // Schedule the actual energy-saving action
        $this->scheduleEnergySavingAction($proposal, $room);
        
        // Log acceptance
        Log::info("Energy negotiation accepted", [
            'proposal_id' => $proposal['id'],
            'user_id' => $user->id,
            'room_id' => $room->id,
            'savings' => $proposal['savings_euro'],
        ]);
        
        return [
            'success' => true,
            'message' => 'Proposition acceptée ! Merci pour votre contribution écologique.',
            'points_awarded' => $proposal['reward_details']['points'] ?? 0,
            'reward' => $proposal['reward'],
            'action_scheduled' => true,
        ];
    }

    /**
     * Process negotiation counter-offer
     */
    private function processNegotiation(array $proposal, User $user, Room $room, array $parameters): array
    {
        if ($proposal['type'] === 'temperature_reduction') {
            $newTempReduction = $parameters['temperature_reduction'] ?? 0.5;
            $newDuration = $parameters['duration'] ?? 1.0;
            
            // Create counter-proposal
            $counterProposal = $this->createTemperatureReductionProposal($room, $user, 
                $proposal['details']['current_temperature'], 
                $this->getOutdoorTemperature()
            );
            
            // Modify with negotiated parameters
            $counterProposal['details']['reduction_celsius'] = $newTempReduction;
            $counterProposal['details']['duration_hours'] = $newDuration;
            $counterProposal['details']['proposed_temperature'] = 
                $proposal['details']['current_temperature'] - $newTempReduction;
            
            // Recalculate savings and rewards
            $newSavings = $proposal['savings_euro'] * ($newTempReduction / $proposal['details']['reduction_celsius']) * 
                         ($newDuration / $proposal['details']['duration_hours']);
            $counterProposal['savings_euro'] = $newSavings;
            $counterProposal['savings'] = number_format($newSavings, 2) . "€ économisés";
            $counterProposal['reward_details'] = $this->calculateReward($newSavings);
            $counterProposal['reward'] = $counterProposal['reward_details']['description'];
            
            // Cache counter-proposal
            $counterProposalId = 'nego_counter_' . uniqid();
            $counterProposal['id'] = $counterProposalId;
            $counterProposal['is_counter_offer'] = true;
            $counterProposal['original_proposal_id'] = $proposal['id'];
            
            Cache::put("negotiation_proposal_{$counterProposalId}", $counterProposal, now()->addMinutes(10));
            
            return [
                'success' => true,
                'message' => 'Contre-proposition générée',
                'counter_proposal' => $counterProposal,
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Négociation non disponible pour ce type de proposition',
        ];
    }

    /**
     * Process rejection of proposal
     */
    private function processRejection(array $proposal, User $user, Room $room): array
    {
        // Small penalty for rejection to encourage participation
        $this->gamificationService->awardPoints(
            $user,
            'energy_negotiation_rejected',
            "Proposition refusée",
            ['proposal_id' => $proposal['id']]
        );
        
        Log::info("Energy negotiation rejected", [
            'proposal_id' => $proposal['id'],
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);
        
        return [
            'success' => true,
            'message' => 'Proposition refusée. Merci pour votre retour.',
            'points_lost' => 0,
        ];
    }

    /**
     * Calculate reward based on savings amount
     */
    private function calculateReward(float $savingsEuro): array
    {
        if ($savingsEuro >= self::REWARDS['team_bonus']['threshold']) {
            return [
                'type' => 'team_bonus',
                'description' => self::REWARDS['team_bonus']['name'],
                'points' => $savingsEuro * self::REWARDS['eco_points']['multiplier'],
                'value_euro' => self::REWARDS['team_bonus']['value_euro'],
            ];
        }
        
        if ($savingsEuro >= self::REWARDS['early_leave']['threshold']) {
            return [
                'type' => 'early_leave',
                'description' => self::REWARDS['early_leave']['name'],
                'points' => $savingsEuro * self::REWARDS['eco_points']['multiplier'],
                'value_euro' => self::REWARDS['early_leave']['value_euro'],
            ];
        }
        
        if ($savingsEuro >= self::REWARDS['lunch_voucher']['threshold']) {
            return [
                'type' => 'lunch_voucher',
                'description' => self::REWARDS['lunch_voucher']['name'],
                'points' => $savingsEuro * self::REWARDS['eco_points']['multiplier'],
                'cost' => self::REWARDS['lunch_voucher']['cost'],
            ];
        }
        
        if ($savingsEuro >= self::REWARDS['coffee_voucher']['threshold']) {
            return [
                'type' => 'coffee_voucher',
                'description' => (int)($savingsEuro * self::REWARDS['eco_points']['multiplier']) . ' ' . self::REWARDS['coffee_voucher']['name'],
                'points' => $savingsEuro * self::REWARDS['eco_points']['multiplier'],
                'cost' => self::REWARDS['coffee_voucher']['cost'],
            ];
        }
        
        // Default reward: eco points only
        return [
            'type' => 'eco_points',
            'description' => (int)($savingsEuro * self::REWARDS['eco_points']['multiplier']) . ' ' . self::REWARDS['eco_points']['name'],
            'points' => $savingsEuro * self::REWARDS['eco_points']['multiplier'],
        ];
    }

    /**
     * Get active negotiation proposals for user
     */
    public function getActiveProposalsForUser(User $user): Collection
    {
        $organizationRooms = $user->organization->buildings()
            ->with('rooms')
            ->get()
            ->pluck('rooms')
            ->flatten();
        
        $activeProposals = collect();
        
        foreach ($organizationRooms as $room) {
            // Check if there are any cached proposals for this room
            $cacheKeys = Cache::getRedis()->keys("laravel_cache:negotiation_proposal_*");
            
            foreach ($cacheKeys as $cacheKey) {
                $proposal = Cache::get(str_replace('laravel_cache:', '', $cacheKey));
                
                if ($proposal && 
                    $proposal['room_id'] === $room->id && 
                    $proposal['user_id'] === $user->id &&
                    now()->lt($proposal['expires_at'])) {
                    $activeProposals->push($proposal);
                }
            }
        }
        
        return $activeProposals;
    }

    // Helper methods

    private function getCurrentTemperature(Room $room): float
    {
        return Cache::remember("room_temperature_{$room->id}", now()->addMinutes(5), function () use ($room) {
            $latestReading = $room->sensors()
                ->join('sensor_data', 'sensors.id', '=', 'sensor_data.sensor_id')
                ->where('sensor_data.timestamp', '>=', now()->subMinutes(30))
                ->whereNotNull('sensor_data.temperature')
                ->orderBy('sensor_data.timestamp', 'desc')
                ->first();
            
            return $latestReading ? $latestReading->temperature : 21.0; // Default room temperature
        });
    }

    private function getOutdoorTemperature(): float
    {
        return Cache::remember('outdoor_temperature', now()->addMinutes(15), function () {
            // In production, this would call a weather API
            return 15.0; // Default outdoor temperature
        });
    }

    private function getOpenDoors(Room $room): array
    {
        return Cache::remember("open_doors_{$room->id}", now()->addMinutes(1), function () use ($room) {
            $openDoors = [];
            
            $sensors = $room->sensors()
                ->join('sensor_data', 'sensors.id', '=', 'sensor_data.sensor_id')
                ->where('sensor_data.timestamp', '>=', now()->subMinutes(5))
                ->where('sensor_data.door_state', true)
                ->get();
            
            foreach ($sensors as $sensor) {
                $openDoors[] = [
                    'sensor_id' => $sensor->id,
                    'sensor_name' => $sensor->name ?? "Capteur {$sensor->id}",
                    'location' => $sensor->location,
                ];
            }
            
            return $openDoors;
        });
    }

    private function scheduleEnergySavingAction(array $proposal, Room $room): void
    {
        // In production, this would integrate with building management systems
        Log::info("Energy saving action scheduled", [
            'proposal_id' => $proposal['id'],
            'room_id' => $room->id,
            'action_type' => $proposal['type'],
            'scheduled_for' => now()->addMinutes(5),
        ]);
        
        // For now, we'll just cache the action to be processed later
        Cache::put("scheduled_action_{$proposal['id']}", [
            'proposal' => $proposal,
            'room_id' => $room->id,
            'scheduled_at' => now(),
            'execute_at' => now()->addMinutes(5),
        ], now()->addHours(24));
    }
}