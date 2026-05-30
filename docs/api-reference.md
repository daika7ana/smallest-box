# API Reference

## `Daika7ana\SmallestBox\Item`

Immutable value object representing a rectangular item.

### `new Item(float $width, float $length, float $height)`

Creates an item with the given dimensions. All dimensions must be positive.

Throws `InvalidArgumentException` if any dimension is <= 0.

### `Item::width(): float`
### `Item::length(): float`
### `Item::height(): float`

Return the respective dimension.

### `Item::volume(): float`

Returns `width * length * height`.

### `Item::rotations(): array`

Returns all six axis-aligned rotations as an array of `[width, length, height]` tuples.

---

## `Daika7ana\SmallestBox\Box`

Immutable value object representing a box (container).

### `new Box(float $width, float $length, float $height)`

Creates a box with the given dimensions. All dimensions must be positive.

Throws `InvalidArgumentException` if any dimension is <= 0.

### `Box::width(): float`
### `Box::length(): float`
### `Box::height(): float`

Return the respective dimension.

### `Box::volume(): float`

Returns `width * length * height`.

### `Box::fits(float $itemWidth, float $itemLength, float $itemHeight): bool`

Returns `true` if an item with the given dimensions would fit inside this box (axis-aligned, no rotation considered).

### `Box::compareTo(Box $other): int`

Compares this box to another by volume. Returns:
- `-1` if this box is smaller
- `0` if equal
- `1` if this box is larger

### `Box::__toString(): string`

Returns a formatted string like `"10.00 x 20.00 x 30.00"`.

---

## `Daika7ana\SmallestBox\SmallestBoxFinder`

Main entry point. Finds the smallest box that fits a set of items.

### Constants

- `SmallestBoxFinder::ALGO_GUILLOTINE` — Guillotine split algorithm (default). Fast, ~63% efficiency for diverse items.
- `SmallestBoxFinder::ALGO_MAXRECTS` — Maximal rectangles algorithm. Slower, ~100% efficiency for diverse items.
- `SmallestBoxFinder::ALGO_EXTREMEPOINT` — Extreme point (corner placement) algorithm. Simple corner-based placement, useful as an alternative heuristic.

### `new SmallestBoxFinder(string $algorithm = self::ALGO_GUILLOTINE)`

Creates a finder instance with an empty internal collection. Optionally specify the packing algorithm.

### `SmallestBoxFinder::setAlgorithm(string $algorithm): self`

Sets the packing algorithm. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::add(Item $item): self`

Adds an item to the internal collection. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::remove(int $index): self`

Removes the item at the given index from the internal collection. Returns `$this` for fluent chaining.

Throws `OutOfRangeException` if the index is out of bounds.

### `SmallestBoxFinder::getItems(): Item[]`

Returns all items in the internal collection.

### `SmallestBoxFinder::clear(): self`

Removes all items from the internal collection. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::addSortOrder(callable $comparator): self`

Adds a custom sort order to try when packing items. The callable receives two `Item` objects and returns an int comparison result (like `usort`). Custom orders are tried after the built-in ones. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::addPackOrder(callable $comparator): self`

Adds a custom pack order to try when packing items into a candidate box. The callable receives two `Item` objects and returns an int comparison result. Custom orders are tried after the built-in ones. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::find(?array $items = null): Box`

Finds the smallest axis-aligned box that fits all items, considering all six rotations.

- If `$items` is provided, uses those items.
- If `$items` is `null`, uses the internal collection.

Algorithm:
1. Sorts items by volume descending.
2. Generates candidate box dimensions from item dimension permutations.
3. Tests candidates with greedy 3D bin packing.
4. Returns the smallest successful box.

Throws:
- `InvalidArgumentException` if items is empty.
- `RuntimeException` if no box can fit all items.

## `Daika7ana\SmallestBox\Dimensional` (abstract class)

Abstract base class providing shared dimensional behaviour (`$width`, `$length`, `$height`, constructor validation, dimension getters, `volume()`). Extended by both `Box` and `Item`.

---

## `Daika7ana\SmallestBox\Packing\PackingStrategy` (interface)

Interface for packing algorithm implementations.

### `PackingStrategy::place(array $rotations): bool`

Attempt to place an item with the given rotations inside the box.

- `$rotations` — Array of `[width, length, height]` tuples (one per unique rotation).
- Returns `true` if placed successfully, `false` if no space available.

---

## `Daika7ana\SmallestBox\Packing\GuillotinePacker`

Guillotine 3D bin packer. Splits free space into three orthogonal slabs (right, front, top) after each placement. Fast but may leave fragmented free space.

Implements `PackingStrategy`.

### `new GuillotinePacker(float $boxWidth, float $boxLength, float $boxHeight)`

Creates a packer for a box with the given dimensions.

---

## `Daika7ana\SmallestBox\Packing\MaxRectsPacker`

Maximal rectangles 3D bin packer. Tries all 6 axis orderings when splitting free space, keeping the ordering that produces the best remaining cuboids. Slower but achieves significantly better packing efficiency for diverse item sets.

Implements `PackingStrategy`.

### `new MaxRectsPacker(float $boxWidth, float $boxLength, float $boxHeight)`

Creates a packer for a box with the given dimensions.

---

## `Daika7ana\SmallestBox\Packing\ExtremePointPacker`

Extreme point (corner placement) 3D bin packer. Maintains a list of extreme points — corner coordinates where items can be placed. For each item, evaluates all points and rotations, selecting the placement with the best score (deep-bottom-left, wall contact, and tight fit). After placement, generates new extreme points from the placed item's faces.

Implements `PackingStrategy`.

### `new ExtremePointPacker(float $boxWidth, float $boxLength, float $boxHeight)`

Creates a packer for a box with the given dimensions.


