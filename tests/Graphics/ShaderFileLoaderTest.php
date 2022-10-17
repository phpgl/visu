<?php 

namespace VISU\Tests\Graphics;

use VISU\Graphics\Exception\ShaderException;
use VISU\Graphics\Exception\ShaderInvalidIncludeException;
use VISU\Graphics\ShaderFileLoader;

class ShaderFileLoaderTest extends \PHPUnit\Framework\TestCase
{
    public function testShaderProcessIncludes()
    {
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);

        $result = $shader->processShaderIncludes(<<< 'GLSL'
#version 330 core

#include "random.glsl"

void main()
{   
}
GLSL);

        $this->assertStringContainsString('random(vec2 st)', $result);
        $this->assertStringNotContainsString('#include', $result);
    }

    public function testShaderProcessInvalidIncludes()
    {
        $this->expectException(ShaderInvalidIncludeException::class);
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);

        $result = $shader->processShaderIncludes(<<< 'GLSL'
#version 330 core
#include "does_not_exist.glsl"
GLSL);
    }

    public function testShaderProcessAdditionalIncludes()
    {
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);
        $shader->addIncludePath(PATH_TEST_RES_SHADER . '/extra_include');

        $result = $shader->processShaderIncludes(<<< 'GLSL'
#version 330 core
#include "hash.glsl"
GLSL);

        $this->assertStringContainsString('vec3 hash_color(uint x)', $result);
        $this->assertStringNotContainsString('#include', $result);

    }

    public function testShaderProcessIncludeRecursion()
    {
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);
        $shader->addIncludePath(PATH_TEST_RES_SHADER . '/extra_include');

        $result = $shader->processShaderIncludes(<<< 'GLSL'
#version 330 core
#include "recursive_include.glsl"
GLSL);

        $this->assertStringContainsString('vec3 hash_color(uint x)', $result);
        $this->assertStringContainsString('// Testing recusion', $result);
        $this->assertStringNotContainsString('#include', $result);

    }

    public function testShaderProcessDefineInjection()
    {
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);
        $macros = [
            'NUM_OF_ITERATIONS' => 42
        ];
        $result = $shader->processShader(<<< 'GLSL'
#version 330 core
for(int i = 0; i < NUM_OF_ITERATIONS; i++){
    // something
}
GLSL, $macros);

        $this->assertStringContainsString("#version 330 core\n#define NUM_OF_ITERATIONS 42", str_replace("\r", "", $result));
    }

    public function testShaderProcessDefineInjectionWithoutVersion()
    {
        $this->expectException(ShaderException::class);
        $shader = new ShaderFileLoader(PATH_TEST_RES_SHADER);
        $macros = [
            'NUM_OF_ITERATIONS' => 42
        ];
        $result = $shader->processShader(<<< 'GLSL'
foo
GLSL, $macros);
    }
}