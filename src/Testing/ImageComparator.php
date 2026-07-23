<?php

namespace VISU\Testing;

use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;

/**
 * Compares two images pixel by pixel and produces divergence metrics.
 *
 * Per-channel absolute difference (not perceptual). A per-pixel tolerance absorbs antialiasing /
 * sRGB rounding jitter along edges; the bad-pixel ratio and mean error decide whether it matches.
 */
class ImageComparator
{
    /**
     * Constructor
     *
     * @param int $pixelTolerance Maximum per-channel difference (0..255) a pixel may have before
     *                            it counts as a "bad" pixel.
     * @param float $maxBadPixelRatio Maximum allowed fraction of bad pixels (0..1).
     * @param float $maxMeanError Maximum allowed mean absolute per-channel difference (0..1).
     */
    public function __construct(
        public int $pixelTolerance = 12,
        public float $maxBadPixelRatio = 0.005,
        public float $maxMeanError = 0.02,
    )
    {
    }

    /**
     * Compares an expected baseline image against an actual capture
     *
     * Both images must share dimensions and channel count, otherwise a dimension-mismatch result
     * is returned. Byte-identical images take a fast path.
     */
    public function compare(Texture2D $expected, Texture2D $actual) : ImageComparison
    {
        if ($expected->width() !== $actual->width() || $expected->height() !== $actual->height()) {
            return ImageComparison::dimensionMismatch(sprintf(
                'expected %dx%d, got %dx%d',
                $expected->width(), $expected->height(), $actual->width(), $actual->height()
            ));
        }

        if ($expected->channels() !== $actual->channels()) {
            return ImageComparison::dimensionMismatch(sprintf(
                'expected %d channels, got %d',
                $expected->channels(), $actual->channels()
            ));
        }

        $width = $expected->width();
        $height = $expected->height();
        $channels = $expected->channels();

        $e = $expected->buffer()->dump();
        $a = $actual->buffer()->dump();

        // fast path: byte identical
        if ($e === $a) {
            return ImageComparison::identical($width, $height);
        }

        $length = strlen($e);

        $sum = 0;
        $maxChannelDiff = 0;
        $badPixelCount = 0;

        // identical bytes become \0 in the xor, allowing us to skip long identical runs at C speed
        // via strspn and only inspect the bytes that actually differ in PHP.
        $xor = $e ^ $a;
        $i = strspn($xor, "\0");

        $currentPixel = -1;
        $currentPixelMax = 0;

        while ($i < $length) {
            $diff = abs(ord($e[$i]) - ord($a[$i]));
            $sum += $diff;
            if ($diff > $maxChannelDiff) {
                $maxChannelDiff = $diff;
            }

            // group channels into pixels, counting a pixel as bad once its largest channel diff
            // exceeds the tolerance
            $pixel = intdiv($i, $channels);
            if ($pixel !== $currentPixel) {
                if ($currentPixel !== -1 && $currentPixelMax > $this->pixelTolerance) {
                    $badPixelCount++;
                }
                $currentPixel = $pixel;
                $currentPixelMax = $diff;
            } elseif ($diff > $currentPixelMax) {
                $currentPixelMax = $diff;
            }

            $i++;
            // skip forward to the next differing byte
            $i += strspn($xor, "\0", $i);
        }

        // account for the very last touched pixel
        if ($currentPixel !== -1 && $currentPixelMax > $this->pixelTolerance) {
            $badPixelCount++;
        }

        $meanError = $length > 0 ? $sum / ($length * 255) : 0.0;
        $badPixelRatio = ($width * $height) > 0 ? $badPixelCount / ($width * $height) : 0.0;

        return new ImageComparison($width, $height, $meanError, $badPixelRatio, $maxChannelDiff);
    }

    /**
     * Writes a grayscale divergence visualization for two images to disk
     *
     * Each output pixel is the largest channel difference of the corresponding input pixels,
     * amplified 5x and clamped, so that small differences remain visible (brighter = more
     * divergence). Does nothing if the images differ in size.
     */
    public function writeDiffImage(Texture2D $expected, Texture2D $actual, string $path) : void
    {
        if ($expected->width() !== $actual->width() || $expected->height() !== $actual->height()) {
            return;
        }
        if ($expected->channels() !== $actual->channels()) {
            return;
        }

        $width = $expected->width();
        $height = $expected->height();
        $channels = $expected->channels();

        $e = $expected->buffer()->dump();
        $a = $actual->buffer()->dump();
        $length = strlen($e);

        $diffBuffer = new UByteBuffer();

        for ($pixel = 0; $pixel * $channels < $length; $pixel++) {
            $base = $pixel * $channels;

            $pixelMax = 0;
            for ($c = 0; $c < $channels; $c++) {
                $diff = abs(ord($e[$base + $c]) - ord($a[$base + $c]));
                if ($diff > $pixelMax) {
                    $pixelMax = $diff;
                }
            }

            // amplify small differences so they are actually visible
            $value = min(255, $pixelMax * 5);
            $diffBuffer->push($value);
            $diffBuffer->push($value);
            $diffBuffer->push($value);
        }

        Texture2D::fromBuffer($width, $height, $diffBuffer, Texture2D::CHANNEL_RGB)->writePNG($path);
    }
}
