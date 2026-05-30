<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox;

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

    private const EPSILON = 0.001;

    /** @var Item[] */
    private array $items = [];

    private string $algorithm;

    /**
     * @param string $algorithm Packing algorithm to use (ALGO_GUILLOTINE or ALGO_MAXRECTS)
     */
    public function __construct(string $algorithm = self::ALGO_GUILLOTINE)
    {
        $this->algorithm = $algorithm;
    }

    /**
     * Set the packing algorithm.
     *
     * @param string $algorithm Packing algorithm to use (ALGO_GUILLOTINE or ALGO_MAXRECTS)
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
        $sortOrders = [
            // Volume descending (largest first)
            function (Item $a, Item $b): int {
                return $b->volume() <=> $a->volume();
            },
            // Max dimension descending
            function (Item $a, Item $b): int {
                return max($b->width(), $b->length(), $b->height())
                    <=> max($a->width(), $a->length(), $a->height());
            },
            // Width descending
            function (Item $a, Item $b): int {
                return $b->width() <=> $a->width();
            },
            // Length descending
            function (Item $a, Item $b): int {
                return $b->length() <=> $a->length();
            },
            // Height descending
            function (Item $a, Item $b): int {
                return $b->height() <=> $a->height();
            },
            // Footprint descending (width * length)
            function (Item $a, Item $b): int {
                return ($b->width() * $b->length()) <=> ($a->width() * $a->length());
            },
            // Surface area descending — hard-to-place items first
            function (Item $a, Item $b): int {
                $saA = 2 * ($a->width() * $a->length() + $a->width() * $a->height() + $a->length() * $a->height());
                $saB = 2 * ($b->width() * $b->length() + $b->width() * $b->height() + $b->length() * $b->height());
                return $saB <=> $saA;
            },
            // Volume ascending (smallest first)
            function (Item $a, Item $b): int {
                return $a->volume() <=> $b->volume();
            },
            // Width ascending
            function (Item $a, Item $b): int {
                return $a->width() <=> $b->width();
            },
            // Length ascending
            function (Item $a, Item $b): int {
                return $a->length() <=> $b->length();
            },
            // Height ascending
            function (Item $a, Item $b): int {
                return $a->height() <=> $b->height();
            },
        ];

        $bestBox = null;

        foreach ($candidates as $candidate) {
            if ($bestBox !== null && $candidate[3] >= $bestBox->volume()) {
                break;
            }

            // Try each sort order
            foreach ($sortOrders as $sortFn) {
                $sortedItems = $items;
                usort($sortedItems, $sortFn);

                if ($this->canPack($sortedItems, $candidate[0], $candidate[1], $candidate[2])) {
                    $bestBox = new Box($candidate[0], $candidate[1], $candidate[2]);
                    break 2; // Found a match, break both loops
                }
            }

            // Exact volume match is optimal
            if ($bestBox !== null && abs($candidate[3] - $totalVolumeRounded) < self::EPSILON) {
                break;
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
            $allDims = array_unique(array_merge($dims, $pairDims ?? $dims, $tripleDims));
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
     * Create a packer instance based on the selected algorithm.
     */
    private function createPacker(float $w, float $l, float $h): PackingStrategy
    {
        if ($this->algorithm === self::ALGO_MAXRECTS) {
            return new MaxRectsPacker($w, $l, $h);
        }
        return new GuillotinePacker($w, $l, $h);
    }

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

        // Try different item orderings inside the packer
        $packOrders = [
            null, // use items as-is (already sorted by caller)
            function (Item $a, Item $b): int {
                return $a->volume() <=> $b->volume();
            },
            function (Item $a, Item $b): int {
                return max($a->width(), $a->length(), $a->height())
                    <=> max($b->width(), $b->length(), $b->height());
            },
            function (Item $a, Item $b): int {
                return ($a->width() * $a->length()) <=> ($b->width() * $b->length());
            },
        ];

        foreach ($packOrders as $sortFn) {
            $attemptItems = $items;
            if ($sortFn !== null) {
                usort($attemptItems, $sortFn);
            }

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
