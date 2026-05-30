<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

/**
 * Internal helper for greedy 3D bin packing.
 *
 * Tracks placed items and remaining free-space regions (maximal empty cuboids).
 *
 * @internal
 */
class Placement
{
    /** @var array<int, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}> */
    private array $spaces;

    private const EPSILON = 0.001;

    public function __construct(float $boxWidth, float $boxLength, float $boxHeight)
    {
        $this->spaces = [
            [0.0, 0.0, 0.0, $boxWidth, $boxLength, $boxHeight],
        ];
    }

    /**
     * Attempt to place an item with the given dimensions inside the box.
     * Tries each free-space region and rotation.
     *
     * @param array{0: float, 1: float, 2: float} $dims [width, length, height]
     * @return bool true if placed successfully
     */
    public function place(array $dims): bool
    {
        $uniqueRotations = $this->deduplicateRotations($dims);

        // Sort spaces by bottom-left-front heuristic (smallest coordinates first)
        usort($this->spaces, function (array $a, array $b): int {
            if ($a[2] !== $b[2]) {
                return $a[2] <=> $b[2]; // z first (height)
            }
            if ($a[1] !== $b[1]) {
                return $a[1] <=> $b[1]; // then y (length)
            }
            return $a[0] <=> $b[0]; // then x (width)
        });

        foreach ($this->spaces as $si => $space) {
            foreach ($uniqueRotations as $rot) {
                if ($rot[0] <= $space[3] && $rot[1] <= $space[4] && $rot[2] <= $space[5]) {
                    $this->splitSpace($si, $rot[0], $rot[1], $rot[2]);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generate all unique rotations of the given dimensions, deduplicating the
     * six permutations where any dimensions are equal.
     *
     * @param array{0: float, 1: float, 2: float} $dims [width, length, height]
     * @return list<array{0: float, 1: float, 2: float}>
     */
    private function deduplicateRotations(array $dims): array
    {
        $rotations = [
            $dims,
            [$dims[0], $dims[2], $dims[1]],
            [$dims[1], $dims[0], $dims[2]],
            [$dims[1], $dims[2], $dims[0]],
            [$dims[2], $dims[0], $dims[1]],
            [$dims[2], $dims[1], $dims[0]],
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

        return $unique;
    }

    /**
     * Split a free-space region after placing an item.
     *
     * @param int $index Index of the space being consumed
     * @param float $iw Placed item width
     * @param float $il Placed item length
     * @param float $ih Placed item height
     */
    private function splitSpace(int $index, float $iw, float $il, float $ih): void
    {
        $space = $this->spaces[$index];
        unset($this->spaces[$index]);

        $sx = $space[0];
        $sy = $space[1];
        $sz = $space[2];
        $sw = $space[3];
        $sl = $space[4];
        $sh = $space[5];

        // Right slab: remaining width along x-axis
        $rw = $sw - $iw;
        if ($rw > self::EPSILON) {
            $this->spaces[] = [$sx + $iw, $sy, $sz, $rw, $sl, $sh];
        }

        // Front slab: remaining length along y-axis (only the width consumed by item)
        $fl = $sl - $il;
        if ($fl > self::EPSILON) {
            $this->spaces[] = [$sx, $sy + $il, $sz, $iw, $fl, $sh];
        }

        // Top slab: remaining height along z-axis (only the width+length consumed)
        $th = $sh - $ih;
        if ($th > self::EPSILON) {
            $this->spaces[] = [$sx, $sy, $sz + $ih, $iw, $il, $th];
        }

    }
}
