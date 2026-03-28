<?php

namespace VISU\Graphics;

use GL\Math\Vec3;
use GL\Math\Vec4;

class Material
{
    public string $name;

    /**
     * Base color factor (used when no albedo texture is set)
     */
    public Vec4 $albedoColor;

    /**
     * Albedo / base color texture (sRGB)
     */
    public ?Texture $albedoTexture = null;

    /**
     * Normal map texture (linear, tangent-space)
     */
    public ?Texture $normalTexture = null;

    /**
     * Metallic factor (0.0 = dielectric, 1.0 = metal)
     */
    public float $metallic = 0.0;

    /**
     * Roughness factor (0.0 = smooth/mirror, 1.0 = rough/diffuse)
     */
    public float $roughness = 1.0;

    /**
     * Combined metallic-roughness texture (B = metallic, G = roughness, glTF convention)
     */
    public ?Texture $metallicRoughnessTexture = null;

    /**
     * Ambient occlusion texture (R channel)
     */
    public ?Texture $aoTexture = null;

    /**
     * Emissive color factor
     */
    public Vec3 $emissiveColor;

    /**
     * Emissive texture (sRGB)
     */
    public ?Texture $emissiveTexture = null;

    /**
     * Alpha mode: 'OPAQUE', 'MASK', 'BLEND'
     */
    public string $alphaMode = 'OPAQUE';

    /**
     * Alpha cutoff for MASK mode
     */
    public float $alphaCutoff = 0.5;

    /**
     * Whether the material is double-sided
     */
    public bool $doubleSided = false;

    public function __construct(
        string $name = 'default',
        ?Vec4 $albedoColor = null,
        float $metallic = 0.0,
        float $roughness = 1.0,
    ) {
        $this->name = $name;
        $this->albedoColor = $albedoColor ?? new Vec4(1.0, 1.0, 1.0, 1.0);
        $this->metallic = $metallic;
        $this->roughness = $roughness;
        $this->emissiveColor = new Vec3(0.0, 0.0, 0.0);
    }

    /**
     * Returns true if the material uses any textures
     */
    public function hasTextures(): bool
    {
        return $this->albedoTexture !== null
            || $this->normalTexture !== null
            || $this->metallicRoughnessTexture !== null
            || $this->aoTexture !== null
            || $this->emissiveTexture !== null;
    }

    /**
     * Returns a bitmask of which textures are bound, for shader variant selection
     */
    public function getTextureFlags(): int
    {
        $flags = 0;
        if ($this->albedoTexture !== null) $flags |= self::FLAG_ALBEDO_MAP;
        if ($this->normalTexture !== null) $flags |= self::FLAG_NORMAL_MAP;
        if ($this->metallicRoughnessTexture !== null) $flags |= self::FLAG_METALLIC_ROUGHNESS_MAP;
        if ($this->aoTexture !== null) $flags |= self::FLAG_AO_MAP;
        if ($this->emissiveTexture !== null) $flags |= self::FLAG_EMISSIVE_MAP;
        return $flags;
    }

    const FLAG_ALBEDO_MAP = 1;
    const FLAG_NORMAL_MAP = 2;
    const FLAG_METALLIC_ROUGHNESS_MAP = 4;
    const FLAG_AO_MAP = 8;
    const FLAG_EMISSIVE_MAP = 16;
}
