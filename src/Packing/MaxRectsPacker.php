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
    use SpaceManagementTrait;

    /** @var array<int, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}> */
    private array $spaces;

    private const EPSILON = 0.001;
    private const AXIS_PERMS = [[0,1,2],[0,2,1],[1,0,2],[1,2,0],[2,0,1],[2,1,0]];

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
     * Split using maximal rectangles approach: try all 6 axis orderings
     * and pick the one that produces the best remaining spaces.
     */
    private function splitSpaceMaxRects(int $index, float $iw, float $il, float $ih): void
    {
        $space = $this->spaces[$index];
        array_splice($this->spaces, $index, 1);

        $sx = $space[0];
        $sy = $space[1];
        $sz = $space[2];
        $sw = $space[3];
        $sl = $space[4];
        $sh = $space[5];

        $dims = [$iw, $il, $ih];

        $bestScore = -PHP_FLOAT_MAX;
        $bestSplits = [];

        foreach (self::AXIS_PERMS as [$ai, $bi, $ci]) {
            $a = $dims[$ai];
            $b = $dims[$bi];
            $c = $dims[$ci];
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
}
