<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Packing;

/**
 * Guillotine 3D bin packer.
 *
 * Splits free space into three orthogonal slabs (right, front, top) after
 * each placement — a classic guillotine cutting strategy.  Adjacent spaces
 * sharing a face are merged to recover larger cuboids over time.
 *
 * @internal
 */
class GuillotinePacker implements PackingStrategy
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
     * Attempt to place an item using pre-computed unique rotations inside the box.
     * Uses best-fit scoring to pick the optimal (space, rotation) pair.
     *
     * @param array<int, array{0: float, 1: float, 2: float}> $rotations Unique rotations to try
     * @return bool true if placed successfully
     */
    public function place(array $rotations): bool
    {
        $bestScore = -PHP_FLOAT_MAX;
        $bestChoice = null;
        $bestRot = null;

        foreach ($this->spaces as $si => $space) {
            foreach ($rotations as $rot) {
                if ($rot[0] <= $space[3] && $rot[1] <= $space[4] && $rot[2] <= $space[5]) {
                    $score = $this->placementScore($space, $rot);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestChoice = $si;
                        $bestRot = $rot;
                    } elseif ($score === $bestScore && $bestChoice !== null) {
                        // Tiebreaker: prefer lower z, then y, then x (deep-bottom-left)
                        $bestSpace = $this->spaces[$bestChoice];
                        if ($space[2] < $bestSpace[2]
                            || ($space[2] === $bestSpace[2] && $space[1] < $bestSpace[1])
                            || ($space[2] === $bestSpace[2] && $space[1] === $bestSpace[1] && $space[0] < $bestSpace[0])
                        ) {
                            $bestChoice = $si;
                            $bestRot = $rot;
                        }
                    }
                }
            }
        }

        if ($bestChoice !== null) {
            $this->splitSpace($bestChoice, $bestRot[0], $bestRot[1], $bestRot[2]);
            return true;
        }

        return false;
    }

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
        $keep = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $this->spaces[$i];
            $subsumed = false;

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }
                $b = $this->spaces[$j];

                // Check if $a is fully contained within $b
                if ($a[0] >= $b[0] && $a[1] >= $b[1] && $a[2] >= $b[2]
                    && $a[0] + $a[3] <= $b[0] + $b[3]
                    && $a[1] + $a[4] <= $b[1] + $b[4]
                    && $a[2] + $a[5] <= $b[2] + $b[5]
                ) {
                    $subsumed = true;
                    break;
                }
            }

            if (!$subsumed) {
                $keep[] = $a;
            }
        }

        $this->spaces = $keep;
    }

    /**
     * Split a free-space region after placing an item.
     *
     * Creates three guillotine slabs (right, front, top) relative to the
     * placed item's position and dimensions.
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
        $this->spaces = array_values($this->spaces);

        $sx = $space[0];
        $sy = $space[1];
        $sz = $space[2];
        $sw = $space[3];
        $sl = $space[4];
        $sh = $space[5];

        // Right slab: remaining width along x-axis
        $rw = $sw - $iw;
        if ($rw > self::EPSILON) {
            $this->insertSpace($sx + $iw, $sy, $sz, $rw, $sl, $sh);
        }

        // Front slab: remaining length along y-axis (only the width consumed by item)
        $fl = $sl - $il;
        if ($fl > self::EPSILON) {
            $this->insertSpace($sx, $sy + $il, $sz, $iw, $fl, $sh);
        }

        // Top slab: remaining height along z-axis (only the width+length consumed)
        $th = $sh - $ih;
        if ($th > self::EPSILON) {
            $this->insertSpace($sx, $sy, $sz + $ih, $iw, $il, $th);
        }

        $this->pruneSubsumedSpaces();
        $this->mergeAdjacentSpaces();
    }

    /**
     * Merge adjacent spaces that share a face and have matching cross-sections.
     *
     * Guillotine splitting fragments free space over time.  This pass recovers
     * larger cuboids by combining neighbours along each axis, which directly
     * improves the quality of future placement decisions.
     */
    private function mergeAdjacentSpaces(): void
    {
        $merged = true;
        while ($merged) {
            $merged = false;
            $count = count($this->spaces);

            for ($i = 0; $i < $count && !$merged; $i++) {
                for ($j = $i + 1; $j < $count && !$merged; $j++) {
                    $a = $this->spaces[$i];
                    $b = $this->spaces[$j];

                    // Merge along X axis: same y, z, l, h and adjacent in x
                    if (abs($a[1] - $b[1]) < self::EPSILON
                        && abs($a[2] - $b[2]) < self::EPSILON
                        && abs($a[4] - $b[4]) < self::EPSILON
                        && abs($a[5] - $b[5]) < self::EPSILON
                        && abs(($a[0] + $a[3]) - $b[0]) < self::EPSILON
                    ) {
                        $this->spaces[$i] = [$a[0], $a[1], $a[2], $a[3] + $b[3], $a[4], $a[5]];
                        unset($this->spaces[$j]);
                        $this->spaces = array_values($this->spaces);
                        $merged = true;
                        continue;
                    }

                    // Merge along Y axis: same x, z, w, h and adjacent in y
                    if (abs($a[0] - $b[0]) < self::EPSILON
                        && abs($a[2] - $b[2]) < self::EPSILON
                        && abs($a[3] - $b[3]) < self::EPSILON
                        && abs($a[5] - $b[5]) < self::EPSILON
                        && abs(($a[1] + $a[4]) - $b[1]) < self::EPSILON
                    ) {
                        $this->spaces[$i] = [$a[0], $a[1], $a[2], $a[3], $a[4] + $b[4], $a[5]];
                        unset($this->spaces[$j]);
                        $this->spaces = array_values($this->spaces);
                        $merged = true;
                        continue;
                    }

                    // Merge along Z axis: same x, y, w, l and adjacent in z
                    if (abs($a[0] - $b[0]) < self::EPSILON
                        && abs($a[1] - $b[1]) < self::EPSILON
                        && abs($a[3] - $b[3]) < self::EPSILON
                        && abs($a[4] - $b[4]) < self::EPSILON
                        && abs(($a[2] + $a[5]) - $b[2]) < self::EPSILON
                    ) {
                        $this->spaces[$i] = [$a[0], $a[1], $a[2], $a[3], $a[4], $a[5] + $b[5]];
                        unset($this->spaces[$j]);
                        $this->spaces = array_values($this->spaces);
                        $merged = true;
                        continue;
                    }
                }
            }
        }
    }
}
