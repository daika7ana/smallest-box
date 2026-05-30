<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

use InvalidArgumentException;

/**
 * Abstract base class for objects with width, length, and height.
 *
 * Provides property storage, constructor validation, dimension getters,
 * and volume calculation. Extended by both Box and Item.
 */
abstract class Dimensional
{
    private float $width;
    private float $length;
    private float $height;

    public function __construct(float $width, float $length, float $height)
    {
        if ($width <= 0 || $length <= 0 || $height <= 0) {
            throw new InvalidArgumentException('All dimensions must be positive.');
        }

        $this->width  = $width;
        $this->length = $length;
        $this->height = $height;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function length(): float
    {
        return $this->length;
    }

    public function height(): float
    {
        return $this->height;
    }

    public function volume(): float
    {
        return $this->width * $this->length * $this->height;
    }
}
