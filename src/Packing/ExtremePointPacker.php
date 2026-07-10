<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Packing;

/**
 * Extreme point (corner placement) 3D bin packer.
 *
 * Maintains a list of extreme points — corner coordinates where items
 * can be placed. For each item, evaluates all points and rotations,
 * selecting the placement that scores best (preferring deep-bottom-left
 * and tight fits). After placement, generates new extreme points from
 * the placed item's faces and removes invalid/duplicate points.
 *
 * @internal
 */
class ExtremePointPacker implements PackingStrategy
{
    /** @var array<int, array{0: float, 1: float, 2: float}> */
    private array $points;

    /** @var array<int, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}> */
    private array $placed;

    private float $boxWidth;
    private float $boxLength;
    private float $boxHeight;

    private const EPSILON = 0.001;

    public function __construct(float $boxWidth, float $boxLength, float $boxHeight)
    {
        $this->points = [[0.0, 0.0, 0.0]];
        $this->placed = [];
        $this->boxWidth = $boxWidth;
        $this->boxLength = $boxLength;
        $this->boxHeight = $boxHeight;
    }

    /**
     * Attempt to place an item using the extreme points strategy.
     *
     * Evaluates all extreme points and rotations, picks the best scoring
     * placement (lower z, then y, then x; wall contact; tight fit).
     *
     * @param array<int, array{0: float, 1: float, 2: float}> $rotations
     * @return bool true if placed successfully
     */
    public function place(array $rotations): bool
    {
        $bestScore = -PHP_FLOAT_MAX;
        $bestPointIndex = null;
        $bestRot = null;

        foreach ($this->points as $pi => $point) {
            [$px, $py, $pz] = $point;

            foreach ($rotations as $rot) {
                [$rw, $rl, $rh] = $rot;

                if (!$this->canPlaceAt($px, $py, $pz, $rw, $rl, $rh)) {
                    continue;
                }

                // Score this placement
                $score = $this->placementScore($px, $py, $pz, $rw, $rl, $rh);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPointIndex = $pi;
                    $bestRot = $rot;
                } elseif ($score === $bestScore && $bestPointIndex !== null) {
                    // Tiebreaker: prefer point with lower z, then y, then x
                    $bestPoint = $this->points[$bestPointIndex];
                    if ($pz < $bestPoint[2]
                        || ($pz === $bestPoint[2] && $py < $bestPoint[1])
                        || ($pz === $bestPoint[2] && $py === $bestPoint[1] && $px < $bestPoint[0])
                    ) {
                        $bestScore = $score;
                        $bestPointIndex = $pi;
                        $bestRot = $rot;
                    }
                }
            }
        }

        if ($bestPointIndex !== null && $bestRot !== null) {
            $px = $this->points[$bestPointIndex][0];
            $py = $this->points[$bestPointIndex][1];
            $pz = $this->points[$bestPointIndex][2];
            $rw = $bestRot[0];
            $rl = $bestRot[1];
            $rh = $bestRot[2];

            // Place the item
            $this->placed[] = [$px, $py, $pz, $rw, $rl, $rh];

            // Prune existing extreme points that now fall inside the newly
            // placed item. Uses the same half-open interval "inside" test
            // used for candidate validation. This must happen before new
            // candidates are generated so dead points do not incorrectly
            // suppress valid new points during deduplication.
            $this->points = array_values(array_filter(
                $this->points,
                fn(array $p) => !(
                    $p[0] >= $px - self::EPSILON && $p[0] < $px + $rw - self::EPSILON
                    && $p[1] >= $py - self::EPSILON && $p[1] < $py + $rl - self::EPSILON
                    && $p[2] >= $pz - self::EPSILON && $p[2] < $pz + $rh - self::EPSILON
                ),
            ));

            // Generate and incrementally validate 3 new extreme points
            // from the placed item's faces (right, front, top)
            $candidates = [
                [$px + $rw, $py, $pz],   // right face
                [$px, $py + $rl, $pz],   // front face
                [$px, $py, $pz + $rh],   // top face
            ];

            foreach ($candidates as $point) {
                [$x, $y, $z] = $point;

                // Box bounds check (must leave room for a tiny item)
                if ($x >= $this->boxWidth - self::EPSILON
                    || $y >= $this->boxLength - self::EPSILON
                    || $z >= $this->boxHeight - self::EPSILON
                ) {
                    continue;
                }

                // Check if inside any placed item (half-open interval
                // [ix, ix+iw) × [iy, iy+il) × [iz, iz+ih))
                $inside = false;
                foreach ($this->placed as $placedItem) {
                    [$ix, $iy, $iz, $iw, $il, $ih] = $placedItem;
                    if ($x >= $ix - self::EPSILON && $x < $ix + $iw - self::EPSILON
                        && $y >= $iy - self::EPSILON && $y < $iy + $il - self::EPSILON
                        && $z >= $iz - self::EPSILON && $z < $iz + $ih - self::EPSILON
                    ) {
                        $inside = true;
                        break;
                    }
                }
                if ($inside) {
                    continue;
                }

                // Deduplicate against existing points
                $duplicate = false;
                foreach ($this->points as $existing) {
                    if (abs($x - $existing[0]) < self::EPSILON
                        && abs($y - $existing[1]) < self::EPSILON
                        && abs($z - $existing[2]) < self::EPSILON
                    ) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate) {
                    continue;
                }

                $this->addPoint($x, $y, $z);
            }

            return true;
        }

