<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;

echo "=== Algorithm Comparison ===\n\n";

$items = [
    new Item(18.2, 7.5, 3.1),
    new Item(5.0, 5.0, 5.0),
    new Item(12.0, 9.0, 2.0),
    new Item(3.5, 3.5, 3.5),
    new Item(7.8, 4.2, 6.0),
    new Item(1.0, 1.0, 1.0),
    new Item(10.5, 10.5, 1.5),
    new Item(6.0, 6.0, 6.0),
    new Item(14.0, 2.0, 2.0),
    new Item(4.0, 4.0, 8.0),
];

$totalVolume = array_sum(array_map(fn(Item $i) => $i->volume(), $items));

echo "10 items, total volume: " . round($totalVolume, 2) . "\n\n";

$algorithms = [
    'Guillotine'   => SmallestBoxFinder::ALGO_GUILLOTINE,
    'MaxRects'     => SmallestBoxFinder::ALGO_MAXRECTS,
    'ExtremePoint' => SmallestBoxFinder::ALGO_EXTREMEPOINT,
];

foreach ($algorithms as $name => $algo) {
    $start = hrtime(true);
    $finder = new SmallestBoxFinder($algo);
    $box = $finder->find($items);
    $elapsed = (hrtime(true) - $start) / 1e6; // milliseconds

    $efficiency = ($totalVolume / $box->volume()) * 100;

    printf(
        "%-12s: %s  (volume: %8.2f, efficiency: %5.1f%%, time: %6.1fms)\n",
        $name,
        $box,
        $box->volume(),
        $efficiency,
        $elapsed,
    );
}
