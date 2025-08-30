<?php

namespace App\Services\DoorDetection;

use App\Data\AccelerometerData;
use App\Data\DoorStateData;
use App\Data\CalibrationData;

class DoorStateAnalyzer
{
    // Door detection thresholds
    private const ANGLE_CHANGE_THRESHOLD = 2; // degrees - As specified in requirements
    private const VERTICAL_THRESHOLD = 15.0; // degrees
    private const HORIZONTAL_THRESHOLD = 30.0; // degrees
    
    public function analyze(
        AccelerometerData $data,
        ?CalibrationData $calibration = null,
        ?array $movementContext = null
    ): DoorStateData {
        $startTime = microtime(true);
        
        $angle = $data->angle();
        $magnitude = $data->magnitude();
        
        if ($calibration) {
            $result = $this->analyzeWithCalibration($data, $calibration, $movementContext);
        } else {
            $result = $this->analyzeWithAngle($data, $movementContext);
        }
        
        if ($movementContext) {
            $result = $this->applyMovementContext($result, $movementContext);
        }
        
        $processingTimeMs = (microtime(true) - $startTime) * 1000;
        
        return new DoorStateData(
            state: $result['state'],
            certainty: $result['certainty'],
            confidence: $result['confidence'],
            needsConfirmation: $result['needs_confirmation'],
            openingType: $result['opening_type'],
            angle: $angle,
            magnitude: $magnitude,
            processingTimeMs: $processingTimeMs,
            accelerometer: $data,
            movementContext: $movementContext
        );
    }
    
    private function analyzeWithCalibration(
        AccelerometerData $data,
        CalibrationData $calibration,
        ?array $movementContext = null
    ): array {
        $wirepasData = $data->toWirepasScale();
        $closedRef = $calibration->closedReference;
        $tolerance = $calibration->tolerance;
        
        // Calculate distance from closed reference position
        $distance = sqrt(
            pow($wirepasData['x'] - $closedRef['x'], 2) +
            pow($wirepasData['y'] - $closedRef['y'], 2) +
            pow($wirepasData['z'] - $closedRef['z'], 2)
        );
        
        $withinTolerance = $distance <= $tolerance;
        
        return [
            'state' => $withinTolerance ? 'closed' : 'opened',
            'certainty' => $withinTolerance ? 'CERTAIN' : 'PROBABLE',
            'confidence' => $withinTolerance ? 95.0 : 85.0,
            'needs_confirmation' => !$withinTolerance && $distance > $tolerance * 1.5,
            'opening_type' => $calibration->openingType,
        ];
    }
    
    private function analyzeWithAngle(AccelerometerData $data, ?array $movementContext = null): array
    {
        $angle = $data->angle();
        $magnitude = $data->magnitude();
        
        // Signal quality affects confidence
        $signalQuality = $this->calculateSignalQuality($magnitude);
        
        if ($data->isVertical(self::VERTICAL_THRESHOLD)) {
            return [
                'state' => 'closed',
                'certainty' => $signalQuality > 0.8 ? 'CERTAIN' : 'PROBABLE',
                'confidence' => min(95.0, $signalQuality * 100),
                'needs_confirmation' => false,
                'opening_type' => 'door', // Default assumption
            ];
        } elseif ($angle > self::HORIZONTAL_THRESHOLD) {
            $confidence = min(90.0, $signalQuality * 100);
            return [
                'state' => $confidence > 80.0 ? 'opened' : 'probably_opened',
                'certainty' => $confidence > 85.0 ? 'PROBABLE' : 'UNCERTAIN',
                'confidence' => $confidence,
                'needs_confirmation' => $confidence < 80.0,
                'opening_type' => 'door',
            ];
        } else {
            // Ambiguous angle range
            return [
                'state' => 'probably_opened',
                'certainty' => 'UNCERTAIN',
                'confidence' => 60.0,
                'needs_confirmation' => true,
                'opening_type' => 'door',
            ];
        }
    }
    
    private function applyMovementContext(array $result, array $movementContext): array
    {
        $movementMagnitude = $movementContext['movement_magnitude'] ?? 0;
        
        // Significant movement increases confidence in state change
        if ($movementMagnitude > 20) { // Threshold for significant movement
            $result['confidence'] = min(100.0, $result['confidence'] * 1.1);
            if ($result['certainty'] === 'UNCERTAIN') {
                $result['certainty'] = 'PROBABLE';
            }
        }
        
        return $result;
    }
    
    private function calculateSignalQuality(float $magnitude): float
    {
        // Signal quality based on magnitude deviation from 1g
        $deviationFrom1G = abs($magnitude - 1.0);
        $quality = 1.0 - min($deviationFrom1G, 1.0);
        
        // Good signal range: 0.8g - 1.2g
        if ($magnitude >= 0.8 && $magnitude <= 1.2) {
            $quality = max(0.8, $quality);
        }
        
        return $quality;
    }
}