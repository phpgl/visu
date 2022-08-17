<?php 

namespace VISU\Tests\Graphics;

use VISU\Graphics\Shader;
use VISU\Tests\GLContextTestCase;

/**
 * @group glfwinit
 */
class ShaderTest extends GLContextTestCase
{
    public function testShaderCreation()
    {
        $shader = new Shader(Shader::VERTEX);
        $this->assertEquals(Shader::VERTEX, $shader->getType());
        $this->assertFalse($shader->isDeleted());
        $this->assertFalse($shader->isCompiled());

        $shader->setSourceCode("#version 330\nvoid main() { }");
        $shader->compile();

        $this->assertEquals($shader->getSourceLength(), 30);
        $this->assertTrue($shader->isCompiled());

        var_dump($shader);
    }
}