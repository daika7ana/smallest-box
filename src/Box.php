<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

/**
 * Immutable value object representing a box with width, length, and height.
 */
class Box extends Dimensional
{
    /**
     * Returns true if this box can contain an item with the given dimensions.
     *
     * This check is axis-aligned only and does not consider rotation.
     *
     * @see SmallestBoxFinder::find() For rotation-aware packing.
     */
    public function fits(float $itemWidth, float $itemLength, float $itemHeight): bool
    {
        return $itemWidth <= $this->width()
            && $itemLength <= $this->length()
            && $itemHeight <= $this->height();
    }

    /**
     * Compare by volume. Returns negative if smaller, positive if larger, 0 if equal.
     */
    public function compareTo(Box $other): int
    {
        return $this->volume() <=> $other->volume();
    }

    public function __toString(): string
    {
        return sprintf('%.2f x %.2f x %.2f', $this->width(), $this->length(), $this->height());
    }
}
