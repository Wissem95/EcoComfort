<?php

use App\Services\DoorDetection\AccelerometerNormalizer;
use App\Data\AccelerometerData;

it('normalizes Wirepas scale to g-force correctly', function () {
    $normalizer = new AccelerometerNormalizer();
    
    $result = $normalizer->normalize(64.0, -32.0, 128.0);
    
    expect($result)
        ->toBeInstanceOf(AccelerometerData::class)
        ->and($result->x)->toBe(1.0)
        ->and($result->y)->toBe(-0.5)
        ->and($result->z)->toBe(2.0);
});

it('calculates signal clarity accurately', function (float $x, float $y, float $z, float $expectedClarity) {
    $normalizer = new AccelerometerNormalizer();
    $data = new AccelerometerData($x, $y, $z);
    
    expect($normalizer->calculateSignalClarity($data))
        ->toBeFloat()
        ->toBeBetween($expectedClarity - 0.1, $expectedClarity + 0.1);
})->with('sensor_readings');

it('handles perfect 1g signal', function () {
    $normalizer = new AccelerometerNormalizer();
    $data = new AccelerometerData(0.0, 0.0, 1.0); // Perfect vertical 1g
    
    $clarity = $normalizer->calculateSignalClarity($data);
    
    expect($clarity)->toBeGreaterThan(95.0);
});

it('penalizes weak signals', function () {
    $normalizer = new AccelerometerNormalizer();
    $data = new AccelerometerData(0.1, 0.1, 0.1); // Very weak signal
    
    $clarity = $normalizer->calculateSignalClarity($data);
    
    expect($clarity)->toBeLessThan(50.0);
});

it('handles zero magnitude gracefully', function () {
    $normalizer = new AccelerometerNormalizer();
    $data = new AccelerometerData(0.0, 0.0, 0.0);
    
    expect(fn() => $normalizer->calculateSignalClarity($data))
        ->not->toThrow(Exception::class);
});