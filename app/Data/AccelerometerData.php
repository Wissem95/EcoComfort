<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Between;

class AccelerometerData extends Data
{
    public function __construct(
        #[Numeric, Between(-2.0, 2.0)]
        public float $x, // -2.0 to 2.0 g-force (reasonable range)
        
        #[Numeric, Between(-2.0, 2.0)]
        public float $y,
        
        #[Numeric, Between(-2.0, 2.0)]
        public float $z,
    ) {}

    public function magnitude(): float
    {
        return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z);
    }

    public function angle(): float
    {
        $magnitude = $this->magnitude();
        $magnitude = max(0.001, $magnitude); // Avoid division by zero
        return rad2deg(acos(abs($this->z) / $magnitude));
    }

    public function isVertical(float $threshold = 15.0): bool
    {
        return $this->angle() < $threshold && abs($this->z) > 0.9;
    }

    public function isHorizontal(float $threshold = 30.0): bool
    {
        return $this->angle() > $threshold;
    }

    public function toWirepasScale(): array
    {
        return [
            'x' => (int) round($this->x * 64.0),
            'y' => (int) round($this->y * 64.0),
            'z' => (int) round($this->z * 64.0)
        ];
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
            'magnitude' => $this->magnitude(),
            'angle' => $this->angle(),
        ];
    }

    public function toDatabase(): array
    {
        $wirepas = $this->toWirepasScale();
        return [
            'acceleration_x' => $wirepas['x'],
            'acceleration_y' => $wirepas['y'],
            'acceleration_z' => $wirepas['z'],
        ];
    }
}