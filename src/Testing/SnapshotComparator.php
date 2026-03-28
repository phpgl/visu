<?php

namespace VISU\Testing;

class SnapshotComparator
{
    /**
     * Compare two PNG images and return the mean absolute error as a percentage (0–100).
     *
     * @param string $actualPng Raw PNG binary of the rendered frame
     * @param string $referencePng Raw PNG binary of the reference snapshot
     * @return float Difference percentage (0.0 = identical, 100.0 = completely different)
     */
    public static function compare(string $actualPng, string $referencePng): float
    {
        $actual = @imagecreatefromstring($actualPng);
        $reference = @imagecreatefromstring($referencePng);

        if ($actual === false || $reference === false) {
            throw new \RuntimeException('Failed to decode PNG image for comparison.');
        }

        $aw = imagesx($actual);
        $ah = imagesy($actual);
        $rw = imagesx($reference);
        $rh = imagesy($reference);

        if ($aw !== $rw || $ah !== $rh) {
            imagedestroy($actual);
            imagedestroy($reference);
            throw new \RuntimeException(
                "Snapshot size mismatch: actual {$aw}x{$ah} vs reference {$rw}x{$rh}"
            );
        }

        $totalDiff = 0.0;
        $pixelCount = $aw * $ah;

        for ($y = 0; $y < $ah; $y++) {
            for ($x = 0; $x < $aw; $x++) {
                $ac = imagecolorat($actual, $x, $y);
                $rc = imagecolorat($reference, $x, $y);

                $ar = ($ac >> 16) & 0xFF;
                $ag = ($ac >> 8) & 0xFF;
                $ab = $ac & 0xFF;

                $rr = ($rc >> 16) & 0xFF;
                $rg = ($rc >> 8) & 0xFF;
                $rb = $rc & 0xFF;

                // Mean per-channel absolute difference for this pixel (0–255 → 0–1)
                $totalDiff += (abs($ar - $rr) + abs($ag - $rg) + abs($ab - $rb)) / (3.0 * 255.0);
            }
        }

        imagedestroy($actual);
        imagedestroy($reference);

        return ($totalDiff / $pixelCount) * 100.0;
    }

    /**
     * Generate a side-by-side diff image (reference | actual | diff-highlight).
     *
     * @return string PNG binary of the diff visualization
     */
    public static function generateDiffImage(string $actualPng, string $referencePng): string
    {
        $actual = @imagecreatefromstring($actualPng);
        $reference = @imagecreatefromstring($referencePng);

        if ($actual === false || $reference === false) {
            throw new \RuntimeException('Failed to decode PNG image for diff generation.');
        }

        $w = imagesx($actual);
        $h = imagesy($actual);
        $gap = 4;
        $totalW = $w * 3 + $gap * 2;

        $diff = imagecreatetruecolor($totalW, $h);
        if ($diff === false) {
            throw new \RuntimeException('Failed to create diff image.');
        }

        $bgColor = imagecolorallocate($diff, 30, 30, 30) ?: 0;
        imagefill($diff, 0, 0, $bgColor);

        // Copy reference (left)
        imagecopy($diff, $reference, 0, 0, 0, 0, $w, $h);

        // Copy actual (center)
        imagecopy($diff, $actual, $w + $gap, 0, 0, 0, $w, $h);

        // Generate diff highlight (right)
        $diffOffsetX = ($w + $gap) * 2;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $ac = imagecolorat($actual, $x, $y);
                $rc = imagecolorat($reference, $x, $y);

                if ($ac === $rc) {
                    // Identical: dark gray
                    $color = imagecolorallocate($diff, 40, 40, 40);
                } else {
                    // Different: red intensity proportional to difference
                    $dr = abs((($ac >> 16) & 0xFF) - (($rc >> 16) & 0xFF));
                    $dg = abs((($ac >> 8) & 0xFF) - (($rc >> 8) & 0xFF));
                    $db = abs(($ac & 0xFF) - ($rc & 0xFF));
                    $intensity = min(255, (int)(($dr + $dg + $db) / 3.0 * 4.0));
                    $color = imagecolorallocate($diff, $intensity, (int)($intensity * 0.15), (int)($intensity * 0.1));
                }

                imagesetpixel($diff, $diffOffsetX + $x, $y, $color ?: 0);
            }
        }

        imagedestroy($actual);
        imagedestroy($reference);

        ob_start();
        imagepng($diff);
        $png = ob_get_clean();
        imagedestroy($diff);

        return $png ?: '';
    }
}
