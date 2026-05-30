<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Packing;

/**
 * Shared space management logic for bin-packing strategies.
 *
 * Provides common implementations for scoring placements, inserting spaces
 * in sorted order, pruning subsumed spaces, and merging adjacent spaces.
 *
 * Each using class MUST define a `private const EPSILON = 0.001;` constant
 * and a `$spaces` property with the same shape as documented below.
 *
 * @internal
 */
trait SpaceManagementTrait
{
    /** @var float Comparison tolerance for floating-point equality */
    private const EPSILON = 0.001;

    /**
     * Score a candidate (space, rotation) pair for best-fit placement.
     *
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $space
     * @param array{0: float, 1: float, 2: float} $rot
     * @return float Higher is better
     */
    private function placementScore(array $space, array $rot): float
    {
        // 1. Wall contact bonus: prefer spaces touching box walls
        $wallContact = 0.0;
        if ($space[0] < self::EPSILON) {
            $wallContact += 1.0;
        }
        if ($space[1] < self::EPSILON) {
            $wallContact += 1.0;
        }
        if ($space[2] < self::EPSILON) {
            $wallContact += 1.0;
        }

        // 2. Tightness: how many dimensions match exactly
        $tightness = 0.0;
        if (abs($space[3] - $rot[0]) < self::EPSILON) {
            $tightness += 1.0;
        }
        if (abs($space[4] - $rot[1]) < self::EPSILON) {
            $tightness += 1.0;
        }
        if (abs($space[5] - $rot[2]) < self::EPSILON) {
            $tightness += 1.0;
        }

        // 3. Position score: prefer origin
        $positionScore = -($space[0] + $space[1] + $space[2]);

        // 4. Waste score: prefer smaller leftover volume
        $waste = ($space[3] * $space[4] * $space[5]) - ($rot[0] * $rot[1] * $rot[2]);
        $wasteScore = -$waste;

        return $wallContact * 50.0
             + $tightness * 30.0
             + $positionScore * 0.1
             + $wasteScore * 0.01;
    }

    /**
     * Insert a space in sorted order using binary search.
     * Spaces are sorted by z, then y, then x.
     *
     * @param float $x X origin of the space
     * @param float $y Y origin of the space
     * @param float $z Z origin of the space
     * @param float $w Width (x-axis extent)
     * @param float $l Length (y-axis extent)
     * @param float $h Height (z-axis extent)
     */
    private function insertSpace(float $x, float $y, float $z, float $w, float $l, float $h): void
    {
        $space = [$x, $y, $z, $w, $l, $h];

        // Binary search for insertion point
        $low = 0;
        $high = count($this->spaces);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            $s = $this->spaces[$mid];
            if ($z < $s[2] || ($z === $s[2] && ($y < $s[1] || ($y === $s[1] && $x < $s[0])))) {
                $high = $mid;
            } else {
                $low = $mid + 1;
            }
        }
        array_splice($this->spaces, $low, 0, [$space]);
    }

    /**
     * Remove spaces that are fully contained within another space.
     */
    private function pruneSubsumedSpaces(): void
    {
        $count = count($this->spaces);
        if ($count < 2) {
            return;
        }

        $subsumed = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($subsumed[$i])) {
                continue;
            }
            $a = $this->spaces[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($subsumed[$j])) {
                    continue;
                }
                $b = $this->spaces[$j];

                // Check if $a contains $b
                if ($a[0] <= $b[0] && $a[1] <= $b[1] && $a[2] <= $b[2]
                    && $a[0] + $a[3] >= $b[0] + $b[3]
                    && $a[1] + $a[4] >= $b[1] + $b[4]
                    && $a[2] + $a[5] >= $b[2] + $b[5]
                ) {
                    $subsumed[$j] = true;
                } elseif ($b[0] <= $a[0] && $b[1] <= $a[1] && $b[2] <= $a[2]
                    && $b[0] + $b[3] >= $a[0] + $a[3]
                    && $b[1] + $b[4] >= $a[1] + $a[4]
                    && $b[2] + $b[5] >= $a[2] + $a[5]
                ) {
                    $subsumed[$i] = true;
                    break;
                }
            }
        }

        if (!empty($subsumed)) {
            $keep = [];
            for ($i = 0; $i < $count; $i++) {
                if (!isset($subsumed[$i])) {
                    $keep[] = $this->spaces[$i];
                }
            }
            $this->spaces = $keep;
        }
    }

    /**
     * Merge adjacent spaces that share a face and have matching cross-sections.
     *
     * Single-pass O(n²) approach: for each space, try to merge with all
     * subsequent spaces along X, Y, or Z axes.
     */
    private function mergeAdjacentSpaces(): void
    {
        $count = count($this->spaces);
        if ($count < 2) {
            return;
        }

        $used = [];
        $merged = [];

        for ($i = 0; $i < $count; $i++) {
            if (isset($used[$i])) {
                continue;
            }
            $current = $this->spaces[$i];

            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($used[$j])) {
                    continue;
                }
                $other = $this->spaces[$j];

                // Merge along X axis: same y, z, l, h and adjacent in x
                if (abs($current[1] - $other[1]) < self::EPSILON
                    && abs($current[2] - $other[2]) < self::EPSILON
                    && abs($current[4] - $other[4]) < self::EPSILON
                    && abs($current[5] - $other[5]) < self::EPSILON
                    && abs(($current[0] + $current[3]) - $other[0]) < self::EPSILON
                ) {
                    $current = [$current[0], $current[1], $current[2], $current[3] + $other[3], $current[4], $current[5]];
                    $used[$j] = true;
                    continue;
                }

                // Merge along Y axis: same x, z, w, h and adjacent in y
                if (abs($current[0] - $other[0]) < self::EPSILON
                    && abs($current[2] - $other[2]) < self::EPSILON
                    && abs($current[3] - $other[3]) < self::EPSILON
                    && abs($current[5] - $other[5]) < self::EPSILON
                    && abs(($current[1] + $current[4]) - $other[1]) < self::EPSILON
                ) {
                    $current = [$current[0], $current[1], $current[2], $current[3], $current[4] + $other[4], $current[5]];
                    $used[$j] = true;
                    continue;
                }

                // Merge along Z axis: same x, y, w, l and adjacent in z
                if (abs($current[0] - $other[0]) < self::EPSILON
                    && abs($current[1] - $other[1]) < self::EPSILON
                    && abs($current[3] - $other[3]) < self::EPSILON
                    && abs($current[4] - $other[4]) < self::EPSILON
                    && abs(($current[2] + $current[5]) - $other[2]) < self::EPSILON
                ) {
                    $current = [$current[0], $current[1], $current[2], $current[3], $current[4], $current[5] + $other[5]];
                    $used[$j] = true;
                    continue;
                }
            }

            $merged[] = $current;
        }

        $this->spaces = $merged;
    }
}
