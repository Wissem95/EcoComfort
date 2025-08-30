<?php

namespace App\Services\DoorDetection;

class KalmanFilterService
{
    private const PROCESS_NOISE = 0.01;
    private const MEASUREMENT_NOISE = 0.1;
    private const INITIAL_ESTIMATE_ERROR = 1.0;

    public function filter(float $measurement, ?array $previousState = null): array
    {
        if ($previousState === null) {
            // Initialize Kalman filter state
            return [
                'estimate' => $measurement,
                'error_covariance' => self::INITIAL_ESTIMATE_ERROR,
                'kalman_gain' => 0.0
            ];
        }

        // Predict step
        $predicted_estimate = $previousState['estimate'];
        $predicted_error_covariance = $previousState['error_covariance'] + self::PROCESS_NOISE;

        // Update step
        $kalman_gain = $predicted_error_covariance / ($predicted_error_covariance + self::MEASUREMENT_NOISE);
        $current_estimate = $predicted_estimate + $kalman_gain * ($measurement - $predicted_estimate);
        $current_error_covariance = (1 - $kalman_gain) * $predicted_error_covariance;

        return [
            'estimate' => $current_estimate,
            'error_covariance' => $current_error_covariance,
            'kalman_gain' => $kalman_gain
        ];
    }

    public function filterAccelerometer(
        float $accelX,
        float $accelY,
        float $accelZ,
        ?array $previousStates = null
    ): array {
        $filteredX = $this->filter($accelX, $previousStates['x'] ?? null);
        $filteredY = $this->filter($accelY, $previousStates['y'] ?? null);
        $filteredZ = $this->filter($accelZ, $previousStates['z'] ?? null);

        return [
            'x' => $filteredX,
            'y' => $filteredY,
            'z' => $filteredZ,
            'filtered_values' => [
                'x' => $filteredX['estimate'],
                'y' => $filteredY['estimate'],
                'z' => $filteredZ['estimate'],
            ]
        ];
    }
}