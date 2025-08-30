<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class RecalculateEventCosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:recalculate-costs 
                            {--dry-run : Preview changes without applying them}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate event costs using the correct hourly formula instead of daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        $this->info('🔄 Event Cost Recalculation Tool');
        $this->info('================================');

        // Get current statistics
        $currentTotal = Event::sum('cost_impact');
        $temperatureEvents = Event::where('type', 'temperature_high')->count();
        $humidityEvents = Event::where('type', 'humidity_high')->count();

        $this->info("📊 Current Statistics:");
        $this->info("   Total Events: " . ($temperatureEvents + $humidityEvents));
        $this->info("   Temperature Events: {$temperatureEvents}");
        $this->info("   Humidity Events: {$humidityEvents}");
        $this->info("   Current Total Cost: {$currentTotal}€");

        // Calculate what the new total would be
        $newTotal = $this->calculateNewTotal();
        $reduction = $currentTotal > 0 ? round(($currentTotal - $newTotal) / $currentTotal * 100, 1) : 0;
        $savings = round($currentTotal - $newTotal, 2);

        $this->info("\n🎯 Projected Changes:");
        $this->info("   New Total Cost: {$newTotal}€");
        $this->info("   Cost Reduction: {$reduction}%");
        $this->info("   Amount Saved: {$savings}€");

        if ($isDryRun) {
            $this->warn("\n🔍 DRY RUN MODE - No changes will be applied");
            $this->showSampleCalculations();
            return;
        }

        // Ask for confirmation unless forced
        if (!$isForced) {
            if (!$this->confirm("\n❓ Apply these changes to the database?")) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        // Apply the changes
        $this->applyRecalculation();
    }

    /**
     * Calculate what the new total would be without applying changes
     */
    private function calculateNewTotal(): float
    {
        $newTotal = 0;

        // Calculate new costs for temperature events
        Event::where('type', 'temperature_high')
            ->whereNotNull('data')
            ->chunk(100, function ($events) use (&$newTotal) {
                foreach ($events as $event) {
                    $data = $event->data;
                    if (!isset($data['deviation'])) continue;

                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30;
                    
                    $extraWatts = $deviation * $surfaceM2 * 20;
                    $hourlyKwh = $extraWatts / 1000;
                    $newCost = $hourlyKwh * 0.1740;
                    
                    $newTotal += $newCost;
                }
            });

        // Calculate new costs for humidity events
        Event::where('type', 'humidity_high')
            ->whereNotNull('data')
            ->chunk(100, function ($events) use (&$newTotal) {
                foreach ($events as $event) {
                    $data = $event->data;
                    if (!isset($data['deviation'])) continue;

                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30;
                    
                    $efficiencyLoss = min(0.2, $deviation / 100);
                    $baseWatts = $surfaceM2 * 15;
                    $extraWatts = $baseWatts * $efficiencyLoss;
                    $hourlyKwh = $extraWatts / 1000;
                    $newCost = $hourlyKwh * 0.1740;
                    
                    $newTotal += $newCost;
                }
            });

        return round($newTotal, 2);
    }

    /**
     * Show sample calculations for dry run
     */
    private function showSampleCalculations(): void
    {
        $this->info("\n📋 Sample Calculations:");
        
        // Show a few examples
        $tempEvent = Event::where('type', 'temperature_high')
            ->whereNotNull('data')
            ->orderBy('cost_impact', 'desc')
            ->first();

        if ($tempEvent) {
            $data = $tempEvent->data;
            $deviation = (float) ($data['deviation'] ?? 0);
            $surfaceM2 = $tempEvent->room->surface_m2 ?? 30;
            
            $this->info("   Example Temperature Event:");
            $this->info("   - Current Cost: {$tempEvent->cost_impact}€");
            $this->info("   - Deviation: {$deviation}°C");
            $this->info("   - Room Surface: {$surfaceM2}m²");
            
            $extraWatts = $deviation * $surfaceM2 * 20;
            $hourlyKwh = $extraWatts / 1000;
            $newCost = round($hourlyKwh * 0.1740, 4);
            
            $this->info("   - New Cost: {$newCost}€/hour");
            $this->info("   - Reduction: " . round(($tempEvent->cost_impact - $newCost) / $tempEvent->cost_impact * 100, 1) . "%");
        }
    }

    /**
     * Apply the recalculation to the database
     */
    private function applyRecalculation(): void
    {
        $this->info("\n🔄 Applying recalculation...");
        
        $bar = $this->output->createProgressBar(Event::whereIn('type', ['temperature_high', 'humidity_high'])->count());
        $bar->start();

        $updatedCount = 0;

        // Update temperature events
        Event::where('type', 'temperature_high')
            ->whereNotNull('data')
            ->chunk(50, function ($events) use ($bar, &$updatedCount) {
                foreach ($events as $event) {
                    $data = $event->data;
                    if (!isset($data['deviation'])) {
                        $bar->advance();
                        continue;
                    }

                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30;
                    
                    $extraWatts = $deviation * $surfaceM2 * 20;
                    $hourlyKwh = $extraWatts / 1000;
                    $newCostImpact = round($hourlyKwh * 0.1740, 4);
                    
                    $event->update([
                        'cost_impact' => $newCostImpact,
                        'cost_calculation_version' => 'v2'
                    ]);
                    
                    $updatedCount++;
                    $bar->advance();
                }
            });

        // Update humidity events
        Event::where('type', 'humidity_high')
            ->whereNotNull('data')
            ->chunk(50, function ($events) use ($bar, &$updatedCount) {
                foreach ($events as $event) {
                    $data = $event->data;
                    if (!isset($data['deviation'])) {
                        $bar->advance();
                        continue;
                    }

                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30;
                    
                    $efficiencyLoss = min(0.2, $deviation / 100);
                    $baseWatts = $surfaceM2 * 15;
                    $extraWatts = $baseWatts * $efficiencyLoss;
                    $hourlyKwh = $extraWatts / 1000;
                    $newCostImpact = round($hourlyKwh * 0.1740, 4);
                    
                    $event->update([
                        'cost_impact' => $newCostImpact,
                        'cost_calculation_version' => 'v2'
                    ]);
                    
                    $updatedCount++;
                    $bar->advance();
                }
            });

        $bar->finish();

        $newTotal = Event::sum('cost_impact');
        
        $this->info("\n\n✅ Recalculation Complete!");
        $this->info("   Updated Events: {$updatedCount}");
        $this->info("   New Total Cost: {$newTotal}€");
        $this->info("   All events now use realistic hourly cost calculation");
    }
}