<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

use Daika7ana\SmallestBox\Packing\ExtremePointPacker;
use Daika7ana\SmallestBox\Packing\GuillotinePacker;
use Daika7ana\SmallestBox\Packing\MaxRectsPacker;
use Daika7ana\SmallestBox\Packing\PackingStrategy;
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
    public const ALGO_GUILLOTINE = 'guillotine';
    public const ALGO_MAXRECTS = 'maxrects';
    public const ALGO_EXTREMEPOINT = 'extremepoint';

    private const EPSILON = 0.001;

    /** @var Item[] */
    private array $items = [];

    private string $algorithm;

    /** @var array<int, callable(Item, Item): int> */
    private array $customSortOrders = [];

    /** @var array<int, callable(Item, Item): int> */
    private array $customPackOrders = [];

    /** @var array<int, callable(Item, Item): int>|null */
    private static ?array $baseSortOrders = null;

    /** @var array<int, (callable(Item, Item): int)|null>|null */
    private static ?array $basePackOrders = null;

    /**
     * @return array<int, callable(Item, Item): int>
     */
    private static function getBaseSortOrders(): array
    {
        return self::$baseSortOrders ??= [
            fn(Item $a, Item $b): int => $b->volume() <=> $a->volume(),
            fn(Item $a, Item $b): int => $a->height() <=> $b->height(),
            fn(Item $a, Item $b): int => $a->volume() <=> $b->volume(),
            fn(Item $a, Item $b): int => $b->width() <=> $a->width(),
        ];
    }

    /**
     * @return array<int, (callable(Item, Item): int)|null>
     */
    private static function getBasePackOrders(): array
    {
        return self::$basePackOrders ??= [
            null,
            fn(Item $a, Item $b): int => $a->volume() <=> $b->volume(),
        ];
    }

    /**
     * @param string $algorithm Packing algorithm to use (ALGO_GUILLOTINE, ALGO_MAXRECTS, or ALGO_EXTREMEPOINT)
     */
    public function __construct(string $algorithm = self::ALGO_GUILLOTINE)
    {
        $this->algorithm = $algorithm;
    }

    /**
     * Set the packing algorithm.
     *
     * @param string $algorithm Packing algorithm to use (ALGO_GUILLOTINE, ALGO_MAXRECTS, or ALGO_EXTREMEPOINT)
     */
    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }

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
     * Add a custom sort order to try when packing items.
     * The callable receives two Items and returns an int comparison result.
     *
     * @param callable(Item, Item): int $comparator
     * @return self
     */
    public function addSortOrder(callable $comparator): self
    {
        $this->customSortOrders[] = $comparator;
        return $this;
    }

    /**
     * Add a custom pack order to try when packing items into a candidate box.
     * The callable receives two Items and returns an int comparison result.
     *
     * @param callable(Item, Item): int $comparator
     * @return self
     */
    public function addPackOrder(callable $comparator): self
    {
        $this->customPackOrders[] = $comparator;
        return $this;
    }

    /** @var array<string, array<int, array{0: float, 1: float, 2: float, 3: float}>>|null */
    private static ?array $candidateCache = null;

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

        $totalVolume = 0.0;
        foreach ($items as $item) {
            $totalVolume += $item->volume();
        }

        $candidates = $this->generateCandidates($items, $totalVolume);

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

        // Define sort orders to try for packing
        // Keep a focused set: the most effective orderings cover the vast majority of cases
        $sortOrders = self::getBaseSortOrders();

        // Append user-defined sort orders
        foreach ($this->customSortOrders as $custom) {
            $sortOrders[] = $custom;
        }

        // Pre-sort items for each sort order once rather than re-sorting for every candidate,
        // and also apply each pack order to avoid re-sorting inside canPack.
        $packOrders = self::getBasePackOrders();
        foreach ($this->customPackOrders as $custom) {
            $packOrders[] = $custom;
        }

        $sortedItemsByOrder = [];
        foreach ($sortOrders as $i => $sortFn) {
            $sortedItems = $items;
            usort($sortedItems, $sortFn);

            $sortedItemsByOrder[$i] = [];
            foreach ($packOrders as $j => $packFn) {
                $packItems = $sortedItems;
                if ($packFn !== null) {
                    usort($packItems, $packFn);
                }
                $sortedItemsByOrder[$i][$j] = $packItems;
            }
        }

        $bestBox = null;

        foreach ($candidates as $candidate) {
            // Try each sort order
            foreach ($sortOrders as $i => $sortFn) {
                if ($this->canPack($sortedItemsByOrder[$i], $candidate[0], $candidate[1], $candidate[2])) {
                    $bestBox = new Box($candidate[0], $candidate[1], $candidate[2]);
                    break 2; // Found a match, break both loops
                }
            }
        }

        if ($bestBox === null) {
            throw new RuntimeException(sprintf(
                'Could not find a box that fits all %d items (total volume: %.4f).',
                count($items),
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
        $cacheKey = $this->candidateCacheKey($items);
        if (isset(self::$candidateCache[$cacheKey])) {
            return self::$candidateCache[$cacheKey];
        }

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

        // Strategy 4: triple sums (items placed end-to-end along one axis)
        if ($n <= 6) {
            $tripleDims = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i; $j < $n; $j++) {
                    for ($k = $j; $k < $n; $k++) {
                        $tripleDims[] = round($dims[$i] + $dims[$j] + $dims[$k], 4);
                    }
                }
            }
            $tripleDims = array_values(array_unique($tripleDims));
            sort($tripleDims);

            // Combine triple sums with single and pair dims
            $allDims = array_unique(array_merge($dims, $pairDims, $tripleDims));
            $allDims = array_values($allDims);
            sort($allDims);
            $an = count($allDims);
            for ($i = 0; $i < $an; $i++) {
                for ($j = 0; $j < $an; $j++) {
                    for ($k = 0; $k < $an; $k++) {
                        $this->addCandidate($candidates, $seen, $allDims[$i], $allDims[$j], $allDims[$k], $totalVolume, $minDims);
                    }
                }
            }
        }

        self::$candidateCache[$cacheKey] = $candidates;

        return $candidates;
    }

    /**
     * Build a cache key from the multiset of item dimensions.
     *
     * @param Item[] $items
     */
    private function candidateCacheKey(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            $parts[] = round($item->width(), 4) . 'x' . round($item->length(), 4) . 'x' . round($item->height(), 4);
        }
        sort($parts);

        return md5(implode('|', $parts));
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
     * Create a packer instance based on the selected algorithm.
     */
    private function createPacker(float $w, float $l, float $h): PackingStrategy
    {
        if ($this->algorithm === self::ALGO_MAXRECTS) {
            return new MaxRectsPacker($w, $l, $h);
        }
        if ($this->algorithm === self::ALGO_EXTREMEPOINT) {
            return new ExtremePointPacker($w, $l, $h);
        }
        return new GuillotinePacker($w, $l, $h);
    }

    /**
     * @param Item[][] $itemSets Pre-sorted item sequences for each pack order
     */
    private function canPack(array $itemSets, float $boxW, float $boxL, float $boxH): bool
    {
        // Quick feasibility check using sorted dimensions: an item fits in a box
        // iff each sorted item dimension is <= the corresponding sorted box dimension.
        $boxDims = [$boxW, $boxL, $boxH];
        sort($boxDims);

        foreach ($itemSets[0] as $item) {
            $dims = $item->sortedDimensions();
            if ($dims[0] > $boxDims[0] || $dims[1] > $boxDims[1] || $dims[2] > $boxDims[2]) {
                return false;
            }
        }

        // Try each pre-sorted item sequence inside the packer
        foreach ($itemSets as $attemptItems) {
            $placement = $this->createPacker($boxW, $boxL, $boxH);
            $placed = true;

            foreach ($attemptItems as $item) {
                if (!$placement->place($item->rotations())) {
                    $placed = false;
                    break;
                }
            }

            if ($placed) {
                return true;
            }
        }

        return false;
    }


}