        return false;
    }

    /**
     * Quick early-exit check: box bounds first, then overlap with placed items.
     */
    private function canPlaceAt(float $x, float $y, float $z, float $w, float $l, float $h): bool
    {
        // Box bounds check
        if ($x + $w > $this->boxWidth + self::EPSILON
            || $y + $l > $this->boxLength + self::EPSILON
            || $z + $h > $this->boxHeight + self::EPSILON
        ) {
            return false;
        }

        // Overlap check
        foreach ($this->placed as $p) {
            if ($x < $p[0] + $p[3] - self::EPSILON
                && $x + $w > $p[0] + self::EPSILON
                && $y < $p[1] + $p[4] - self::EPSILON
                && $y + $l > $p[1] + self::EPSILON
                && $z < $p[2] + $p[5] - self::EPSILON
                && $z + $h > $p[2] + self::EPSILON
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Score a candidate placement.
     *
     * Rewards wall contact, contact with placed items (tight fits),
     * deep-bottom-left positioning (lower z, then y, then x),
     * and tight fits against box walls.
     *
     * @return float Higher is better
     */
    private function placementScore(float $x, float $y, float $z, float $w, float $l, float $h): float
    {
        // 1. Wall contact bonus: prefer corners touching box walls
        $wallContact = 0.0;
        if ($x < self::EPSILON) {
            $wallContact += 1.0;
        }
        if (abs($x + $w - $this->boxWidth) < self::EPSILON) {
            $wallContact += 1.0;
        }
        if ($y < self::EPSILON) {
            $wallContact += 1.0;
        }
        if (abs($y + $l - $this->boxLength) < self::EPSILON) {
            $wallContact += 1.0;
        }
        if ($z < self::EPSILON) {
            $wallContact += 1.0;
        }
        if (abs($z + $h - $this->boxHeight) < self::EPSILON) {
            $wallContact += 1.0;
        }

        // 2. Contact with placed items (tight fit — shared faces)
        $contact = 0.0;
        foreach ($this->placed as $placedItem) {
            [$ix, $iy, $iz, $iw, $il, $ih] = $placedItem;

            // Right face of placed touches left face of candidate
            if (abs($x - ($ix + $iw)) < self::EPSILON
                && $y < $iy + $il && $y + $l > $iy
                && $z < $iz + $ih && $z + $h > $iz
            ) {
                $contact += 1.0;
            }
            // Left face of placed touches right face of candidate
            if (abs(($x + $w) - $ix) < self::EPSILON
                && $y < $iy + $il && $y + $l > $iy
                && $z < $iz + $ih && $z + $h > $iz
            ) {
                $contact += 1.0;
            }
            // Front face of placed touches back face of candidate
            if (abs($y - ($iy + $il)) < self::EPSILON
                && $x < $ix + $iw && $x + $w > $ix
                && $z < $iz + $ih && $z + $h > $iz
            ) {
                $contact += 1.0;
            }
            // Back face of placed touches front face of candidate
            if (abs(($y + $l) - $iy) < self::EPSILON
                && $x < $ix + $iw && $x + $w > $ix
                && $z < $iz + $ih && $z + $h > $iz
            ) {
                $contact += 1.0;
            }
            // Top face of placed touches bottom face of candidate
            if (abs($z - ($iz + $ih)) < self::EPSILON
                && $x < $ix + $iw && $x + $w > $ix
                && $y < $iy + $il && $y + $l > $iy
            ) {
                $contact += 1.0;
            }
            // Bottom face of placed touches top face of candidate
            if (abs(($z + $h) - $iz) < self::EPSILON
                && $x < $ix + $iw && $x + $w > $ix
                && $y < $iy + $il && $y + $l > $iy
            ) {
                $contact += 1.0;
            }
        }

        // 3. Deep-bottom-left position score: prefer lower z, then y, then x
        $positionScore = -($z * 100.0 + $y * 10.0 + $x);

        // 4. Tightness: how many dimensions exactly match the box
        $tightness = 0.0;
        if (abs($w - $this->boxWidth) < self::EPSILON) {
            $tightness += 1.0;
        }
        if (abs($l - $this->boxLength) < self::EPSILON) {
            $tightness += 1.0;
        }
        if (abs($h - $this->boxHeight) < self::EPSILON) {
            $tightness += 1.0;
        }

        return $wallContact * 50.0
             + $contact * 40.0
             + $positionScore * 0.1
             + $tightness * 30.0;
    }

    /**
     * Add a point, maintaining sorted order (z ascending, then y, then x).
     */
    private function addPoint(float $x, float $y, float $z): void
    {
        // Binary search for insertion point
        $low = 0;
        $high = count($this->points);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            $p = $this->points[$mid];
            if ($z < $p[2] || ($z === $p[2] && ($y < $p[1] || ($y === $p[1] && $x < $p[0])))) {
                $high = $mid;
            } else {
                $low = $mid + 1;
            }
        }
        array_splice($this->points, $low, 0, [[$x, $y, $z]]);
    }

}
