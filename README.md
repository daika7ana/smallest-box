# smallest-box

<div align="center">

[![CI](https://github.com/daika7ana/smallest-box/actions/workflows/ci.yml/badge.svg)](https://github.com/daika7ana/smallest-box/actions/workflows/ci.yml)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-9.x-0A7BBB?style=flat-square)](docs/testing.md)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-blue?style=flat-square)](phpstan.neon)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-green?style=flat-square)](LICENSE)

Calculate the smallest rectangular box that fits a set of rectangular items, using axis-aligned 3D bin packing with rotation support.

</div>

> Zero dependencies, fully typed, three packing algorithms, and rotation-aware candidate generation.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Packing Algorithms](#packing-algorithms)
- [Custom Sort & Pack Orders](#custom-sort--pack-orders)
- [Testing](#testing)
- [Documentation](#documentation)

## Features

### Core Strengths

- ✅ **Smallest Box Finding** — Greedy 3D bin packing with rotation support
- ✅ **Six Axis-Aligned Rotations** — Items tested in all orientations for optimal fit
- ✅ **Three Packing Algorithms** — Guillotine, MaxRects, and ExtremePoint
- ✅ **Custom Sort & Pack Orders** — Fine-tune item ordering via closures
- ✅ **Fluent API** — Build item lists incrementally with `add()`, `remove()`, `clear()`
- ✅ **Immutable Value Objects** — `Item` and `Box` with type-safe accessors
- ✅ **Static Analysis with PHPStan** — Strict type checks across the codebase
- ✅ **Zero Dependencies** — Only requires PHP 7.4+

### Perfect For

- 🎯 Logistics and shipping box optimisation
- 🎯 Warehouse packing calculations
- 🎯 3D layout and spatial planning
- 🎯 Any scenario needing the smallest bounding box for rectangular items

## Requirements

- **PHP 7.4+**

## Installation

### Via Composer

```bash
composer require daika7ana/smallest-box
```

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

echo $box;           // "5.00 x 5.00 x 3.00"
echo $box->volume(); // 75.0
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

That's it! You've got the smallest box that fits all your items.

## Packing Algorithms

| Algorithm | Efficiency | Speed | Best For |
|-----------|-----------|-------|----------|
| `ALGO_GUILLOTINE` (default) | ~65% | Fast | Uniform items, speed-critical |
| `ALGO_EXTREMEPOINT` | ~75% | Medium | Balanced efficiency and speed |
| `ALGO_MAXRECTS` | ~100% | Slower | Diverse items, best fit |

### How It Works

1. **Sort** items by multiple heuristics (volume, dimensions, footprint).
2. **Generate** candidate box dimensions from item dimension permutations and sums.
3. **Test** each candidate with the selected packing algorithm and all six rotations.
4. **Return** the smallest box that successfully fits all items.

## Custom Sort & Pack Orders

Fine-tune packing behaviour for your specific use case:

```php
$finder = new SmallestBoxFinder();

// Sort items by height ascending (build layers from bottom up)
$finder->addSortOrder(function (Item $a, Item $b): int {
    return $a->height() <=> $b->height();
});

// Pack widest items first
$finder->addPackOrder(function (Item $a, Item $b): int {
    return $b->width() <=> $a->width();
});

$box = $finder->find($items);
```

Custom orders are tried after the built-in ones. Both methods support fluent chaining.

## Testing

### Run Static Analysis

```bash
composer stan
```

PHPStan is configured via `phpstan.neon` and analyses the `src/` directory.

### Run Code Style Checks

```bash
composer pint
```

### Run Full Test Suite

```bash
composer test
```

### Run The Same Checks As CI

```bash
composer ci
```

Available Composer scripts:

- `composer pint` — Check code style (Laravel Pint)
- `composer stan` — Run static analysis (PHPStan)
- `composer test` — Run the test suite (PHPUnit)
- `composer ci` — Run all checks in sequence

## Documentation

| Guide | Purpose |
|-------|---------|
| 📖 [Usage & Examples](docs/usage.md) | Working examples and patterns |
| 🔧 [Installation](docs/installation.md) | Detailed setup instructions |
| 📋 [API Reference](docs/api-reference.md) | Complete class and method reference |
| ⚙️ [Algorithm Details](docs/algorithm.md) | How the packing algorithms work |

---

<div align="center">

Made with ❤️

**Questions?** [Check the docs](docs/usage.md) or [open an issue](../../issues)

</div>
