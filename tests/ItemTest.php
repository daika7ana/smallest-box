<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Item;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    public function testConstruction(): void
    {
        $item = new Item(2.0, 3.0, 4.0);

        $this->assertSame(2.0, $item->width());
        $this->assertSame(3.0, $item->length());
        $this->assertSame(4.0, $item->height());
    }

    public function testVolume(): void
    {
        $item = new Item(2.0, 3.0, 4.0);

        $this->assertSame(24.0, $item->volume());
    }

    public function testRotations(): void
    {
        $item = new Item(2.0, 3.0, 4.0);
        $rotations = $item->rotations();

        $this->assertCount(6, $rotations);
        $this->assertContains([2.0, 3.0, 4.0], $rotations);
        $this->assertContains([2.0, 4.0, 3.0], $rotations);
        $this->assertContains([3.0, 2.0, 4.0], $rotations);
        $this->assertContains([3.0, 4.0, 2.0], $rotations);
        $this->assertContains([4.0, 2.0, 3.0], $rotations);
        $this->assertContains([4.0, 3.0, 2.0], $rotations);
    }

    public function testZeroDimensionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Item(0.0, 3.0, 4.0);
    }

    public function testNegativeDimensionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Item(2.0, -1.0, 4.0);
    }
}
