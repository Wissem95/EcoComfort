<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Between;
use Carbon\Carbon;

class EnergyImpactData extends Data
{
    public function __construct(
        #[Numeric, Between(0.0, 10000.0)]
        public float $energyLossWatts,
        
        #[Numeric, Between(0.0, 1000.0)]
        public float $costEuros,
        
        #[Numeric, Between(0.0, 10000.0)]
        public float $co2EmissionsGrams,
        
        #[Numeric, Between(0.0, 3600.0)]
        public float $durationSeconds,
        
        #[Numeric, Between(-50.0, 100.0)]
        public float $indoorTemperature,
        
        #[Numeric, Between(-50.0, 100.0)]
        public float $outdoorTemperature,
        
        #[Numeric, Between(0.0, 1000.0)]
        public float $roomSurfaceM2,
        
        public Carbon $calculatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'energy_loss_watts' => $this->energyLossWatts,
            'cost_euros' => $this->costEuros,
            'co2_emissions_grams' => $this->co2EmissionsGrams,
            'duration_seconds' => $this->durationSeconds,
            'indoor_temperature' => $this->indoorTemperature,
            'outdoor_temperature' => $this->outdoorTemperature,
            'temperature_delta' => abs($this->indoorTemperature - $this->outdoorTemperature),
            'room_surface_m2' => $this->roomSurfaceM2,
            'calculated_at' => $this->calculatedAt->toISOString(),
        ];
    }

    public function toDatabase(): array
    {
        return [
            'energy_loss_watts' => $this->energyLossWatts,
            'cost_euros' => $this->costEuros,
            'co2_emissions_grams' => $this->co2EmissionsGrams,
            'duration_seconds' => $this->durationSeconds,
            'temperature_delta' => abs($this->indoorTemperature - $this->outdoorTemperature),
        ];
    }

    public function getFormattedCost(): string
    {
        return '€' . number_format($this->costEuros, 4);
    }

    public function getFormattedCO2(): string
    {
        return number_format($this->co2EmissionsGrams, 1) . 'g CO₂';
    }

    public function getFormattedPowerLoss(): string
    {
        if ($this->energyLossWatts >= 1000) {
            return number_format($this->energyLossWatts / 1000, 2) . ' kW';
        }
        return number_format($this->energyLossWatts, 1) . ' W';
    }

    public function getDailyCostEstimate(): float
    {
        // Estimate daily cost if this energy loss continued for 24 hours
        return ($this->energyLossWatts / 1000) * 24 * 0.1740; // €0.1740/kWh EDF tariff
    }
}