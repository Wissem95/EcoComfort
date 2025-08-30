<?php

dataset('sensor_readings', [
    // Normal readings
    [0.1, 0.2, 0.9, 85.0], // Vertical position
    [-0.1, -0.2, -0.9, 85.0], // Inverted vertical
    [0.8, 0.2, 0.3, 65.0], // Horizontal (opened)
    [-0.8, -0.2, -0.3, 65.0], // Horizontal opposite
    
    // Edge cases
    [0.0, 0.0, 1.0, 90.0], // Perfect vertical
    [1.0, 0.0, 0.0, 40.0], // Perfect horizontal
    [0.707, 0.0, 0.707, 75.0], // 45-degree angle
    
    // Noisy readings
    [0.15, 0.25, 0.85, 80.0], // Slightly noisy vertical
    [0.75, 0.15, 0.25, 60.0], // Slightly noisy horizontal
]);

dataset('mqtt_messages', [
    // Valid RuuviTag message
    [
        'topic' => 'gw-event/status/1',
        'payload' => json_encode([
            'sourceEndpoint' => 238,
            'destinationEndpoint' => 238,
            'queueDelay' => 0,
            'sourceAddress' => 123456,
            'hopCount' => 1,
            'data' => [
                'temperature' => 2234, // 22.34°C
                'humidity' => 5042, // 50.42%
                'pressure' => 100234, // 1002.34 hPa
                'accelerometer' => [64, -32, 128], // x=1.0g, y=-0.5g, z=2.0g
                'batteryVoltage' => 3000, // 3.0V
            ]
        ]),
    ],
    
    // Invalid message
    [
        'topic' => 'gw-event/status/1',
        'payload' => 'invalid json',
    ],
    
    // Missing data
    [
        'topic' => 'gw-event/status/1',
        'payload' => json_encode([
            'sourceAddress' => 123456,
            'data' => []
        ]),
    ],
]);

dataset('calibration_scenarios', [
    // Door calibration
    [
        'type' => 'door',
        'duration' => 30,
        'expected_positions' => ['closed', 'opened'],
    ],
    
    // Window calibration  
    [
        'type' => 'window',
        'duration' => 20,
        'expected_positions' => ['closed', 'probably_opened'],
    ],
    
    // Temperature offset calibration
    [
        'type' => 'temperature',
        'duration' => 60,
        'offset' => -1.5,
    ],
]);