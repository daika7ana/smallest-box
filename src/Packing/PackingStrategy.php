<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Packing;

interface PackingStrategy
{
    /**
     * Attempt to place an item with the given rotations.
     *
     * @param array<int, array{0: float, 1: float, 2: float}> $rotations
     * @return bool true if placed successfully
     */
    public function place(array $rotations): bool;
}
