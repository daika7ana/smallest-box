<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

use InvalidArgumentException;
use OutOfRangeException;
use RuntimeException;

/**
 * Finds the smallest box (W×L×H) that fits a set of rectangular items.
 *
 * Algorithm:
 * 1. Sort items by volume descending (largest-first heuristic).
 * 2. Generate candidate box dimensions from item dimension permutations.
 * 3. For each candidate, attempt greedy 3D placement with rotation.
 * 4. Return the smallest successful box.
 */
class SmallestBoxFinder
{
    private const EPSILON = 0.001;

    /** @var Item[] */
    private array $items = [];

    /**
     * Add an item to the internal collection.
     */
    public function add(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove an item from the internal collection by index.
     *
     * @throws OutOfRangeException if the index is out of bounds
     */
    public function remove(int $index): self
    {
        if (!isset($this->items[$index])) {
            throw new OutOfRangeException(sprintf(
                'Index %d is out of range (items count: %d).',
                $index,
                count($this->items),
            ));
        }

        array_splice($this->items, $index, 1);

        return $this;
    }

    /**
     * Get all items in the internal collection.
     *
     * @return Item[]
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    /**
     * Clear all items from the internal collection.
     */
    public function clear(): self
    {
        $this->items = [];

        return $this;
    }

    /**
     * Find the smallest box that fits the items.
     *
     * If $items is provided, uses those items.
     * If $items is null, uses the internal collection.
     *
     * @param Item[]|null $items
     * @return Box
     */
    public function find(?array $items = null): Box
    {
        $items = $items ?? $this->items;

        if (empty($items)) {
            throw new InvalidArgumentException('Items array must not be empty.');
        }

        // Sort by volume descending (work on a copy to avoid mutating the input)
        $sortedItems = $items;
        usort($sortedItems, function (Item $a, Item $b): int {
            return $b->volume() <=> $a->volume();
        });

        $totalVolume = 0.0;
        foreach ($sortedItems as $item) {
            $totalVolume += $item->volume();
        }

        $candidates = $this->generateCandidates($sortedItems, $totalVolume);

        // Sort candidates by volume ascending
        usort($candidates, function (array $a, array $b): int {
            return $a[3] <=> $b[3];
        });

        // Partition: try exact-volume candidates first
        $totalVolumeRounded = round($totalVolume, 4);
        $exactMatches = [];
        $otherCandidates = [];
        foreach ($candidates as $candidate) {
            if (abs($candidate[3] - $totalVolumeRounded) < self::EPSILON) {
                $exactMatches[] = $candidate;
            } else {
                $otherCandidates[] = $candidate;
            }
        }
        $candidates = array_merge($exactMatches, $otherCandidates);

        $bestBox = null;

        foreach ($candidates as $candidate) {
            if ($bestBox !== null && $candidate[3] >= $bestBox->volume()) {
                break;
            }

            if ($this->canPack($items, $candidate[0], $candidate[1], $candidate[2])) {
                $bestBox = new Box($candidate[0], $candidate[1], $candidate[2]);

                // Exact volume match is optimal
                if (abs($candidate[3] - $totalVolumeRounded) < self::EPSILON) {
                    break;
                }
            }
        }

        if ($bestBox === null) {
            throw new RuntimeException(sprintf(
                'Could not find a box that fits all %d items (total volume: %.4f).',
                count($sortedItems),
                $totalVolume,
            ));
        }

        return $bestBox;
    }

    /**
     * Generate candidate box dimensions from item dimension permutations.
     *
     * @param Item[] $items
     * @param float $totalVolume
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function generateCandidates(array $items, float $totalVolume): array
    {
        // Compute per-axis lower bounds for early pruning
        $minDims = [];
        foreach ($items as $item) {
            $itemMinDim = PHP_FLOAT_MAX;
            foreach ($item->rotations() as $rot) {
                $minDim = min($rot[0], $rot[1], $rot[2]);
                $itemMinDim = min($itemMinDim, $minDim);
            }
            $minDims[] = $itemMinDim;
        }
        rsort($minDims);

        // Collect all unique dimensions from all items
        $dims = [];
        foreach ($items as $item) {
            foreach ([$item->width(), $item->length(), $item->height()] as $d) {
                $dims[(string) round($d, 4)] = $d;
            }
        }
        $dims = array_values($dims);
        sort($dims);

        $candidates = [];
        $seen = [];

        // Strategy 1: single-item rotation fills the box exactly
        foreach ($items as $item) {
            foreach ($item->rotations() as $rot) {
                $this->addCandidate($candidates, $seen, $rot[0], $rot[1], $rot[2], $totalVolume, $minDims);
            }
        }

        // Strategy 2: all permutations of unique item dimensions
        $n = count($dims);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                for ($k = 0; $k < $n; $k++) {
                    $this->addCandidate($candidates, $seen, $dims[$i], $dims[$j], $dims[$k], $totalVolume, $minDims);
                }
            }
        }

        // Strategy 3: pairwise sums combined with single dimensions
        if ($n <= 8) {
            // Full pairwise combinations for small dimension sets
            $pairDims = $dims;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i; $j < $n; $j++) {
                    $pairDims[] = $dims[$i] + $dims[$j];
                }
            }
            $pairDims = array_values(array_unique(array_map(function (float $d): float {
                return round($d, 4);
            }, $pairDims)));
            sort($pairDims);

            $pn = count($pairDims);
            for ($i = 0; $i < $pn; $i++) {
                for ($j = 0; $j < $pn; $j++) {
                    for ($k = 0; $k < $pn; $k++) {
                        $this->addCandidate($candidates, $seen, $pairDims[$i], $pairDims[$j], $pairDims[$k], $totalVolume, $minDims);
                    }
                }
            }
        } else {
            // For larger dimension sets, only combine pair sums with single dimensions
            $pairDims = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i; $j < $n; $j++) {
                    $pairDims[] = round($dims[$i] + $dims[$j], 4);
                }
            }
            $pairDims = array_values(array_unique($pairDims));
            sort($pairDims);

            $pn = count($pairDims);
            // pair × pair × single (reduced from pn³)
            for ($i = 0; $i < $pn; $i++) {
                for ($j = 0; $j < $pn; $j++) {
                    for ($k = 0; $k < $n; $k++) {
                        $this->addCandidate($candidates, $seen, $pairDims[$i], $pairDims[$j], $dims[$k], $totalVolume, $minDims);
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, array{0: float, 1: float, 2: float, 3: float}> $candidates
     * @param array<string, bool> $seen
     * @param list<float> $minDims Item minimum dimensions sorted descending
     */
    private function addCandidate(array &$candidates, array &$seen, float $w, float $l, float $h, float $totalVolume, array $minDims): void
    {
        $vol = $w * $l * $h;
        if ($vol < $totalVolume - self::EPSILON) {
            return;
        }

        // Per-axis feasibility: the largest candidate dimension must be
        // at least as large as the largest item minimum dimension.
        $candidateDims = [$w, $l, $h];
        rsort($candidateDims);
        if ($candidateDims[0] < $minDims[0] - self::EPSILON) {
            return;
        }

        $sorted = [$w, $l, $h];
        sort($sorted);
        $key = round($sorted[0], 4) . 'x' . round($sorted[1], 4) . 'x' . round($sorted[2], 4);

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $candidates[] = [$sorted[0], $sorted[1], $sorted[2], $vol];
        }
    }

    /**
     * Attempt to greedily pack all items into a box of given dimensions.
     *
     * @param Item[] $items
     * @return bool
     */
    private function canPack(array $items, float $boxW, float $boxL, float $boxH): bool
    {
        // Quick feasibility check: verify each item fits in at least one rotation
        foreach ($items as $item) {
            $fits = false;
            foreach ($item->rotations() as $rot) {
                if ($rot[0] <= $boxW && $rot[1] <= $boxL && $rot[2] <= $boxH) {
                    $fits = true;
                    break;
                }
            }
            if (!$fits) {
                return false;
            }
        }

        $placement = new Placement($boxW, $boxL, $boxH);

        foreach ($items as $item) {
            if (!$placement->place($item->rotations())) {
                return false;
            }
        }

        return true;
    }


}
