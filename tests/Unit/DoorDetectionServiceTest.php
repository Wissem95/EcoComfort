<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DoorDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class DoorDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DoorDetectionService $doorDetectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doorDetectionService = new DoorDetectionService();
        Cache::flush(); // Clear cache between tests
    }

    /**
     * Test door detection accuracy with known test cases
     * Must achieve >95% accuracy as specified
     */
    public function test_door_detection_accuracy_above_95_percent()
    {
        // Generate test data with known door states
        $testCases = $this->generateAccuracyTestCases();
        $totalTests = count($testCases);
        $correctDetections = 0;

        foreach ($testCases as $index => $testCase) {
            $sensorId = "test_sensor_{$index}";
            
            // Process the accelerometer data
            $result = $this->doorDetectionService->detectDoorState(
                $testCase['accel_x'],
                $testCase['accel_y'],
                $testCase['accel_z'],
                $sensorId
            );

            // Check if detection matches expected state
            if ($result['door_state'] === $testCase['expected_state']) {
                $correctDetections++;
            }
        }

        $accuracy = ($correctDetections / $totalTests) * 100;
        
        // Log accuracy for debugging
        echo "\nDoor Detection Accuracy: {$accuracy}% ({$correctDetections}/{$totalTests})\n";
        
        // Assert accuracy is >95% as specified in requirements
        $this->assertGreaterThan(95.0, $accuracy, "Door detection accuracy must be >95%, got {$accuracy}%");
    }

    /**
     * Test performance requirement <25ms
     */
    public function test_door_detection_performance_under_25ms()
    {
        $performanceTests = [];
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $sensorId = "perf_test_sensor_{$i}";
            
            // Random but realistic accelerometer data
            $accelX = rand(-100, 100) / 100.0; // -1.0 to 1.0 g
            $accelY = rand(-100, 100) / 100.0;
            $accelZ = rand(80, 120) / 100.0; // Close to 1g when vertical

            $startTime = microtime(true);
            
            $result = $this->doorDetectionService->detectDoorState($accelX, $accelY, $accelZ, $sensorId);
            
            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to ms
            $performanceTests[] = $processingTime;
            
            // Individual test should be <25ms
            $this->assertLessThan(25.0, $processingTime, 
                "Processing time must be <25ms, got {$processingTime}ms for iteration {$i}");
        }

        $averageTime = array_sum($performanceTests) / count($performanceTests);
        $maxTime = max($performanceTests);
        
        echo "\nPerformance Results:\n";
        echo "Average: {$averageTime}ms\n";
        echo "Maximum: {$maxTime}ms\n";
        
        // Average should be well under 25ms
        $this->assertLessThan(15.0, $averageTime, "Average processing time should be <15ms, got {$averageTime}ms");
    }

    /**
     * Test door/window distinction accuracy
     */
    public function test_opening_type_detection()
    {
        $doorTestCases = $this->generateDoorSignatureCases();
        $windowTestCases = $this->generateWindowSignatureCases();
        
        $correctDoorDetections = 0;
        $correctWindowDetections = 0;

        // Test door cases
        foreach ($doorTestCases as $index => $testCase) {
            $sensorId = "door_test_{$index}";
            
            // Process multiple samples to build signature
            for ($sample = 0; $sample < 10; $sample++) {
                $this->doorDetectionService->detectDoorState(
                    $testCase['accel_x'] + (rand(-5, 5) / 100), // Add noise
                    $testCase['accel_y'] + (rand(-5, 5) / 100),
                    $testCase['accel_z'] + (rand(-5, 5) / 100),
                    $sensorId
                );
            }
            
            $result = $this->doorDetectionService->detectDoorState(
                $testCase['accel_x'],
                $testCase['accel_y'],
                $testCase['accel_z'],
                $sensorId
            );

            if ($result['opening_type'] === 'door') {
                $correctDoorDetections++;
            }
        }

        // Test window cases
        foreach ($windowTestCases as $index => $testCase) {
            $sensorId = "window_test_{$index}";
            
            // Process multiple samples to build signature
            for ($sample = 0; $sample < 10; $sample++) {
                $this->doorDetectionService->detectDoorState(
                    $testCase['accel_x'] + (rand(-3, 3) / 100), // Less noise for windows
                    $testCase['accel_y'] + (rand(-3, 3) / 100),
                    $testCase['accel_z'] + (rand(-3, 3) / 100),
                    $sensorId
                );
            }
            
            $result = $this->doorDetectionService->detectDoorState(
                $testCase['accel_x'],
                $testCase['accel_y'],
                $testCase['accel_z'],
                $sensorId
            );

            if ($result['opening_type'] === 'window') {
                $correctWindowDetections++;
            }
        }

        $doorAccuracy = ($correctDoorDetections / count($doorTestCases)) * 100;
        $windowAccuracy = ($correctWindowDetections / count($windowTestCases)) * 100;
        $overallAccuracy = (($correctDoorDetections + $correctWindowDetections) / 
                           (count($doorTestCases) + count($windowTestCases))) * 100;

        echo "\nOpening Type Detection:\n";
        echo "Door Detection: {$doorAccuracy}%\n";
        echo "Window Detection: {$windowAccuracy}%\n";
        echo "Overall: {$overallAccuracy}%\n";

        // Should be able to distinguish doors from windows with reasonable accuracy
        $this->assertGreaterThan(80.0, $overallAccuracy, "Opening type detection should be >80%");
    }

    /**
     * Test confidence scoring
     */
    public function test_confidence_scoring()
    {
        $sensorId = "confidence_test_sensor";
        
        // Clear, unambiguous door opening (high confidence expected)
        $clearResult = $this->doorDetectionService->detectDoorState(
            0.8,  // Strong X acceleration
            0.1,  // Minimal Y 
            0.6,  // Reduced Z (tilted)
            $sensorId
        );

        // Noisy, ambiguous data (low confidence expected)
        $noisyResult = $this->doorDetectionService->detectDoorState(
            0.05, // Very small changes
            0.03,
            0.98,
            $sensorId
        );

        // Clear signal should have higher confidence
        $this->assertGreaterThan($noisyResult['confidence'], $clearResult['confidence'],
            "Clear signal should have higher confidence than noisy signal");
        
        // All confidence scores should be ≤95% as specified
        $this->assertLessThanOrEqual(95.0, $clearResult['confidence']);
        $this->assertLessThanOrEqual(95.0, $noisyResult['confidence']);
    }

    /**
     * Test 2° angle threshold as specified
     */
    public function test_2_degree_angle_threshold()
    {
        $sensorId = "angle_test_sensor";
        
        // Test data just below 2° change (should not trigger)
        $result1 = $this->doorDetectionService->detectDoorState(0.02, 0.01, 0.999, $sensorId);
        $result2 = $this->doorDetectionService->detectDoorState(0.03, 0.01, 0.998, $sensorId);
        
        // Test data above 2° change (should trigger)
        $result3 = $this->doorDetectionService->detectDoorState(0.1, 0.05, 0.95, $sensorId);
        
        // The significant change should be detected
        $this->assertNotEquals($result1['raw_state'], $result3['raw_state'],
            "2° threshold should trigger state change detection");
    }

    /**
     * Generate test cases with known door states for accuracy testing
     */
    private function generateAccuracyTestCases(): array
    {
        return [
            // Closed door cases (vertical sensor)
            ['accel_x' => 0.01, 'accel_y' => 0.02, 'accel_z' => 0.98, 'expected_state' => 'closed'],
            ['accel_x' => -0.01, 'accel_y' => 0.01, 'accel_z' => 0.99, 'expected_state' => 'closed'],
            ['accel_x' => 0.02, 'accel_y' => -0.01, 'accel_z' => 1.0, 'expected_state' => 'closed'],
            
            // Opening door cases (tilting sensor)
            ['accel_x' => 0.5, 'accel_y' => 0.1, 'accel_z' => 0.85, 'expected_state' => 'opened'],
            ['accel_x' => 0.7, 'accel_y' => 0.2, 'accel_z' => 0.7, 'expected_state' => 'opened'],
            ['accel_x' => 0.6, 'accel_y' => 0.15, 'accel_z' => 0.75, 'expected_state' => 'opened'],
            
            // Fully open door cases (horizontal sensor)
            ['accel_x' => 0.9, 'accel_y' => 0.05, 'accel_z' => 0.4, 'expected_state' => 'opened'],
            ['accel_x' => 0.85, 'accel_y' => 0.1, 'accel_z' => 0.5, 'expected_state' => 'opened'],
            ['accel_x' => 0.95, 'accel_y' => 0.02, 'accel_z' => 0.3, 'expected_state' => 'opened'],
            
            // Closing door cases
            ['accel_x' => 0.3, 'accel_y' => 0.1, 'accel_z' => 0.9, 'expected_state' => 'closed'],
            ['accel_x' => 0.2, 'accel_y' => 0.05, 'accel_z' => 0.95, 'expected_state' => 'closed'],
        ];
    }

    /**
     * Generate door-specific vibration signatures
     */
    private function generateDoorSignatureCases(): array
    {
        return [
            // Heavy doors - lower frequency, higher amplitude variance
            ['accel_x' => 0.6, 'accel_y' => 0.3, 'accel_z' => 0.7], // Strong motion
            ['accel_x' => 0.5, 'accel_y' => 0.4, 'accel_z' => 0.75], // Heavy swing
            ['accel_x' => 0.7, 'accel_y' => 0.2, 'accel_z' => 0.65], // Door momentum
        ];
    }

    /**
     * Generate window-specific vibration signatures
     */
    private function generateWindowSignatureCases(): array
    {
        return [
            // Light windows - higher frequency, lower amplitude variance
            ['accel_x' => 0.3, 'accel_y' => 0.1, 'accel_z' => 0.9], // Light motion
            ['accel_x' => 0.25, 'accel_y' => 0.15, 'accel_z' => 0.92], // Quick movement
            ['accel_x' => 0.35, 'accel_y' => 0.05, 'accel_z' => 0.88], // Window slide
        ];
    }

    /**
     * Test accuracy metrics calculation
     */
    public function test_accuracy_metrics()
    {
        $sensorId = "metrics_test_sensor";
        
        // Process several samples to build history
        for ($i = 0; $i < 20; $i++) {
            $this->doorDetectionService->detectDoorState(
                rand(-10, 10) / 100.0,
                rand(-10, 10) / 100.0,
                rand(95, 105) / 100.0,
                $sensorId
            );
        }
        
        $metrics = $this->doorDetectionService->getAccuracyMetrics($sensorId);
        
        $this->assertArrayHasKey('accuracy', $metrics);
        $this->assertArrayHasKey('confidence', $metrics);
        $this->assertArrayHasKey('stability', $metrics);
        $this->assertArrayHasKey('samples', $metrics);
        
        // Accuracy should be capped at 95% as specified
        $this->assertLessThanOrEqual(95.0, $metrics['accuracy']);
        
        // Should have processed the samples
        $this->assertEquals(20, $metrics['samples']);
    }
}