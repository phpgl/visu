<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Resource\TextureResource;

/**
 * Additional GBuffer data for PBR rendering.
 * Used alongside the standard GBufferPassData.
 */
class PBRGBufferData
{
    /**
     * Metallic (R channel) + Roughness (G channel) packed texture
     */
    public TextureResource $metallicRoughnessTexture;

    /**
     * Emissive color (RGB)
     */
    public TextureResource $emissiveTexture;
}
