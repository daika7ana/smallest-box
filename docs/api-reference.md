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

### `new SmallestBoxFinder()`

Creates a finder instance with an empty internal collection.

### `SmallestBoxFinder::add(Item $item): self`

Adds an item to the internal collection. Returns `$this` for fluent chaining.

### `SmallestBoxFinder::remove(int $index): self`

Removes the item at the given index from the internal collection. Returns `$this` for fluent chaining.

Throws `OutOfRangeException` if the index is out of bounds.

### `SmallestBoxFinder::getItems(): Item[]`

Returns all items in the internal collection.

### `SmallestBoxFinder::clear(): self`

Removes all items from the internal collection. Returns `$this` for fluent chaining.

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

---

## `Daika7ana\SmallestBox\Dimensional` (abstract class)

Abstract base class providing shared dimensional behaviour (`$width`, `$length`, `$height`, constructor validation, dimension getters, `volume()`). Extended by both `Box` and `Item`.

---

## `Daika7ana\SmallestBox\Placement` (internal)

Internal helper class for greedy 3D bin packing. Not part of the public API.
