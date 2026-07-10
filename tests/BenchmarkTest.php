<?php

declare(strict_types=1);

namespace Daika7ana\SmallestBox\Tests;

use Daika7ana\SmallestBox\Box;
use Daika7ana\SmallestBox\Item;
use Daika7ana\SmallestBox\SmallestBoxFinder;
use PHPUnit\Framework\TestCase;

class BenchmarkTest extends TestCase
{
    /**
     * @return array<int, Item>
     */
    private function createBenchmarkItems(): array
    {
        $specs = [
            [5.0, 5.0, 5.0, 1],
            [28.0, 17.0, 39.0, 3],
            [44.0, 54.0, 33.0, 1],
            [5.0, 5.0, 5.0, 5],
            [8.0, 12.0, 8.0, 3],
        ];

        $items = [];
        foreach ($specs as [$w, $l, $h, $qty]) {
            for ($i = 0; $i < $qty; $i++) {
                $items[] = new Item($w, $l, $h);
            }
        }

        return $items;
    }

    public function testAllAlgorithmsReturnBox(): void
    {
        $items = $this->createBenchmarkItems();
        $algorithms = [
            'MaxRects' => SmallestBoxFinder::ALGO_MAXRECTS,
            'Guillotine' => SmallestBoxFinder::ALGO_GUILLOTINE,
            'ExtremePoint' => SmallestBoxFinder::ALGO_EXTREMEPOINT,
        ];

        foreach ($algorithms as $name => $algo) {
            $finder = new SmallestBoxFinder($algo);
            $box = $finder->find($items);

            $this->assertInstanceOf(Box::class, $box, "{$name} should return a Box");
        }
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: float, 3: float, 4: float}>
     */
    public function algorithmBoxProvider(): array
    {
        return [
            'MaxRects' => [SmallestBoxFinder::ALGO_MAXRECTS, 33.0, 54.0, 77.0, 137214.0],
            'Guillotine' => [SmallestBoxFinder::ALGO_GUILLOTINE, 33.0, 61.0, 82.0, 165066.0],
            'ExtremePoint' => [SmallestBoxFinder::ALGO_EXTREMEPOINT, 44.0, 56.0, 61.0, 150304.0],
        ];
    }

    /**
     * @dataProvider algorithmBoxProvider
     */
    public function testAlgorithmProducesExpectedBox(string $algo, float $width, float $length, float $height, float $volume): void
    {
        $items = $this->createBenchmarkItems();

        $finder = new SmallestBoxFinder($algo);
        $box = $finder->find($items);

        $this->assertSame($width, $box->width());
        $this->assertSame($length, $box->length());
        $this->assertSame($height, $box->height());
        $this->assertSame($volume, $box->volume());
    }

    public function testAllAlgorithmsFindBoxLargerThanTotalVolume(): void
    {
        $items = $this->createBenchmarkItems();
        $totalVolume = array_sum(array_map(fn(Item $i) => $i->volume(), $items));

        $algorithms = [
            'MaxRects' => SmallestBoxFinder::ALGO_MAXRECTS,
            'Guillotine' => SmallestBoxFinder::ALGO_GUILLOTINE,
            'ExtremePoint' => SmallestBoxFinder::ALGO_EXTREMEPOINT,
        ];

        foreach ($algorithms as $name => $algo) {
            $finder = new SmallestBoxFinder($algo);
            $box = $finder->find($items);

            $this->assertGreaterThanOrEqual(
                $totalVolume,
                $box->volume(),
                "{$name} box volume must be at least the total item volume",
            );
        }
    }

    private function boxFitsItem(Box $box, Item $item): bool
    {
        $dims = [$item->width(), $item->length(), $item->height()];

        $permutations = [
            [$dims[0], $dims[1], $dims[2]],
            [$dims[0], $dims[2], $dims[1]],
            [$dims[1], $dims[0], $dims[2]],
            [$dims[1], $dims[2], $dims[0]],
            [$dims[2], $dims[0], $dims[1]],
            [$dims[2], $dims[1], $dims[0]],
        ];

        foreach ($permutations as [$w, $l, $h]) {
            if ($box->fits($w, $l, $h)) {
                return true;
            }
        }

        return false;
    }

    public function testAllAlgorithmsAgreeOnMinimumDimensions(): void
    {
        $items = $this->createBenchmarkItems();

        // Largest single item in the benchmark set.
        $largestItem = new Item(44.0, 54.0, 33.0);

        $algorithms = [
            'MaxRects' => SmallestBoxFinder::ALGO_MAXRECTS,
            'Guillotine' => SmallestBoxFinder::ALGO_GUILLOTINE,
            'ExtremePoint' => SmallestBoxFinder::ALGO_EXTREMEPOINT,
        ];

        foreach ($algorithms as $name => $algo) {
            $finder = new SmallestBoxFinder($algo);
            $box = $finder->find($items);

            $this->assertTrue(
                $this->boxFitsItem($box, $largestItem),
                "{$name} box must fit the largest benchmark item in some rotation",
            );
        }
    }

    public function testMaxRectsAchievesHighEfficiencyOnBenchmark(): void
    {
        $items = $this->createBenchmarkItems();
        $totalVolume = array_sum(array_map(fn(Item $i) => $i->volume(), $items));

        $finder = new SmallestBoxFinder(SmallestBoxFinder::ALGO_MAXRECTS);
        $box = $finder->find($items);

        $efficiency = $totalVolume / $box->volume();

        $this->assertGreaterThanOrEqual(
            0.85,
            $efficiency,
            'MaxRects should achieve at least 85% efficiency on the benchmark set',
        );
    }
}
