<?php

namespace App\Services\DoorDetection;

use App\Data\AccelerometerData;

class AccelerometerNormalizer
{
    public function normalize(float $x, float $y, float $z): AccelerometerData
    {
        // Normalisation Wirepas -> g-force
        return new AccelerometerData(
            x: $x / 64.0,
            y: $y / 64.0,
            z: $z / 64.0
        );
    }
    
    public function calculateSignalClarity(AccelerometerData $data): float
    {
        // Analyse qualité signal pour ajustement confiance
        $magnitude = $data->magnitude();
        
        // Signal parfait serait proche de 1g
        $deviationFrom1G = abs($magnitude - 1.0);
        
        // Calculer clarté basée sur la déviation
        // Plus proche de 1g = signal plus clair
        $clarity = 1.0 - min($deviationFrom1G, 1.0);
        
        // Ajuster pour les variations normales (0.8g - 1.2g = bon signal)
        if ($magnitude >= 0.8 && $magnitude <= 1.2) {
            $clarity = max(0.8, $clarity);
        }
        
        return $clarity * 100; // Retourne en pourcentage
    }
}