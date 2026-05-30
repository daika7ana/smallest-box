<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Packing;

/**
 * Maximal rectangles 3D bin packer.
 *
 * Similar to guillotine splits but tries all 6 axis orderings when splitting
 * free space, keeping the ordering that produces the best remaining cuboids.
 * This preserves corner regions that guillotine splits lose, resulting in
 * better packing efficiency for diverse item sets.
 *
 * @internal
 */
class MaxRectsPacker implements PackingStrategy
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
            $this->splitSpaceMaxRects($bestChoice, $bestRot[0], $bestRot[1], $bestRot[2]);
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
        // Wall contact
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

        // Tightness
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

        // Position
        $positionScore = -($space[0] + $space[1] + $space[2]);

        // Waste
        $waste = ($space[3] * $space[4] * $space[5]) - ($rot[0] * $rot[1] * $rot[2]);
        $wasteScore = -$waste;

        return $wallContact * 50.0 + $tightness * 30.0 + $positionScore * 0.1 + $wasteScore * 0.01;
    }

    /**
     * Split using maximal rectangles approach: try all 6 axis orderings
     * and pick the one that produces the best remaining spaces.
     */
    private function splitSpaceMaxRects(int $index, float $iw, float $il, float $ih): void
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

        // All 6 axis orderings for splitting
        $orderings = [
            [$iw, $il, $ih],  // X→Y→Z
            [$iw, $ih, $il],  // X→Z→Y
            [$il, $iw, $ih],  // Y→X→Z
            [$il, $ih, $iw],  // Y→Z→X
            [$ih, $iw, $il],  // Z→X→Y
            [$ih, $il, $iw],  // Z→Y→X
        ];

        $bestScore = -PHP_FLOAT_MAX;
        $bestSplits = [];

        foreach ($orderings as [$a, $b, $c]) {
            $splits = [];

            // First cut along X
            $rw = $sw - $a;
            if ($rw > self::EPSILON) {
                $splits[] = [$sx + $a, $sy, $sz, $rw, $sl, $sh];
            }

            // Second cut along Y (within consumed X)
            $fl = $sl - $b;
            if ($fl > self::EPSILON) {
                $splits[] = [$sx, $sy + $b, $sz, $a, $fl, $sh];
            }

            // Third cut along Z (within consumed X and Y)
            $th = $sh - $c;
            if ($th > self::EPSILON) {
                $splits[] = [$sx, $sy, $sz + $c, $a, $b, $th];
            }

            // Score this split set
            $score = $this->scoreSplit($splits);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSplits = $splits;
            }
        }

        // Insert the best splits
        foreach ($bestSplits as $s) {
            $this->insertSpace($s[0], $s[1], $s[2], $s[3], $s[4], $s[5]);
        }

        $this->pruneSubsumedSpaces();
        $this->mergeAdjacentSpaces();
    }

    /**
     * Score a set of cuboids produced by a split ordering.
     *
     * @param array<int, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}> $cuboids
     * @return float Higher is better
     */
    private function scoreSplit(array $cuboids): float
    {
        $score = 0.0;
        foreach ($cuboids as $c) {
            $vol = $c[3] * $c[4] * $c[5];
            $score += $vol * 0.01;
            $score += min($c[3], $c[4], $c[5]) * 10.0;
            if ($c[0] < self::EPSILON) {
                $score += 50.0;
            }
            if ($c[1] < self::EPSILON) {
                $score += 50.0;
            }
            if ($c[2] < self::EPSILON) {
                $score += 50.0;
            }
        }
        return $score;
    }

    /**
     * Insert a space in sorted order using binary search.
     * Spaces are sorted by z, then y, then x.
     */
    private function insertSpace(float $x, float $y, float $z, float $w, float $l, float $h): void
    {
        $space = [$x, $y, $z, $w, $l, $h];
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
     * Merge adjacent spaces that share a face and have matching cross-sections.
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
