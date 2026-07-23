<?php

namespace VISU\Testing;

/**
 * The result of comparing two images (an "expected" baseline against an "actual" capture).
 *
 * Holds the divergence metrics produced by the ImageComparator plus an optional
 * dimension-mismatch reason.
 */
class ImageComparison
{
    /**
     * Constructor
     *
     * @param int $width Width of the compared images in pixels
     * @param int $height Height of the compared images in pixels
     * @param float $meanError Mean absolute per-channel difference (0..1)
     * @param float $badPixelRatio Fraction of pixels exceeding the pixel tolerance (0..1)
     * @param int $maxChannelDiff Largest single-channel difference (0..255)
     * @param string|null $dimensionMismatch Reason string when the images are not comparable, null otherwise
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly float $meanError,
        public readonly float $badPixelRatio,
        public readonly int $maxChannelDiff,
        public readonly ?string $dimensionMismatch = null,
    )
    {
    }

    /**
     * Creates a comparison result for two byte-identical images
     */
    public static function identical(int $width, int $height) : self
    {
        return new self($width, $height, 0.0, 0.0, 0, null);
    }

    /**
     * Creates a comparison result for two images that cannot be compared (differing size or channels)
     */
    public static function dimensionMismatch(string $reason) : self
    {
        return new self(0, 0, 1.0, 1.0, 255, $reason);
    }

    /**
     * Returns true if the divergence is within the given thresholds
     *
     * A dimension mismatch never passes. Otherwise both the bad-pixel ratio and (when a mean
     * error threshold is given) the mean error must stay at or below their limits.
     */
    public function passes(float $maxBadPixelRatio, ?float $maxMeanError = null) : bool
    {
        if ($this->dimensionMismatch !== null) {
            return false;
        }

        if ($this->badPixelRatio > $maxBadPixelRatio) {
            return false;
        }

        if ($maxMeanError !== null && $this->meanError > $maxMeanError) {
            return false;
        }

        return true;
    }

    /**
     * Returns a short human readable summary of the metrics, useful for test failure messages
     */
    public function summary() : string
    {
        if ($this->dimensionMismatch !== null) {
            return "dimension mismatch: {$this->dimensionMismatch}";
        }

        return sprintf(
            'bad %.3f%%  mean %.3f%%  maxChannelDiff %d',
            $this->badPixelRatio * 100.0,
            $this->meanError * 100.0,
            $this->maxChannelDiff
        );
    }
}
