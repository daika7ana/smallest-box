<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;

echo "=== Basic Usage ===\n\n";

$items = [
    new Item(5.0, 3.0, 2.0),
    new Item(3.0, 3.0, 3.0),
    new Item(2.0, 2.0, 1.0),
];

$finder = new SmallestBoxFinder();
$box = $finder->find($items);

echo "Items:\n";
foreach ($items as $i => $item) {
    printf("  %d: %.1f x %.1f x %.1f\n", $i + 1, $item->width(), $item->length(), $item->height());
}

printf("\nSmallest box: %s\n", $box);
printf("Box volume: %.1f\n", $box->volume());
