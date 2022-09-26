<?php 

namespace VISU\Tests\Graphics;

use VISU\Graphics\ShaderStage;
use VISU\OS\Window;
use VISU\Tests\GLContextTestCase;

/**
 * @group glfwinit
 */
class ShaderStageTest extends GLContextTestCase
{
    private Window $window;

    public function setUp(): void
    {
        parent::setUp();
        $this->window = $this->createWindow();
    }

    public function testShaderCreation()
    {
        $shader = new ShaderStage(ShaderStage::VERTEX);
        $this->assertEquals(ShaderStage::VERTEX, $shader->getTypeFromGL());
        $this->assertFalse($shader->isDeleted());
        $this->assertFalse($shader->isCompiled());

        $shader->setSourceCode("#version 330\nvoid main() { }");
        $shader->compile();

        $this->assertGreaterThan(20, $shader->getSourceLength());
        $this->assertTrue($shader->isCompiled());
        $this->assertFalse($shader->isDeleted());
    }
}