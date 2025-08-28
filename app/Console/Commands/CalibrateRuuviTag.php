<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sensor;

class CalibrateRuuviTag extends Command
{
    protected $signature = 'ruuvitag:calibrate 
                           {source_address : RuuviTag source address (ex: 422801533)}
                           {real_temperature : Real temperature measured with reference (ex: 19.0)}
                           {--displayed_temp= : Temperature currently displayed by RuuviTag (if different from latest)}';

    protected $description = 'Calibrate RuuviTag temperature offset based on real vs displayed temperature';

    public function handle(): int
    {
        $sourceAddress = $this->argument('source_address');
        $realTemp = (float) $this->argument('real_temperature');
        $displayedTemp = $this->option('displayed_temp');

        $this->info("🎯 CALIBRATION RUUVITAG {$sourceAddress}");
        $this->line("=========================================");

        // Find the RuuviTag sensor
        $sensor = Sensor::where('source_address', $sourceAddress)->first();
        
        if (!$sensor) {
            $this->error("❌ RuuviTag {$sourceAddress} not found in database");
            $this->info("💡 Available RuuviTag:");
            
            $sensors = Sensor::where('type', 'ruuvitag')->get(['source_address', 'name']);
            foreach ($sensors as $s) {
                $this->line("   • {$s->source_address} - {$s->name}");
            }
            
            return self::FAILURE;
        }

        // Get current displayed temperature
        if (!$displayedTemp) {
            $latestData = $sensor->latestData;
            if (!$latestData || !$latestData->temperature) {
                $this->error("❌ No recent temperature data found for this RuuviTag");
                $this->info("💡 Please provide --displayed_temp option or ensure sensor is sending data");
                return self::FAILURE;
            }
            $displayedTemp = (float) $latestData->temperature;
        } else {
            $displayedTemp = (float) $displayedTemp;
        }

        // Calculate offset needed
        $currentOffset = $sensor->temperature_offset ?? 0.0;
        $neededOffset = $realTemp - $displayedTemp;
        $newOffset = $currentOffset + $neededOffset;

        $this->info("📊 CALIBRATION ANALYSIS:");
        $this->line("   🌡️  Real Temperature: {$realTemp}°C");
        $this->line("   📱 Displayed Temperature: {$displayedTemp}°C");
        $this->line("   📏 Current Offset: {$currentOffset}°C");
        $this->line("   🔧 Needed Correction: {$neededOffset}°C");
        $this->line("   ⚖️  New Offset: {$newOffset}°C");
        $this->line("");

        if (abs($neededOffset) < 0.1) {
            $this->info("✅ RuuviTag is already well calibrated (error < 0.1°C)");
            return self::SUCCESS;
        }

        if (abs($neededOffset) > 15) {
            $this->error("⚠️  Large offset detected ({$neededOffset}°C)");
            $this->error("   This could indicate a sensor malfunction");
            
            if (!$this->confirm("Continue calibration anyway?")) {
                return self::FAILURE;
            }
        }

        // Apply calibration
        if ($this->confirm("Apply calibration offset of {$neededOffset}°C?")) {
            $sensor->update([
                'temperature_offset' => $newOffset
            ]);

            $this->info("✅ Calibration applied successfully!");
            $this->line("   📝 Updated temperature_offset: {$newOffset}°C");
            $this->line("   🎯 Future readings will be automatically corrected");
            $this->line("");
            $this->info("🔄 NEXT STEPS:");
            $this->line("   1. Wait for new sensor data");
            $this->line("   2. Verify corrected temperature in dashboard");
            $this->line("   3. Re-run calibration if needed");
            
            return self::SUCCESS;
        }

        $this->info("❌ Calibration cancelled");
        return self::FAILURE;
    }
}
