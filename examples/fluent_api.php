<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;

echo "=== Fluent API ===\n\n";

$finder = new SmallestBoxFinder();

$finder
    ->add(new Item(5.0, 3.0, 2.0))
    ->add(new Item(3.0, 3.0, 3.0))
    ->add(new Item(2.0, 2.0, 1.0));

echo "Items in collection: " . count($finder->getItems()) . "\n";

$box = $finder->find();
printf("Box: %s (volume: %.1f)\n", $box, $box->volume());

$finder->remove(1); // Remove the 3x3x3 item
echo "\nAfter removing item at index 1: " . count($finder->getItems()) . " items\n";

$box = $finder->find();
printf("Box: %s (volume: %.1f)\n", $box, $box->volume());
