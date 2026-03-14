<?php

namespace VISU\Component;

use VISU\Graphics\Terrain\HeightmapTerrain;
use VISU\Graphics\Texture;

class TerrainComponent
{
    /**
     * The heightmap terrain instance
     */
    public ?HeightmapTerrain $terrain = null;

    /**
     * Blend map texture (RGBA: each channel = weight for one terrain texture)
     */
    public ?Texture $blendMap = null;

    /**
     * Terrain layer textures (up to 4, mapped to blend map RGBA channels)
     * @var array<Texture|null>
     */
    public array $layerTextures = [null, null, null, null];

    /**
     * UV tiling scale for each layer (how many times textures repeat across terrain)
     * @var array<float>
     */
    public array $layerTiling = [10.0, 10.0, 10.0, 10.0];

    /**
     * Whether this terrain casts shadows
     */
    public bool $castsShadows = true;
}
