<?php

namespace VISU\Tests\Testing;

use PHPUnit\Framework\TestCase;
use VISU\Testing\SnapshotComparator;

class SnapshotComparatorTest extends TestCase
{
    private function createSolidPng(int $w, int $h, int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $color);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    public function testIdenticalImagesReturnZero(): void
    {
        $png = $this->createSolidPng(10, 10, 128, 64, 200);

        $this->assertSame(0.0, SnapshotComparator::compare($png, $png));
    }

    public function testCompletelyDifferentImagesReturnHighValue(): void
    {
        $black = $this->createSolidPng(10, 10, 0, 0, 0);
        $white = $this->createSolidPng(10, 10, 255, 255, 255);

        $this->assertEqualsWithDelta(100.0, SnapshotComparator::compare($black, $white), 0.01);
    }

    public function testPartialDifference(): void
    {
        // Red vs slightly different red
        $a = $this->createSolidPng(10, 10, 200, 0, 0);
        $b = $this->createSolidPng(10, 10, 210, 0, 0);

        $diff = SnapshotComparator::compare($a, $b);
        $this->assertGreaterThan(0.0, $diff);
        $this->assertLessThan(5.0, $diff); // small difference
    }

    public function testSizeMismatchThrows(): void
    {
        $a = $this->createSolidPng(10, 10, 0, 0, 0);
        $b = $this->createSolidPng(20, 10, 0, 0, 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('size mismatch');
        SnapshotComparator::compare($a, $b);
    }

    public function testInvalidPngThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        SnapshotComparator::compare('not-a-png', 'also-not-a-png');
    }

    public function testGenerateDiffImageReturnsPng(): void
    {
        $a = $this->createSolidPng(10, 10, 255, 0, 0);
        $b = $this->createSolidPng(10, 10, 0, 0, 255);

        $diffPng = SnapshotComparator::generateDiffImage($a, $b);

        // Verify it's valid PNG
        $img = imagecreatefromstring($diffPng);
        $this->assertNotFalse($img);

        // Should be 3x width + 2x gap
        $this->assertSame(10 * 3 + 4 * 2, imagesx($img));
        $this->assertSame(10, imagesy($img));
        imagedestroy($img);
    }
}
