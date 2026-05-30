<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Placement;
use PHPUnit\Framework\TestCase;

class PlacementTest extends TestCase
{
    public function testSingleItemPlacement(): void
    {
        $placement = new Placement(10.0, 10.0, 10.0);

        // Cube: only 1 unique rotation
        $this->assertTrue($placement->place([[5.0, 5.0, 5.0]]));
    }

    public function testMultipleItemsExactFit(): void
    {
        $placement = new Placement(10.0, 10.0, 10.0);

        // 10×10×5 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($placement->place([[10.0, 10.0, 5.0], [10.0, 5.0, 10.0], [5.0, 10.0, 10.0]]));
        $this->assertTrue($placement->place([[10.0, 10.0, 5.0], [10.0, 5.0, 10.0], [5.0, 10.0, 10.0]]));
    }

    public function testItemRejectionWhenSpaceExhausted(): void
    {
        $placement = new Placement(5.0, 5.0, 5.0);

        // Cube: only 1 unique rotation
        $this->assertTrue($placement->place([[5.0, 5.0, 5.0]]));
        $this->assertFalse($placement->place([[1.0, 1.0, 1.0]]));
    }

    public function testRotationEnablesFit(): void
    {
        // Box is 5 wide, 10 long, 5 tall.
        // Item [10, 5, 5] does not fit as-is (10 > 5 width),
        // but rotating to [5, 10, 5] fits exactly.
        $placement = new Placement(5.0, 10.0, 5.0);

        // 10×5×5 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($placement->place([[10.0, 5.0, 5.0], [5.0, 10.0, 5.0], [5.0, 5.0, 10.0]]));
    }

    public function testSpaceSplittingCorrectness(): void
    {
        $placement = new Placement(10.0, 10.0, 10.0);

        // 6×10×10 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($placement->place([[6.0, 10.0, 10.0], [10.0, 6.0, 10.0], [10.0, 10.0, 6.0]]));

        // 4×6×10 has all different → 6 unique rotations
        $this->assertTrue($placement->place([
            [4.0, 6.0, 10.0],
            [4.0, 10.0, 6.0],
            [6.0, 4.0, 10.0],
            [6.0, 10.0, 4.0],
            [10.0, 4.0, 6.0],
            [10.0, 6.0, 4.0],
        ]));

        // 4×4×10 has 2 equal dimensions → 3 unique rotations
        $this->assertTrue($placement->place([[4.0, 4.0, 10.0], [4.0, 10.0, 4.0], [10.0, 4.0, 4.0]]));

        // The box should now be completely full.
        $this->assertFalse($placement->place([[1.0, 1.0, 1.0]]));
    }
}
