<?php

namespace Tests\Component;

use GL\Math\Vec4;
use PHPUnit\Framework\TestCase;
use VISU\Component\MeshRendererComponent;
use VISU\Graphics\Material;

class MeshRendererComponentTest extends TestCase
{
    public function testConstruction(): void
    {
        $renderer = new MeshRendererComponent('cube.glb');
        $this->assertEquals('cube.glb', $renderer->modelIdentifier);
        $this->assertNull($renderer->materialOverride);
        $this->assertTrue($renderer->castsShadows);
        $this->assertTrue($renderer->receivesShadows);
    }

    public function testMaterialOverride(): void
    {
        $renderer = new MeshRendererComponent('cube.glb');
        $override = new Material('red', new Vec4(1, 0, 0, 1), 0.0, 0.5);
        $renderer->materialOverride = $override;

        $this->assertSame($override, $renderer->materialOverride);
        $this->assertEquals('red', $renderer->materialOverride->name);
    }

    public function testShadowFlags(): void
    {
        $renderer = new MeshRendererComponent('mesh');
        $renderer->castsShadows = false;
        $renderer->receivesShadows = false;
        $this->assertFalse($renderer->castsShadows);
        $this->assertFalse($renderer->receivesShadows);
    }
}
