<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class SensorReadingData extends Data
{
    public function __construct(
        public float $accelerationX,
        public float $accelerationY,
        public float $accelerationZ,
        public ?float $temperature = null,
        public ?float $humidity = null,
        public ?float $pressure = null,
        public ?float $batteryVoltage = null,
        public ?int $rssi = null,
        public ?Carbon $timestamp = null,
    ) {
        $this->timestamp = $this->timestamp ?? now();
    }

    public function normalized(): AccelerometerData
    {
        return new AccelerometerData(
            x: $this->accelerationX / 64.0,
            y: $this->accelerationY / 64.0,
            z: $this->accelerationZ / 64.0
        );
    }

    public function magnitude(): float
    {
        return sqrt(
            $this->accelerationX * $this->accelerationX +
            $this->accelerationY * $this->accelerationY +
            $this->accelerationZ * $this->accelerationZ
        );
    }
}