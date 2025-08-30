<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Event;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add a column to track which calculation version was used
        Schema::table('events', function (Blueprint $table) {
            $table->string('cost_calculation_version', 10)->default('v1')->after('cost_impact');
        });

        // Recalculate cost_impact for existing events using correct formula
        $this->recalculateEventCosts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the version column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cost_calculation_version');
        });
    }

    /**
     * Recalculate event costs with correct formula
     */
    private function recalculateEventCosts(): void
    {
        echo "🔄 Recalculating event costs with correct formula...\n";
        
        $totalBefore = Event::sum('cost_impact');
        echo "📊 Total cost before: {$totalBefore}€\n";
        
        $updatedCount = 0;

        // Process temperature_high events
        Event::where('type', 'temperature_high')
            ->whereNotNull('data')
            ->chunk(100, function ($events) use (&$updatedCount) {
                foreach ($events as $event) {
                    $data = $event->data;
                    
                    if (!isset($data['deviation'])) {
                        continue;
                    }
                    
                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30; // Default to 30m² if not set
                    
                    // New formula: 20W per m² per degree deviation, calculated per HOUR
                    $extraWatts = $deviation * $surfaceM2 * 20;
                    $hourlyKwh = $extraWatts / 1000; // Convert W to kW for 1 hour
                    $newCostImpact = round($hourlyKwh * 0.1740, 4); // EDF tariff
                    
                    $event->update([
                        'cost_impact' => $newCostImpact,
                        'cost_calculation_version' => 'v2'
                    ]);
                    
                    $updatedCount++;
                }
            });

        // Process humidity_high events
        Event::where('type', 'humidity_high')
            ->whereNotNull('data')
            ->chunk(100, function ($events) use (&$updatedCount) {
                foreach ($events as $event) {
                    $data = $event->data;
                    
                    if (!isset($data['deviation'])) {
                        continue;
                    }
                    
                    $deviation = (float) $data['deviation'];
                    $surfaceM2 = $event->room->surface_m2 ?? 30; // Default to 30m² if not set
                    
                    // New formula for humidity: efficiency loss calculation per HOUR
                    $efficiencyLoss = min(0.2, $deviation / 100); // Max 20% efficiency loss
                    $baseWatts = $surfaceM2 * 15; // 15W per m² base HVAC
                    $extraWatts = $baseWatts * $efficiencyLoss;
                    $hourlyKwh = $extraWatts / 1000; // Convert W to kW for 1 hour
                    $newCostImpact = round($hourlyKwh * 0.1740, 4); // EDF tariff
                    
                    $event->update([
                        'cost_impact' => $newCostImpact,
                        'cost_calculation_version' => 'v2'
                    ]);
                    
                    $updatedCount++;
                }
            });

        $totalAfter = Event::sum('cost_impact');
        $reduction = round(($totalBefore - $totalAfter) / $totalBefore * 100, 1);
        
        echo "✅ Updated {$updatedCount} events\n";
        echo "📊 Total cost after: {$totalAfter}€\n";
        echo "📉 Cost reduction: {$reduction}% (saved " . round($totalBefore - $totalAfter, 2) . "€)\n";
        echo "🎯 Events now use realistic hourly cost calculation\n";
    }
};