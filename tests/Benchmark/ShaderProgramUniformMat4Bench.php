<?php

namespace VISU\Tests\Benchmark;

use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use GL\Math\Vec3;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

/**
 * @BeforeMethods("setUp")
 */
class ShaderProgramUniformMat4Bench extends GLContextBenchmark
{
    private ShaderProgram $shader;

    public function setUp() : void
    {
        parent::setUp();

        $this->shader = new ShaderProgram($this->glstate);
        $this->shader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
#version 330 core
layout (location = 0) in vec3 position;
layout (location = 1) in vec3 color;
out vec4 pcolor;

uniform mat4 somemat;

void main()
{
    pcolor = vec4(color, 1.0f);
    gl_Position = somemat * vec4(position, 1.0f);
}
GLSL));

        $this->shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
#version 330 core
out vec4 fragment_color;
in vec4 pcolor;
void main()
{
    fragment_color = pcolor;
} 
GLSL)); 

        $this->shader->link();
    }

    /**
     * @Revs(10000)
     */
    public function benchUnsafeSettersBuffer()
    {
        $buffer = new FloatBuffer();
        $buffer->pushMat4(new Mat4);

        $this->shader->use();
        $uniformLoc = $this->shader->getUniformLocation('somemat');

        for ($i = 0; $i < 1000; $i++) {
            $this->shader->unsafeSetUniformMatrix4fv("somemat", false, $buffer);
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchUnsafeSettersArray()
    {
        $matrix = [
            1.0, 0.0, 0.0, 0.0,
            0.0, 1.0, 0.0, 0.0,
            0.0, 0.0, 1.0, 0.0,
            0.0, 0.0, 0.0, 1.0,
        ];

        $this->shader->use();
        $uniformLoc = $this->shader->getUniformLocation('somemat');

        for ($i = 0; $i < 1000; $i++) {
            $this->shader->unsafeSetUniformMatrix4fv("somemat", false, $matrix);
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchDirectMat4()
    {
        $mat4 = new Mat4;

        $this->shader->use();
        $uniformLoc = $this->shader->getUniformLocation('somemat');
        for ($i = 0; $i < 1000; $i++) {
            glUniformMatrix4f($uniformLoc, false, $mat4);
        }
    }
}