<?php

use App\Services\DoorDetection\DoorStateAnalyzer;
use App\Data\AccelerometerData;
use App\Data\DoorStateData;
use App\Data\CalibrationData;

beforeEach(function () {
    $this->analyzer = new DoorStateAnalyzer();
});

it('detects closed door from vertical position', function () {
    $data = new AccelerometerData(0.1, 0.2, 0.9); // Vertical position
    
    $result = $this->analyzer->analyze($data);
    
    expect($result)
        ->toBeInstanceOf(DoorStateData::class)
        ->and($result->state)->toBe('closed')
        ->and($result->certainty)->toBe('CERTAIN')
        ->and($result->confidence)->toBeGreaterThan(80.0);
});

it('detects opened door from horizontal position', function () {
    $data = new AccelerometerData(0.8, 0.2, 0.3); // Horizontal position
    
    $result = $this->analyzer->analyze($data);
    
    expect($result->isOpen())->toBeTrue()
        ->and($result->confidence)->toBeGreaterThan(60.0);
});

it('processes in less than 25ms', function () {
    $data = new AccelerometerData(0.1, 0.2, 0.9);
    
    expect(fn() => $this->analyzer->analyze($data))
        ->toBePerformant(25);
});

it('improves confidence with movement context', function () {
    $data = new AccelerometerData(0.8, 0.2, 0.3);
    $movementContext = [
        'movement_magnitude' => 30, // Significant movement
        'movement_delta' => ['x' => 25, 'y' => 5, 'z' => -20]
    ];
    
    $resultWithoutContext = $this->analyzer->analyze($data);
    $resultWithContext = $this->analyzer->analyze($data, null, $movementContext);
    
    expect($resultWithContext->confidence)
        ->toBeGreaterThan($resultWithoutContext->confidence);
});

it('uses calibration data when available', function () {
    $data = new AccelerometerData(0.1, 0.2, 0.9);
    $calibration = new CalibrationData(
        openingType: 'door',
        tolerance: 5.0,
        closedReference: ['x' => 6, 'y' => 13, 'z' => 58] // Close to current position
    );
    
    $result = $this->analyzer->analyze($data, $calibration);
    
    expect($result->certainty)->toBe('CERTAIN')
        ->and($result->confidence)->toBeGreaterThan(90.0);
});

it('handles ambiguous positions', function () {
    $data = new AccelerometerData(0.5, 0.5, 0.7); // Ambiguous 45-degree angle
    
    $result = $this->analyzer->analyze($data);
    
    expect($result->needsConfirmation)->toBeTrue()
        ->and($result->certainty)->toBe('UNCERTAIN');
});