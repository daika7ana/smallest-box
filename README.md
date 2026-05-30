# smallest-box

[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-777BB4.svg)](https://php.net)
[![Packagist Version](https://img.shields.io/packagist/v/daika7ana/smallest-box)](https://packagist.org/packages/daika7ana/smallest-box)

Calculate the smallest rectangular box (W x L x H) that fits a set of rectangular items, using axis-aligned 3D bin packing with rotation support.

## Features

- Finds the smallest box that fits all items via greedy 3D bin packing
- Supports all six axis-aligned rotations for optimal fitting
- Three packing algorithms: guillotine (default), maximal rectangles, and extreme point
- Custom sort orders and pack orders via closures
- Fluent API for building item lists incrementally
- Immutable value objects for items and boxes
- Zero dependencies

## Installation

```bash
composer require daika7ana/smallest-box
```

Requires PHP >= 7.4.

## Quick Start

### Pass items directly

```php
use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;

$items = [
    new Item(5.0, 3.0, 2.0),
    new Item(3.0, 3.0, 3.0),
    new Item(2.0, 2.0, 1.0),
];

$finder = new SmallestBoxFinder();
$box = $finder->find($items);

echo $box;                       // "5.00 x 5.00 x 3.00"
echo $box->volume();             // 75.0
```

### Build item list incrementally

```php
$finder = new SmallestBoxFinder();

$finder
    ->add(new Item(5.0, 3.0, 2.0))
    ->add(new Item(3.0, 3.0, 3.0))
    ->add(new Item(2.0, 2.0, 1.0));

$box = $finder->find();
```

### Choose a packing algorithm

```php
// Default: guillotine (fast, ~65% efficiency for diverse items)
$finder = new SmallestBoxFinder();

// MaxRects (slower, ~100% efficiency for diverse items)
$finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_MAXRECTS);

// ExtremePoint (medium speed, ~75% efficiency for diverse items)
$finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_EXTREMEPOINT);
```

## Algorithm Comparison

| Algorithm | Efficiency | Speed | Best For |
|-----------|-----------|-------|----------|
| `ALGO_GUILLOTINE` (default) | ~65% | Fast | Uniform items, speed-critical |
| `ALGO_EXTREMEPOINT` | ~75% | Medium | Balanced efficiency and speed |
| `ALGO_MAXRECTS` | ~100% | Slower | Diverse items, best fit |

## How It Works

1. **Sort** items by multiple heuristics (volume, dimensions, footprint).
2. **Generate** candidate box dimensions from item dimension permutations and sums.
3. **Test** each candidate with a packing algorithm that supports all six axis-aligned rotations.
4. **Return** the smallest box that successfully fits all items.

## Custom Sort & Pack Orders

You can add custom orderings to fine-tune packing for your specific use case:

```php
$finder = new SmallestBoxFinder();

// Add a custom sort order (tried after built-in ones)
$finder->addSortOrder(function (Item $a, Item $b): int {
    return $a->height() <=> $b->height();
});

// Add a custom pack order
$finder->addPackOrder(function (Item $a, Item $b): int {
    return $b->width() <=> $a->width();
});

$box = $finder->find($items);
```

## Examples

See the [examples/](examples/) directory for runnable scripts:

- [basic_usage.php](examples/basic_usage.php) — Simple item creation and box finding
- [fluent_api.php](examples/fluent_api.php) — Add/remove/clear fluent API
- [algorithm_comparison.php](examples/algorithm_comparison.php) — Guillotine vs MaxRects vs ExtremePoint comparison

## Documentation

- [Installation Guide](docs/installation.md)
- [Usage & Examples](docs/usage.md)
- [API Reference](docs/api-reference.md)
- [Algorithm Details](docs/algorithm.md)

## License

GPL-3.0-or-later. See [LICENSE](LICENSE) for details.
