# Usage & Examples

## Basic Usage

Create items with their dimensions (width, length, height), then find the smallest box that fits them:

```php
use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\Box;
use Daika7ana\SmallestBox\SmallestBoxFinder;

$items = [
    new Item(10.0, 5.0, 3.0),
    new Item(4.0, 4.0, 4.0),
    new Item(6.0, 2.0, 1.0),
];

$finder = new SmallestBoxFinder();
$box = $finder->find($items);

echo "Box dimensions: {$box}";        // e.g. "10.00 x 9.00 x 3.00"
echo "Box volume: " . $box->volume(); // 270.0
```

## Checking if a Box Fits an Item

```php
$box = new Box(10.0, 10.0, 5.0);

$box->fits(5.0, 5.0, 3.0);   // true
$box->fits(11.0, 5.0, 3.0);  // false — too wide
$box->fits(5.0, 5.0, 6.0);   // false — too tall
```

## Comparing Boxes by Volume

```php
$a = new Box(10.0, 10.0, 10.0); // volume: 1000
$b = new Box(5.0, 5.0, 5.0);    // volume: 125

$a->compareTo($b); // 1  (a is larger)
$b->compareTo($a); // -1 (b is smaller)
```

## Item Rotations

Items can be rotated in all six axis-aligned orientations:

```php
$item = new Item(2.0, 3.0, 4.0);

foreach ($item->rotations() as [$w, $l, $h]) {
    printf("  %.1f x %.1f x %.1f\n", $w, $l, $h);
}
// Output:
//   2.0 x 3.0 x 4.0
//   2.0 x 4.0 x 3.0
//   3.0 x 2.0 x 4.0
//   3.0 x 4.0 x 2.0
//   4.0 x 2.0 x 3.0
//   4.0 x 3.0 x 2.0
```

## Fluent API (Add/Remove Items)

You can build the item list incrementally using `add()` and `remove()`:

```php
$finder = new SmallestBoxFinder();

$finder
    ->add(new Item(10.0, 5.0, 3.0))
    ->add(new Item(4.0, 4.0, 4.0))
    ->add(new Item(6.0, 2.0, 1.0));

$box = $finder->find(); // Uses internal collection
```

Remove items by index:

```php
$finder->remove(1); // Removes the second item (0-indexed)

$box = $finder->find(); // Finds box for remaining items
```

Other utility methods:

```php
$items = $finder->getItems(); // Get current items
$finder->clear();             // Remove all items
```

You can still pass items directly to `find()` — this ignores the internal collection:

```php
$box = $finder->find($items); // Uses passed items, not internal collection
```

## Error Handling

- Passing an **empty array** to `find()` throws `InvalidArgumentException`.
- If no box can fit all items, `find()` throws `RuntimeException`.
- Creating a `Box` or `Item` with zero or negative dimensions throws `InvalidArgumentException`.

## Packing Algorithms

Three packing algorithms are available:

| Algorithm | Efficiency | Speed | Best For |
|-----------|-----------|-------|----------|
| `ALGO_GUILLOTINE` (default) | ~65% | Fast | Uniform items, speed-critical |
| `ALGO_EXTREMEPOINT` | ~75% | Medium | Balanced efficiency and speed |
| `ALGO_MAXRECTS` | ~100% | Slower | Diverse items, best fit |

### Using MaxRects

```php
// Via constructor
$finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_MAXRECTS);
$box = $finder->find($items);

// Via setter
$finder = new SmallestBoxFinder();
$finder->setAlgorithm(SmallestBoxFinder::ALGO_MAXRECTS);
$box = $finder->find($items);
```

The MaxRects algorithm tries all 6 axis orderings when splitting free space, preserving corner regions that guillotine splits lose. This results in significantly better packing for diverse item sets.

### Using ExtremePoint

```php
// Via constructor
$finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_EXTREMEPOINT);
$box = $finder->find($items);

// Via setter
$finder = new SmallestBoxFinder();
$finder->setAlgorithm(SmallestBoxFinder::ALGO_EXTREMEPOINT);
$box = $finder->find($items);
```

The ExtremePoint algorithm maintains a list of corner points where items can be placed, rather than tracking free-space cuboids. After each placement, it generates new points from the item's faces and removes invalid points. This simple heuristic is fast but may leave gaps that more sophisticated algorithms can fill.

## Custom Sort & Pack Orders

You can add custom sort orders and pack orders to fine-tune packing behavior for your specific use case.

### Custom Sort Orders

Sort orders control the order in which candidate box sizes are tested with different item orderings:

```php
$finder = new SmallestBoxFinder();

// Sort items by height ascending (build layers from bottom up)
$finder->addSortOrder(function (Item $a, Item $b): int {
    return $a->height() <=> $b->height();
});

// Sort items by footprint descending (largest base first)
$finder->addSortOrder(function (Item $a, Item $b): int {
    return ($b->width() * $b->length()) <=> ($a->width() * $a->length());
});
```

### Custom Pack Orders

Pack orders control the order in which items are fed to the packer when testing a candidate box:

```php
// Pack widest items first
$finder->addPackOrder(function (Item $a, Item $b): int {
    return $b->width() <=> $a->width();
});

// Pack tallest items first
$finder->addPackOrder(function (Item $a, Item $b): int {
    return $b->height() <=> $a->height();
});
```

Custom orders are tried after the built-in ones. Both methods support fluent chaining.

## Performance Notes

The candidate generation grows combinatorially with the number of unique item dimensions. For most practical packing problems (tens of items with shared dimensions), performance is acceptable. For hundreds of items with many unique dimensions, consider pre-filtering or batching.
