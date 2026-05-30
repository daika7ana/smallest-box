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

        $this->assertTrue($placement->place([5.0, 5.0, 5.0]));
    }

    public function testMultipleItemsExactFit(): void
    {
        $placement = new Placement(10.0, 10.0, 10.0);

        $this->assertTrue($placement->place([10.0, 10.0, 5.0]));
        $this->assertTrue($placement->place([10.0, 10.0, 5.0]));
    }

    public function testItemRejectionWhenSpaceExhausted(): void
    {
        $placement = new Placement(5.0, 5.0, 5.0);

        $this->assertTrue($placement->place([5.0, 5.0, 5.0]));
        $this->assertFalse($placement->place([1.0, 1.0, 1.0]));
    }

    public function testRotationEnablesFit(): void
    {
        // Box is 5 wide, 10 long, 5 tall.
        // Item [10, 5, 5] does not fit as-is (10 > 5 width),
        // but rotating to [5, 10, 5] fits exactly.
        $placement = new Placement(5.0, 10.0, 5.0);

        $this->assertTrue($placement->place([10.0, 5.0, 5.0]));
    }

    public function testSpaceSplittingCorrectness(): void
    {
        $placement = new Placement(10.0, 10.0, 10.0);

        // Place a 6 x 10 x 10 item — occupies the left 6 units of width.
        // This leaves a right slab: [6, 0, 0, 4, 10, 10]
        $this->assertTrue($placement->place([6.0, 10.0, 10.0]));

        // Place a 4 x 6 x 10 item in the right slab.
        // This consumes the left 4 units of that slab (6..10 in x, 0..6 in y),
        // and leaves a front slab: [6, 6, 0, 4, 4, 10]
        $this->assertTrue($placement->place([4.0, 6.0, 10.0]));

        // Place a 4 x 4 x 10 item in the remaining front slab.
        // This fills the box exactly — no space left.
        $this->assertTrue($placement->place([4.0, 4.0, 10.0]));

        // The box should now be completely full.
        $this->assertFalse($placement->place([1.0, 1.0, 1.0]));
    }
}
