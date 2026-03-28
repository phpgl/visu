<?php

namespace VISU\Graphics\Terrain;

class TerrainData
{
    /**
     * Height values in row-major order (Z-major, then X)
     * @var array<float>
     */
    private array $heights;

    /**
     * Width in vertices (X axis)
     */
    public readonly int $width;

    /**
     * Depth in vertices (Z axis)
     */
    public readonly int $depth;

    /**
     * World-space size on X axis
     */
    public readonly float $sizeX;

    /**
     * World-space size on Z axis
     */
    public readonly float $sizeZ;

    /**
     * Maximum height scale
     */
    public readonly float $heightScale;

    /**
     * @param array<float> $heights Row-major height values (depth rows of width values)
     */
    public function __construct(
        array $heights,
        int $width,
        int $depth,
        float $sizeX = 100.0,
        float $sizeZ = 100.0,
        float $heightScale = 20.0,
    ) {
        $this->heights = $heights;
        $this->width = $width;
        $this->depth = $depth;
        $this->sizeX = $sizeX;
        $this->sizeZ = $sizeZ;
        $this->heightScale = $heightScale;
    }

    /**
     * Returns the height at grid coordinates (clamped to bounds)
     */
    public function getHeight(int $x, int $z): float
    {
        $x = max(0, min($x, $this->width - 1));
        $z = max(0, min($z, $this->depth - 1));
        return $this->heights[$z * $this->width + $x] * $this->heightScale;
    }

    /**
     * Returns the interpolated height at a world-space position.
     * The terrain is centered at origin, spanning [-sizeX/2, sizeX/2] x [-sizeZ/2, sizeZ/2].
     */
    public function getHeightAtWorld(float $worldX, float $worldZ): float
    {
        // convert world position to grid-space [0, width-1] x [0, depth-1]
        $gx = (($worldX + $this->sizeX * 0.5) / $this->sizeX) * ($this->width - 1);
        $gz = (($worldZ + $this->sizeZ * 0.5) / $this->sizeZ) * ($this->depth - 1);

        $x0 = (int)floor($gx);
        $z0 = (int)floor($gz);
        $x1 = min($x0 + 1, $this->width - 1);
        $z1 = min($z0 + 1, $this->depth - 1);
        $x0 = max(0, $x0);
        $z0 = max(0, $z0);

        $fx = $gx - $x0;
        $fz = $gz - $z0;

        // bilinear interpolation
        $h00 = $this->getHeight($x0, $z0);
        $h10 = $this->getHeight($x1, $z0);
        $h01 = $this->getHeight($x0, $z1);
        $h11 = $this->getHeight($x1, $z1);

        $h0 = $h00 + ($h10 - $h00) * $fx;
        $h1 = $h01 + ($h11 - $h01) * $fx;

        return $h0 + ($h1 - $h0) * $fz;
    }

    /**
     * Generates a flat terrain (all heights = 0)
     */
    public static function flat(int $width, int $depth, float $sizeX = 100.0, float $sizeZ = 100.0): self
    {
        return new self(array_fill(0, $width * $depth, 0.0), $width, $depth, $sizeX, $sizeZ);
    }

    /**
     * Generates terrain from a grayscale image file.
     * Pixel brightness (0-255) is mapped to [0, 1] height.
     */
    public static function fromHeightmapFile(
        string $path,
        float $sizeX = 100.0,
        float $sizeZ = 100.0,
        float $heightScale = 20.0,
    ): self {
        if (!file_exists($path)) {
            throw new \RuntimeException("Heightmap file not found: {$path}");
        }

        $info = getimagesize($path);
        if ($info === false) {
            throw new \RuntimeException("Failed to read heightmap image: {$path}");
        }

        $width = $info[0];
        $height = $info[1];

        $image = match ($info[2]) {
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_BMP => imagecreatefrombmp($path),
            default => throw new \RuntimeException("Unsupported heightmap format: {$path}"),
        };

        if ($image === false) {
            throw new \RuntimeException("Failed to load heightmap image: {$path}");
        }

        $heights = [];
        for ($z = 0; $z < $height; $z++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $z);
                if ($rgb === false) {
                    $heights[] = 0.0;
                    continue;
                }
                // use red channel (or grayscale luminance)
                $r = ($rgb >> 16) & 0xFF;
                $heights[] = $r / 255.0;
            }
        }

        imagedestroy($image);

        return new self($heights, $width, $height, $sizeX, $sizeZ, $heightScale);
    }

    /**
     * Returns all raw height values.
     * @return array<float>
     */
    public function getRawHeights(): array
    {
        return $this->heights;
    }
}
