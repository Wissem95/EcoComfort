<?php

use App\Data\AccelerometerData;

it('calculates magnitude correctly', function () {
    $data = new AccelerometerData(0.6, 0.8, 0.0); // 3-4-5 triangle
    
    expect($data->magnitude())->toBe(1.0);
});

it('calculates angle correctly', function () {
    $data = new AccelerometerData(0.0, 0.0, 1.0); // Perfect vertical
    
    expect($data->angle())->toBeLessThan(1.0); // Should be close to 0 degrees
});

it('detects vertical position', function () {
    $vertical = new AccelerometerData(0.0, 0.0, 1.0);
    $horizontal = new AccelerometerData(1.0, 0.0, 0.0);
    
    expect($vertical->isVertical())->toBeTrue()
        ->and($horizontal->isVertical())->toBeFalse();
});

it('converts to Wirepas scale correctly', function () {
    $data = new AccelerometerData(1.0, -0.5, 2.0);
    $wirepas = $data->toWirepasScale();
    
    expect($wirepas)
        ->toHaveKey('x', 64)
        ->toHaveKey('y', -32)
        ->toHaveKey('z', 128);
});

it('handles zero magnitude gracefully', function () {
    $data = new AccelerometerData(0.0, 0.0, 0.0);
    
    expect(fn() => $data->angle())->not->toThrow(Exception::class);
    expect($data->magnitude())->toBe(0.0);
});