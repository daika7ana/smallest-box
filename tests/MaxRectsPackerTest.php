<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Packing\MaxRectsPacker;
use PHPUnit\Framework\TestCase;

class MaxRectsPackerTest extends TestCase
{
    public function testSingleItemPlacement(): void
    {
        $packer = new MaxRectsPacker(10.0, 10.0, 10.0);

        // Cube: only 1 unique rotation
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));
    }

    public function testMultipleItemsExactFit(): void
    {
        $packer = new MaxRectsPacker(10.0, 10.0, 10.0);

        // 10×10×5 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($packer->place([[10.0, 10.0, 5.0], [10.0, 5.0, 10.0], [5.0, 10.0, 10.0]]));
        $this->assertTrue($packer->place([[10.0, 10.0, 5.0], [10.0, 5.0, 10.0], [5.0, 10.0, 10.0]]));
    }

    public function testItemRejectionWhenSpaceExhausted(): void
    {
        $packer = new MaxRectsPacker(5.0, 5.0, 5.0);

        // Cube: only 1 unique rotation
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));
        $this->assertFalse($packer->place([[1.0, 1.0, 1.0]]));
    }

    public function testRotationEnablesFit(): void
    {
        // Box is 5 wide, 10 long, 5 tall.
        // Item [10, 5, 5] does not fit as-is (10 > 5 width),
        // but rotating to [5, 10, 5] fits exactly.
        $packer = new MaxRectsPacker(5.0, 10.0, 5.0);

        // 10×5×5 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($packer->place([[10.0, 5.0, 5.0], [5.0, 10.0, 5.0], [5.0, 5.0, 10.0]]));
    }

    public function testSpaceSplittingCorrectness(): void
    {
        // 5×10×10 box (volume 500) — 4 cubes of 5×5×5 fill it exactly.
        $packer = new MaxRectsPacker(5.0, 10.0, 10.0);

        // Cube: only 1 unique rotation
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));
        $this->assertTrue($packer->place([[5.0, 5.0, 5.0]]));

        // The box should now be completely full.
        $this->assertFalse($packer->place([[1.0, 1.0, 1.0]]));
    }
}
