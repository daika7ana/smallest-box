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
            return ($a[0] * $a[1] * $a[2]) <=> ($b[0] * $b[1] * $b[2]);
        });

        $bestBox = null;

        foreach ($candidates as $candidate) {
            if ($bestBox !== null && ($candidate[0] * $candidate[1] * $candidate[2]) >= $bestBox->volume()) {
                break;
            }

            if ($this->canPack($items, $candidate[0], $candidate[1], $candidate[2])) {
                $bestBox = new Box($candidate[0], $candidate[1], $candidate[2]);
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
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    private function generateCandidates(array $items, float $totalVolume): array
    {
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
                $this->addCandidate($candidates, $seen, $rot[0], $rot[1], $rot[2], $totalVolume);
            }
        }

        // Strategy 2: all permutations of unique item dimensions
        $n = count($dims);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                for ($k = 0; $k < $n; $k++) {
                    $this->addCandidate($candidates, $seen, $dims[$i], $dims[$j], $dims[$k], $totalVolume);
                }
            }
        }

        // Strategy 3: pairwise sums along each axis (items placed side by side).
        // This strategy is O(m³) where m is the number of unique pair dimensions,
        // which can grow quickly. Cap it to n ≤ 8 unique dimensions to keep
        // the combinatorial explosion manageable while still covering common cases.
        if ($n <= 8) {
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
                        $this->addCandidate($candidates, $seen, $pairDims[$i], $pairDims[$j], $pairDims[$k], $totalVolume);
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, array{0: float, 1: float, 2: float}> $candidates
     * @param array<string, bool> $seen
     */
    private function addCandidate(array &$candidates, array &$seen, float $w, float $l, float $h, float $totalVolume): void
    {
        if ($w * $l * $h < $totalVolume - self::EPSILON) {
            return;
        }

        $sorted = [$w, $l, $h];
        sort($sorted);
        $key = round($sorted[0], 4) . 'x' . round($sorted[1], 4) . 'x' . round($sorted[2], 4);

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $candidates[] = $sorted;
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
        $placement = new Placement($boxW, $boxL, $boxH);

        foreach ($items as $item) {
            if (!$placement->place([$item->width(), $item->length(), $item->height()])) {
                return false;
            }
        }

        return true;
    }


}
