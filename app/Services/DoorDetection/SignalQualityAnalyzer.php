<?php

namespace App\Services\DoorDetection;

use App\Data\AccelerometerData;

class SignalQualityAnalyzer
{
    private const IDEAL_MAGNITUDE = 1.0; // 1g is ideal for Earth gravity
    private const ACCEPTABLE_RANGE = [0.8, 1.2]; // Acceptable magnitude range
    
    public function analyzeSignalClarity(AccelerometerData $data): array
    {
        $magnitude = $data->magnitude();
        
        return [
            'clarity_score' => $this->calculateClarityScore($magnitude),
            'magnitude_quality' => $this->assessMagnitudeQuality($magnitude),
            'noise_level' => $this->estimateNoiseLevel($data),
            'signal_stability' => $this->assessSignalStability($magnitude),
            'recommendations' => $this->generateRecommendations($magnitude, $data),
        ];
    }
    
    private function calculateClarityScore(float $magnitude): float
    {
        // Signal parfait serait proche de 1g
        $deviationFrom1G = abs($magnitude - self::IDEAL_MAGNITUDE);
        
        // Score de clarté basé sur la déviation
        $clarity = 1.0 - min($deviationFrom1G, 1.0);
        
        // Bonus pour les valeurs dans la plage acceptable
        if ($magnitude >= self::ACCEPTABLE_RANGE[0] && $magnitude <= self::ACCEPTABLE_RANGE[1]) {
            $clarity = max(0.8, $clarity);
        }
        
        return $clarity;
    }
    
    private function assessMagnitudeQuality(float $magnitude): string
    {
        if ($magnitude >= self::ACCEPTABLE_RANGE[0] && $magnitude <= self::ACCEPTABLE_RANGE[1]) {
            return 'excellent';
        } elseif ($magnitude >= 0.6 && $magnitude <= 1.4) {
            return 'good';
        } elseif ($magnitude >= 0.4 && $magnitude <= 1.6) {
            return 'acceptable';
        } else {
            return 'poor';
        }
    }
    
    private function estimateNoiseLevel(AccelerometerData $data): float
    {
        // Estimation du bruit basée sur la déviation des composantes
        $expectedMagnitude = $data->magnitude();
        
        // Si le magnitude est proche de 1, on s'attend à ce qu'une composante domine
        $dominantComponent = max(abs($data->x), abs($data->y), abs($data->z));
        $secondaryComponents = $expectedMagnitude - $dominantComponent;
        
        // Plus les composantes secondaires sont importantes, plus il y a de bruit
        return min(1.0, $secondaryComponents / $expectedMagnitude);
    }
    
    private function assessSignalStability(float $magnitude): string
    {
        $deviation = abs($magnitude - self::IDEAL_MAGNITUDE);
        
        if ($deviation < 0.05) return 'very_stable';
        if ($deviation < 0.1) return 'stable';
        if ($deviation < 0.2) return 'moderately_stable';
        return 'unstable';
    }
    
    private function generateRecommendations(float $magnitude, AccelerometerData $data): array
    {
        $recommendations = [];
        
        if ($magnitude < 0.6) {
            $recommendations[] = 'signal_too_weak';
            $recommendations[] = 'check_sensor_placement';
        }
        
        if ($magnitude > 1.4) {
            $recommendations[] = 'signal_too_strong';
            $recommendations[] = 'check_for_interference';
        }
        
        $noiseLevel = $this->estimateNoiseLevel($data);
        if ($noiseLevel > 0.3) {
            $recommendations[] = 'high_noise_detected';
            $recommendations[] = 'consider_recalibration';
        }
        
        if (abs($data->x) + abs($data->y) + abs($data->z) < 0.5) {
            $recommendations[] = 'low_activity_detected';
            $recommendations[] = 'sensor_may_be_disconnected';
        }
        
        return $recommendations;
    }
    
    public function shouldTriggerRecalibration(AccelerometerData $data): bool
    {
        $analysis = $this->analyzeSignalClarity($data);
        
        return $analysis['clarity_score'] < 0.5 || 
               in_array('high_noise_detected', $analysis['recommendations']) ||
               $analysis['magnitude_quality'] === 'poor';
    }
}