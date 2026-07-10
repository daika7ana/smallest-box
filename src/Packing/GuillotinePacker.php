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
class GuillotinePacker extends AbstractPacker
{
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

        if ($bestChoice !== null && $bestRot !== null) {
            $this->splitSpace($bestChoice, $bestRot[0], $bestRot[1], $bestRot[2]);
            return true;
        }

        return false;
    }

    /**
     * Split a free-space region after placing an item.
     *
     * Creates three guillotine slabs (right, front, top) relative to the
     * placed item's position and dimensions.  Incrementally prunes new
     * slabs against existing spaces instead of running full O(n²)
     * subsumption over all spaces.
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

        // Build the up-to-3 new guillotine slabs
        $newSlabs = [];

        // Right slab: remaining width along x-axis
        $rw = $sw - $iw;
        if ($rw > self::EPSILON) {
            $newSlabs[] = [$sx + $iw, $sy, $sz, $rw, $sl, $sh];
        }

        // Front slab: remaining length along y-axis (only the width consumed by item)
        $fl = $sl - $il;
        if ($fl > self::EPSILON) {
            $newSlabs[] = [$sx, $sy + $il, $sz, $iw, $fl, $sh];
        }

        // Top slab: remaining height along z-axis (only the width+length consumed)
        $th = $sh - $ih;
        if ($th > self::EPSILON) {
            $newSlabs[] = [$sx, $sy, $sz + $ih, $iw, $il, $th];
        }

        // Incremental pruning: compare new slabs only against existing spaces.
        // Use direct array indexing and volume pre-filter for speed.
        $removeIndices = [];
        $survivors = [];

        foreach ($newSlabs as $newSlab) {
            $nx  = $newSlab[0];
            $ny  = $newSlab[1];
            $nz  = $newSlab[2];
            $nw  = $newSlab[3];
            $nl  = $newSlab[4];
            $nh  = $newSlab[5];
            $nMaxX = $nx + $nw;
            $nMaxY = $ny + $nl;
            $nMaxZ = $nz + $nh;
            $nVol = $nw * $nl * $nh;

            $subsumed = false;
            foreach ($this->spaces as $ei => $e) {
                $eVol = $e[3] * $e[4] * $e[5];

                if ($eVol >= $nVol
                    && $e[0] <= $nx && $e[1] <= $ny && $e[2] <= $nz
                    && $e[0] + $e[3] >= $nMaxX
                    && $e[1] + $e[4] >= $nMaxY
                    && $e[2] + $e[5] >= $nMaxZ
                ) {
                    $subsumed = true;
                    break;
                }

                if ($nVol >= $eVol
                    && $nMaxX >= $e[0] + $e[3]
                    && $nMaxY >= $e[1] + $e[4]
                    && $nMaxZ >= $e[2] + $e[5]
                    && $nx <= $e[0] && $ny <= $e[1] && $nz <= $e[2]
                ) {
                    $removeIndices[$ei] = true;
                }
            }

            if (!$subsumed) {
                $survivors[] = $newSlab;
            }
        }

        // Remove existing spaces that were subsumed by a surviving new slab
        if (!empty($removeIndices)) {
            $keep = [];
            foreach ($this->spaces as $ei => $existing) {
                if (!isset($removeIndices[$ei])) {
                    $keep[] = $existing;
                }
            }
            $this->spaces = $keep;
        }

        // Insert surviving new slabs in sort order
        foreach ($survivors as $slab) {
            $this->insertSpace($slab[0], $slab[1], $slab[2], $slab[3], $slab[4], $slab[5]);
        }

        $this->mergeAdjacentSpaces();
    }
}
