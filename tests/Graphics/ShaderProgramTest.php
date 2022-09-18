<?php 

namespace VISU\Tests\Graphics;

use VISU\Graphics\Exception\ShaderProgramLinkingException;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Tests\GLContextTestCase;

/**
 * @group glfwinit
 */
class ShaderProgramTest extends GLContextTestCase
{
    public function testShaderCreation()
    {
        $shader = new ShaderProgram($this->glstate);
        $shader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
#version 330 core
layout (location = 0) in vec3 position;
layout (location = 1) in vec3 color;
out vec4 pcolor;
void main()
{
    pcolor = vec4(color, 1.0f);
    gl_Position = vec4(position, 1.0f);
}
GLSL));

        $shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
#version 330 core
out vec4 fragment_color;
in vec4 pcolor;
void main()
{
    fragment_color = pcolor;
} 
GLSL)); 

        $shader->link();

        $this->assertTrue($shader->isLinked());
    }

    public function testShaderLinkError()
    {
        if (PHP_OS_FAMILY === "Linux") {
            $this->markTestSkipped("This test is not supported on Linux, as no link error is thrown..");
        }

        $this->expectException(ShaderProgramLinkingException::class);
        $shader = new ShaderProgram($this->glstate);
        $shader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
#version 330 core
layout (location = 0) in vec3 position;
layout (location = 1) in vec3 color;
out vec4 pcolor_wrong;
void main()
{
    pcolor_wrong = vec4(color, 1.0f);
    gl_Position = vec4(position, 1.0f);
}
GLSL));

        $shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
#version 330 core
out vec4 fragment_color;
in vec4 pcolor;
void main()
{
    fragment_color = pcolor;
} 
GLSL)); 

        $shader->link();
    }
}