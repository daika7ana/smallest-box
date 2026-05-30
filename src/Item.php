<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

/**
 * Immutable value object representing a rectangular item with width, length, and height.
 */
class Item extends Dimensional
{
    /**
     * Returns all 6 axis-aligned rotations of this item.
     * Each rotation is a [width, length, height] tuple.
     *
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    public function rotations(): array
    {
        $w = $this->width();
        $l = $this->length();
        $h = $this->height();

        return [
            [$w, $l, $h],
            [$w, $h, $l],
            [$l, $w, $h],
            [$l, $h, $w],
            [$h, $w, $l],
            [$h, $l, $w],
        ];
    }
}
