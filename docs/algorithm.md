# Algorithm Details

## Overview

`SmallestBoxFinder` solves the 3D bin packing problem: given a set of rectangular items, find the smallest axis-aligned box that can contain them. Items may be rotated into any of six orientations.

## Algorithm Steps

### 1. Sort Items

Items are sorted by volume in **descending** order. This largest-first heuristic is a standard greedy optimization: placing the biggest items first reduces fragmentation of free space.

### 2. Candidate Generation

Three strategies generate potential box dimensions:

| Strategy | Description |
|----------|-------------|
| Single-item rotations | Each item's six rotations are used directly as candidate box dimensions. |
| All dimension permutations | Cartesian product of all unique item dimensions along width, length, and height. |
| Pairwise sums | Sums of any two item dimensions are added as candidates (items placed side by side). |

Candidates with volume below the total item volume (minus a floating-point tolerance of `0.001`) are filtered out. Duplicates are removed.

Candidates are sorted by volume ascending so the smallest viable box is found first.

### 3. Greedy Packing

For each candidate box, the `Placement` class attempts to pack all items:

1. Initialize a single free-space region covering the entire box.
2. For each item (in volume-descending order):
   - Try all six rotations (deduped for dimensions that repeat).
   - Sort free spaces by a bottom-left-front heuristic (smallest `z`, then `y`, then `x`).
   - Place the item in the first space large enough.
   - Split the consumed space into right, front, and top slabs (maximal empty cuboids).
3. If any item cannot be placed, the candidate is rejected.

### 4. Result

The algorithm returns the first (smallest-volume) candidate box that successfully packs all items.

## Limitations

- **Heuristic, not optimal**: The greedy approach does not guarantee the absolute minimum bounding box. It finds a good solution quickly.
- **Combinatorial explosion**: With many unique item dimensions, candidate generation can produce many candidates. For practical use cases (items sharing standard dimensions), this is rarely an issue.
- **Axis-aligned only**: All placements are axis-aligned. Diagonal or non-axis-aligned rotations are not supported.
