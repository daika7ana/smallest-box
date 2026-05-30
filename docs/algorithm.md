# Algorithm Details

## Overview

`SmallestBoxFinder` solves the 3D bin packing problem: given a set of rectangular items, find the smallest axis-aligned box that can contain them. Items may be rotated into any of six orientations.

Three packing algorithms are available via the `PackingStrategy` interface.

## Algorithm Steps

### 1. Sort Items

Items are sorted using multiple heuristics (volume, max dimension, footprint, surface area, individual dimensions). The finder tries each sort order and picks the smallest box that works with any of them.

### 2. Candidate Generation

Four strategies generate potential box dimensions:

| Strategy | Description |
|----------|-------------|
| Single-item rotations | Each item's rotations are used directly as candidate box dimensions. |
| All dimension permutations | Cartesian product of all unique item dimensions along width, length, and height. |
| Pairwise sums | Sums of any two item dimensions (items placed side by side). |
| Triple sums | Sums of any three item dimensions (items placed end to end). |

Candidates with volume below the total item volume are filtered out. Per-axis feasibility checks reject candidates whose largest dimension can't fit the largest item. Duplicates are removed.

Candidates are sorted by volume ascending so the smallest viable box is found first. Exact-volume candidates are tried first as a short-circuit optimization.

### 3. Packing

For each candidate box, the selected packing algorithm attempts to pack all items using multiple item orderings:

#### GuillotinePacker (default)

1. Initialize a single free-space region covering the entire box.
2. For each item:
   - Try all rotations.
   - Score each (space, rotation) pair using wall contact, tightness, position, and waste metrics.
   - Place the item in the best-scoring space.
   - Split the consumed space into three guillotine slabs (right, front, top).
   - Prune subsumed spaces and merge adjacent spaces.
3. If any item cannot be placed, the candidate is rejected.

#### MaxRectsPacker

1. Initialize a single free-space region covering the entire box.
2. For each item:
   - Try all rotations.
   - Score each (space, rotation) pair (same as GuillotinePacker).
   - Try all 6 axis orderings for splitting the consumed space.
   - Pick the split ordering that produces the best remaining cuboids.
   - Prune subsumed spaces and merge adjacent spaces.
3. If any item cannot be placed, the candidate is rejected.

The key difference: GuillotinePacker always splits X→Y→Z, while MaxRectsPacker tries all 6 permutations (X→Y→Z, X→Z→Y, Y→X→Z, Y→Z→X, Z→X→Y, Z→Y→X) and picks the best. This preserves corner regions that guillotine splits lose, resulting in significantly better packing for diverse item sets.

#### ExtremePointPacker

1. Initialize a single extreme point at (0, 0, 0).
2. For each item:
   - For each extreme point (sorted by z, then y, then x):
     - For each rotation: check if the item fits at that point (within bounds, no overlap).
     - Score the placement using wall contact, contact with placed items, deep-bottom-left position, and box tightness.
   - Place the item at the best-scoring (point, rotation) pair.
   - Generate three new extreme points from the placed item's faces:
     - Right face: (x + width, y, z)
     - Front face: (x, y + length, z)
     - Top face: (x, y, z + height)
   - Remove points that are inside placed items or outside the box.
   - Deduplicate nearby points (within EPSILON).
3. If any item cannot be placed, the candidate is rejected.

The ExtremePointPacker follows a simpler strategy than the other two: instead of tracking free-space cuboids, it only tracks corner points where new items can start. This makes it lightweight, though it may leave gaps that guillotine or max-rects strategies can fill.

### 4. Result

The algorithm returns the first (smallest-volume) candidate box that successfully packs all items.

## Performance Characteristics

| Aspect | GuillotinePacker | MaxRectsPacker | ExtremePointPacker |
|--------|-----------------|----------------|--------------------|
| Efficiency | ~65% for diverse items | ~100% for diverse items | ~50-60% for diverse items |
| Speed | Fast (3 splits per placement) | ~2-4x slower (6 split orderings per placement) | Fast (point list management) |
| Space tracking | Non-overlapping slabs | Overlapping cuboids with pruning | Extreme point cloud |
| Best for | Uniform items, speed-critical | Diverse items, best fit | Simple heuristic, speed |

## Limitations

- **Heuristic, not optimal**: The greedy approach does not guarantee the absolute minimum bounding box. It finds a good solution quickly.
- **Combinatorial explosion**: With many unique item dimensions, candidate generation can produce many candidates. Strategy 3 is capped at 8 unique dimensions, and Strategy 4 at 6.
- **Axis-aligned only**: All placements are axis-aligned. Diagonal or non-axis-aligned rotations are not supported.
