<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\EnergyCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnergyCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnergyCalculatorService $energyCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->energyCalculator = new EnergyCalculatorService();
    }

    /**
     * Test EcoComfort specific formula: Watts perdus = ΔT × Surface × Coefficient U
     */
    public function test_ecocomfort_energy_loss_formula()
    {
        // Test case 1: Standard conditions
        $result = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 21.0,   // °C
            outdoorTemp: 15.0,  // °C  
            roomSurface: 25.0,  // m²
            openingType: 'door'
        );

        // ΔT = 21 - 15 = 6°C
        // Surface = 25 m²
        // U coefficient door = 3.5 W/(m²·K)
        // Expected: 6 × 25 × 3.5 = 525 W
        $expectedWatts = 6.0 * 25.0 * 3.5;
        
        $this->assertEquals($expectedWatts, $result['energy_loss_watts']);
        $this->assertEquals(6.0, $result['deltaT']);
        $this->assertEquals(25.0, $result['surface_m2']);
        $this->assertEquals(3.5, $result['u_coefficient']);
        $this->assertEquals('door', $result['opening_type']);
    }

    /**
     * Test cost calculation based on EDF tariff
     */
    public function test_edf_tariff_cost_calculation()
    {
        $result = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 22.0,
            outdoorTemp: 18.0,
            roomSurface: 20.0,
            openingType: 'window'
        );

        // ΔT = 4°C, Surface = 20m², U = 2.8 W/(m²·K)
        // Energy loss = 4 × 20 × 2.8 = 224 W = 0.224 kW
        // Cost per hour = 0.224 × 0.1740 = 0.038976 €/h
        $expectedCostPerHour = (4.0 * 20.0 * 2.8 / 1000) * 0.1740;
        
        $this->assertEquals(round($expectedCostPerHour, 4), $result['cost_impact_euro_per_hour']);
        $this->assertArrayHasKey('yearly_cost_projection', $result);
        $this->assertArrayHasKey('daily_cost_projection', $result);
    }

    /**
     * Test performance requirement <25ms
     */
    public function test_energy_calculation_performance_under_25ms()
    {
        $performanceTests = [];
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $result = $this->energyCalculator->calculateEnergyLossEcoComfort(
                indoorTemp: 20.0 + rand(-5, 10),  // 15-30°C
                outdoorTemp: 10.0 + rand(-10, 20), // 0-30°C
                roomSurface: 10.0 + rand(5, 40),   // 15-50 m²
                openingType: rand(0, 1) ? 'door' : 'window'
            );
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $performanceTests[] = $processingTime;
            
            // Individual test should be <25ms
            $this->assertLessThan(25.0, $processingTime, 
                "Energy calculation must be <25ms, got {$processingTime}ms for iteration {$i}");
        }

        $averageTime = array_sum($performanceTests) / count($performanceTests);
        $maxTime = max($performanceTests);
        
        echo "\nEnergy Calculation Performance:\n";
        echo "Average: {$averageTime}ms\n"; 
        echo "Maximum: {$maxTime}ms\n";
        
        // Average should be well under 25ms
        $this->assertLessThan(10.0, $averageTime, "Average processing time should be <10ms, got {$averageTime}ms");
    }

    /**
     * Test different opening types (door vs window)
     */
    public function test_opening_type_coefficients()
    {
        $conditions = [
            'indoorTemp' => 20.0,
            'outdoorTemp' => 10.0,
            'roomSurface' => 30.0,
        ];

        $doorResult = $this->energyCalculator->calculateEnergyLossEcoComfort(
            ...array_merge($conditions, ['openingType' => 'door'])
        );

        $windowResult = $this->energyCalculator->calculateEnergyLossEcoComfort(
            ...array_merge($conditions, ['openingType' => 'window'])
        );

        // Door should have higher U coefficient (3.5 vs 2.8)
        $this->assertGreaterThan($windowResult['energy_loss_watts'], $doorResult['energy_loss_watts']);
        $this->assertEquals(3.5, $doorResult['u_coefficient']);
        $this->assertEquals(2.8, $windowResult['u_coefficient']);
    }

    /**
     * Test edge cases
     */
    public function test_edge_cases()
    {
        // No temperature difference
        $result1 = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 20.0,
            outdoorTemp: 20.05, // Very small difference
            roomSurface: 25.0,
            openingType: 'door'
        );

        $this->assertEquals(0.0, $result1['energy_loss_watts']);
        $this->assertEquals(0.0, $result1['cost_impact_euro_per_hour']);

        // Very large temperature difference
        $result2 = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 25.0,
            outdoorTemp: -5.0, // 30°C difference
            roomSurface: 50.0,
            openingType: 'door'
        );

        // Should handle large differences correctly
        $expectedWatts = 30.0 * 50.0 * 3.5; // 5250 W
        $this->assertEquals($expectedWatts, $result2['energy_loss_watts']);
    }

    /**
     * Test ROI calculation accuracy
     */
    public function test_roi_calculations()
    {
        $result = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 23.0,
            outdoorTemp: 18.0,
            roomSurface: 20.0,
            openingType: 'door'
        );

        // Verify yearly projection (8760 hours per year)
        $expectedYearly = $result['cost_impact_euro_per_hour'] * 8760;
        $this->assertEquals(round($expectedYearly, 2), $result['yearly_cost_projection']);

        // Verify daily projection (24 hours per day)
        $expectedDaily = $result['cost_impact_euro_per_hour'] * 24;
        $this->assertEquals(round($expectedDaily, 2), $result['daily_cost_projection']);

        // ROI should be demonstrable - cost should be > 0 for temperature differences
        $this->assertGreaterThan(0, $result['cost_impact_euro_per_hour']);
        $this->assertGreaterThan(0, $result['yearly_cost_projection']);
    }

    /**
     * Test formula consistency with thermodynamic principles
     */
    public function test_thermodynamic_consistency()
    {
        $baseResult = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 20.0,
            outdoorTemp: 15.0,
            roomSurface: 20.0,
            openingType: 'door'
        );

        // Double the temperature difference -> double the energy loss
        $doubleTempResult = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 25.0, // Same ΔT but doubled: (25-15) vs (20-15)
            outdoorTemp: 15.0,
            roomSurface: 20.0,
            openingType: 'door'
        );

        $this->assertEquals(
            $baseResult['energy_loss_watts'] * 2, 
            $doubleTempResult['energy_loss_watts'],
            'Energy loss should be proportional to temperature difference'
        );

        // Double the surface -> double the energy loss
        $doubleSurfaceResult = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 20.0,
            outdoorTemp: 15.0,
            roomSurface: 40.0, // Doubled surface
            openingType: 'door'
        );

        $this->assertEquals(
            $baseResult['energy_loss_watts'] * 2,
            $doubleSurfaceResult['energy_loss_watts'],
            'Energy loss should be proportional to surface area'
        );
    }

    /**
     * Test advanced thermodynamic method compatibility
     */
    public function test_advanced_method_compatibility()
    {
        // Test that the advanced method still works
        $advancedResult = $this->energyCalculator->calculateEnergyLoss(
            indoorTemp: 21.0,
            outdoorTemp: 15.0,
            roomVolume: 75.0, // 20m² × 3.75m height
            openingType: 'door'
        );

        // Should return a reasonable energy loss value
        $this->assertGreaterThan(0, $advancedResult);
        $this->assertIsFloat($advancedResult);
    }

    /**
     * Test cost calculation with custom tariff
     */
    public function test_custom_tariff_calculation()
    {
        $result = $this->energyCalculator->calculateEnergyLossEcoComfort(
            indoorTemp: 22.0,
            outdoorTemp: 16.0,
            roomSurface: 25.0,
            openingType: 'door'
        );

        // Test with calculateCost method using custom tariff
        $energyLossKwh = $result['energy_loss_watts'] / 1000;
        $customCost = $this->energyCalculator->calculateCost($energyLossKwh, 0.20); // 20 cents/kWh

        $expectedCustomCost = round($energyLossKwh * 0.20, 2);
        $this->assertEquals($expectedCustomCost, $customCost);
    }
}