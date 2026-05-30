# smallest-box

[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-777BB4.svg)](https://php.net)
[![Packagist Version](https://img.shields.io/packagist/v/daika7ana/smallest-box)](https://packagist.org/packages/daika7ana/smallest-box)

Calculate the smallest rectangular box (W x L x H) that fits a set of rectangular items, using axis-aligned 3D bin packing with rotation support.

## Installation

```bash
composer require daika7ana/smallest-box
```

Requires PHP >= 7.4.

## Quick Start

```php
use Daika7ana\SmallestBox\Box;
use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;

$items = [
    new Item(5.0, 3.0, 2.0),
    new Item(3.0, 3.0, 3.0),
    new Item(2.0, 2.0, 1.0),
];

$finder = new SmallestBoxFinder();
$box    = $finder->find($items);

echo $box;                   // e.g. "5.00 x 5.00 x 3.00"
echo $box->volume();         // 75.0
echo $box->width();          // 5.0
echo $box->fits(3.0, 3.0, 2.0); // true
```

## How It Works

1. **Sort** items by volume descending (largest-first heuristic).
2. **Generate** candidate box dimensions from item dimension permutations.
3. **Test** each candidate with a greedy 3D bin packing algorithm that supports all six axis-aligned rotations.
4. **Return** the smallest box that successfully fits all items.

## Documentation

- [Installation Guide](docs/installation.md)
- [Usage & Examples](docs/usage.md)
- [API Reference](docs/api-reference.md)
- [Algorithm Details](docs/algorithm.md)

## License

GPL-3.0-or-later. See [LICENSE](LICENSE) for details.
