<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Box;
use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SmallestBoxFinderTest extends TestCase
{
    private SmallestBoxFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new SmallestBoxFinder();
    }

    public function testEmptyItemsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->finder->find([]);
    }

    public function testSingleItem(): void
    {
        $items = [new Item(2.0, 3.0, 4.0)];
        $box = $this->finder->find($items);

        $this->assertSame(24.0, $box->volume());
        $this->assertTrue($box->fits(2.0, 3.0, 4.0));
    }

    public function testTwoIdenticalItems(): void
    {
        $items = [
            new Item(2.0, 2.0, 2.0),
            new Item(2.0, 2.0, 2.0),
        ];
        $box = $this->finder->find($items);

        // Total volume is 16, box must be at least that
        $this->assertGreaterThanOrEqual(16.0, $box->volume());
        // Each item must fit in some rotation
        $this->assertTrue($box->fits(2.0, 2.0, 2.0));
    }

    public function testMixedSizes(): void
    {
        $items = [
            new Item(5.0, 5.0, 5.0),
            new Item(3.0, 3.0, 3.0),
            new Item(2.0, 2.0, 2.0),
        ];
        $box = $this->finder->find($items);

        // Total volume: 125 + 27 + 8 = 160
        $this->assertGreaterThanOrEqual(160.0, $box->volume());
        // Largest item dimension is 5
        $this->assertGreaterThanOrEqual(5.0, $box->width());
        $this->assertGreaterThanOrEqual(5.0, $box->length());
        $this->assertGreaterThanOrEqual(5.0, $box->height());
    }

    public function testRotationRequired(): void
    {
        // Item is 1x10x1, needs rotation to fit efficiently
        $items = [
            new Item(1.0, 10.0, 1.0),
            new Item(1.0, 10.0, 1.0),
        ];
        $box = $this->finder->find($items);

        // Total volume is 20
        $this->assertGreaterThanOrEqual(20.0, $box->volume());
        // Box must accommodate the 10-unit dimension
        $maxDim = max($box->width(), $box->length(), $box->height());
        $this->assertGreaterThanOrEqual(10.0, $maxDim);
    }

    public function testFlatItems(): void
    {
        $items = [
            new Item(10.0, 10.0, 0.5),
            new Item(10.0, 10.0, 0.5),
            new Item(10.0, 10.0, 0.5),
            new Item(10.0, 10.0, 0.5),
        ];
        $box = $this->finder->find($items);

        // Total volume: 200, but rotation allows stacking — min is 100 (4 × 0.5 thick)
        $this->assertGreaterThanOrEqual(100.0, $box->volume());
        // Upper bound: optimal is 10×10×2 = 200, allow some slack
        $this->assertLessThanOrEqual(250.0, $box->volume());
        // Box must accommodate the 10-unit dimension in at least two axes
        $dims = [$box->width(), $box->length(), $box->height()];
        sort($dims);
        $this->assertGreaterThanOrEqual(10.0, $dims[1]);
        $this->assertGreaterThanOrEqual(10.0, $dims[2]);
    }

    public function testSingleTinyItem(): void
    {
        $items = [new Item(0.5, 0.5, 0.5)];
        $box = $this->finder->find($items);

        $this->assertSame(0.125, $box->volume());
    }

    public function testRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/^Could not find a box that fits all \d+ items \(total volume: \d+\.\d{4}\)\.$/',
        );

        // 30 identical cubes of 10×10×10: total volume is 30000,
        // but the candidate generator's largest dimension sum is 30 (triple sum),
        // giving a max candidate volume of 30×30×30 = 27000 < 30000,
        // so no candidate passes the volume filter → RuntimeException.
        $items = array_fill(0, 30, new Item(10.0, 10.0, 10.0));
        $this->finder->find($items);
    }

    public function testAdd(): void
    {
        $item = new Item(1.0, 2.0, 3.0);
        $this->finder->add($item);

        $items = $this->finder->getItems();
        $this->assertCount(1, $items);
        $this->assertSame($item, $items[0]);
    }

    public function testAddFluent(): void
    {
        $item = new Item(1.0, 2.0, 3.0);
        $result = $this->finder->add($item);

        $this->assertSame($this->finder, $result);
    }

    public function testRemove(): void
    {
        $item1 = new Item(1.0, 1.0, 1.0);
        $item2 = new Item(2.0, 2.0, 2.0);
        $this->finder->add($item1);
        $this->finder->add($item2);

        $this->finder->remove(0);

        $items = $this->finder->getItems();
        $this->assertCount(1, $items);
        $this->assertSame($item2, $items[0]);
    }

    public function testRemoveInvalidIndexThrows(): void
    {
        $this->expectException(OutOfRangeException::class);

        $this->finder->remove(0);
    }

    public function testRemoveFluent(): void
    {
        $this->finder->add(new Item(1.0, 1.0, 1.0));
        $result = $this->finder->remove(0);

        $this->assertSame($this->finder, $result);
    }

    public function testClear(): void
    {
        $this->finder->add(new Item(1.0, 1.0, 1.0));
        $this->finder->add(new Item(2.0, 2.0, 2.0));

        $this->finder->clear();

        $this->assertEmpty($this->finder->getItems());
    }

    public function testClearFluent(): void
    {
        $result = $this->finder->clear();

        $this->assertSame($this->finder, $result);
    }

    public function testGetItemsReturnsCopy(): void
    {
        $item = new Item(1.0, 1.0, 1.0);
        $this->finder->add($item);

        $returnedItems = $this->finder->getItems();
        $returnedItems[] = new Item(2.0, 2.0, 2.0);

        $this->assertCount(1, $this->finder->getItems());
    }

    public function testFindWithInternalCollection(): void
    {
        $this->finder->add(new Item(2.0, 3.0, 4.0));
        $box = $this->finder->find();

        $this->assertSame(24.0, $box->volume());
        $this->assertTrue($box->fits(2.0, 3.0, 4.0));
    }

    public function testFindWithInternalCollectionEmptyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->finder->find();
    }

    public function testMaxRectsAchievesHighEfficiency(): void
    {
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

        // MaxRects should find a box with very high efficiency
        $finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_MAXRECTS);
        $box = $finder->find($items);

        $efficiency = $totalVolume / $box->volume();
        $this->assertGreaterThanOrEqual(0.95, $efficiency, 'MaxRects should achieve at least 95% efficiency');
    }
}
