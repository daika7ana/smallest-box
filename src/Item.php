<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

/**
 * Immutable value object representing a rectangular item with width, length, and height.
 */
class Item extends Dimensional
{
    /** @var array<int, array{0: float, 1: float, 2: float}>|null */
    private ?array $uniqueRotations = null;

    /**
     * Returns all unique axis-aligned rotations of this item.
     * Each rotation is a [width, length, height] tuple.
     * The result is cached after the first call.
     *
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    public function rotations(): array
    {
        if ($this->uniqueRotations !== null) {
            return $this->uniqueRotations;
        }

        $w = $this->width();
        $l = $this->length();
        $h = $this->height();

        $rotations = [
            [$w, $l, $h],
            [$w, $h, $l],
            [$l, $w, $h],
            [$l, $h, $w],
            [$h, $w, $l],
            [$h, $l, $w],
        ];

        $seen = [];
        $unique = [];
        foreach ($rotations as $r) {
            $key = $r[0] . ',' . $r[1] . ',' . $r[2];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }

        $this->uniqueRotations = $unique;

        return $unique;
    }
}
