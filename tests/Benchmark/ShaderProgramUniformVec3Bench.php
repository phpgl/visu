<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Vec3;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

/**
 * @BeforeMethods("setUp")
 */
class ShaderProgramUniformVec3Bench extends GLContextBenchmark
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

uniform vec3 somevec;

void main()
{
    pcolor = vec4(color, 1.0f);
    gl_Position = vec4(position * somevec, 1.0f);
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
    public function benchSafeSetters()
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->shader->setUniform3f("somevec", 1.0, 2.0, 3.0);
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchSafeSettersWrapper()
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->shader->setUniformVec3("somevec", new Vec3(1.0, 2.0, 3.0));
        }
    }

    // /**
    //  * @Revs(10000)
    //  */
    // public function benchSafeSettersWrapperInline()
    // {
    //     $v = new Vec3(1.0, 2.0, 3.0);
    //     for ($i = 0; $i < 1000; $i++) {
    //         $this->shader->setUniformVec3Inline("somevec", $v);
    //     }
    // }

    /**
     * @Revs(10000)
     */
    public function benchUnsafeSetters()
    {
        $this->shader->use();
        $this->shader->getUniformLocation('somevec');
        for ($i = 0; $i < 1000; $i++) {
            $this->shader->unsafeSetUniform3f("somevec", 1.0, 2.0, 3.0);
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchDirect()
    {
        $this->shader->use();
        $uniformLoc = $this->shader->getUniformLocation('somevec');
        for ($i = 0; $i < 1000; $i++) {
            glUniform3f($uniformLoc, 1.0, 2.0, 3.0);
        }
    }
}