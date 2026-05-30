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
    use SpaceManagementTrait;

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
        array_splice($this->spaces, $index, 1);

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
}
