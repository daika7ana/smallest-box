<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Box;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BoxTest extends TestCase
{
    public function testConstruction(): void
    {
        $box = new Box(10.0, 20.0, 30.0);

        $this->assertSame(10.0, $box->width());
        $this->assertSame(20.0, $box->length());
        $this->assertSame(30.0, $box->height());
    }

    public function testVolume(): void
    {
        $box = new Box(10.0, 20.0, 30.0);

        $this->assertSame(6000.0, $box->volume());
    }

    public function testFits(): void
    {
        $box = new Box(10.0, 20.0, 30.0);

        $this->assertTrue($box->fits(10.0, 20.0, 30.0));
        $this->assertTrue($box->fits(5.0, 10.0, 15.0));
        $this->assertFalse($box->fits(11.0, 20.0, 30.0));
        $this->assertFalse($box->fits(10.0, 21.0, 30.0));
        $this->assertFalse($box->fits(10.0, 20.0, 31.0));
    }

    public function testCompareTo(): void
    {
        $small = new Box(5.0, 5.0, 5.0);
        $large = new Box(10.0, 10.0, 10.0);
        $same = new Box(5.0, 5.0, 5.0);

        $this->assertLessThan(0, $small->compareTo($large));
        $this->assertGreaterThan(0, $large->compareTo($small));
        $this->assertSame(0, $small->compareTo($same));
    }

    public function testToString(): void
    {
        $box = new Box(10.5, 20.25, 30.0);

        $this->assertSame('10.50 x 20.25 x 30.00', (string) $box);
    }

    public function testZeroDimensionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Box(0.0, 10.0, 10.0);
    }

    public function testNegativeDimensionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Box(10.0, -5.0, 10.0);
    }
}
