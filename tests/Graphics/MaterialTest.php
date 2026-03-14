<?php

namespace Tests\Graphics;

use GL\Math\Vec3;
use GL\Math\Vec4;
use PHPUnit\Framework\TestCase;
use VISU\Graphics\Material;

class MaterialTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $mat = new Material();
        $this->assertEquals('default', $mat->name);
        $this->assertEquals(0.0, $mat->metallic);
        $this->assertEquals(1.0, $mat->roughness);
        $this->assertEquals(1.0, $mat->albedoColor->x);
        $this->assertEquals(1.0, $mat->albedoColor->y);
        $this->assertEquals(1.0, $mat->albedoColor->z);
        $this->assertEquals(1.0, $mat->albedoColor->w);
        $this->assertEquals(0.0, $mat->emissiveColor->x);
    }

    public function testCustomConstructor(): void
    {
        $mat = new Material(
            name: 'gold',
            albedoColor: new Vec4(1.0, 0.766, 0.336, 1.0),
            metallic: 1.0,
            roughness: 0.3,
        );
        $this->assertEquals('gold', $mat->name);
        $this->assertEquals(1.0, $mat->metallic);
        $this->assertEquals(0.3, $mat->roughness);
        $this->assertEqualsWithDelta(0.766, $mat->albedoColor->y, 0.001);
    }

    public function testHasTexturesReturnsFalseByDefault(): void
    {
        $mat = new Material();
        $this->assertFalse($mat->hasTextures());
    }

    public function testGetTextureFlagsDefault(): void
    {
        $mat = new Material();
        $this->assertEquals(0, $mat->getTextureFlags());
    }

    public function testAlphaMode(): void
    {
        $mat = new Material();
        $this->assertEquals('OPAQUE', $mat->alphaMode);
        $this->assertEquals(0.5, $mat->alphaCutoff);
        $this->assertFalse($mat->doubleSided);
    }

    public function testFlagConstants(): void
    {
        $this->assertEquals(1, Material::FLAG_ALBEDO_MAP);
        $this->assertEquals(2, Material::FLAG_NORMAL_MAP);
        $this->assertEquals(4, Material::FLAG_METALLIC_ROUGHNESS_MAP);
        $this->assertEquals(8, Material::FLAG_AO_MAP);
        $this->assertEquals(16, Material::FLAG_EMISSIVE_MAP);
    }

    public function testEmissiveColorDefault(): void
    {
        $mat = new Material();
        $this->assertEquals(0.0, $mat->emissiveColor->x);
        $this->assertEquals(0.0, $mat->emissiveColor->y);
        $this->assertEquals(0.0, $mat->emissiveColor->z);
    }

    public function testEmissiveColorCustom(): void
    {
        $mat = new Material();
        $mat->emissiveColor = new Vec3(1.0, 0.5, 0.0);
        $this->assertEquals(1.0, $mat->emissiveColor->x);
        $this->assertEquals(0.5, $mat->emissiveColor->y);
    }
}
