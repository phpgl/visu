<?php

namespace VISU\Tests\Graphics;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\GLState;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\Exception\ShaderException;
use VISU\OS\Window;
use VISU\Tests\GLContextTestCase;

class ShaderPermutationTest extends GLContextTestCase
{
    private Window $window;
    private ShaderCollection $shaderCollection;

    public function setUp(): void
    {
        parent::setUp();
        $this->window = $this->createWindow();
        $this->shaderCollection = new ShaderCollection(new GLState(), PATH_TEST_RES_SHADER);
    }

    public function testDefinePermutations()
    {
        $this->shaderCollection->definePermutations('test', [
            'basic' => ['DEFINE_A' => 1, 'DEFINE_B' => 0],
            'advanced' => ['DEFINE_A' => 1, 'DEFINE_B' => 1, 'DEFINE_C' => 'test'],
        ]);

        $this->assertTrue($this->shaderCollection->hasPermutations('test'));
        $this->assertFalse($this->shaderCollection->hasPermutations('nonexistent'));

        $permutations = $this->shaderCollection->getAvailablePermutations('test');
        $this->assertCount(2, $permutations);
        $this->assertContains('basic', $permutations);
        $this->assertContains('advanced', $permutations);
    }

    public function testGetAvailablePermutations()
    {
        $this->shaderCollection->definePermutations('lighting', [
            'mobile' => ['MOBILE' => 1],
            'desktop' => ['MOBILE' => 0],
            'vr' => ['VR' => 1],
        ]);

        $permutations = $this->shaderCollection->getAvailablePermutations('lighting');
        $expected = ['mobile', 'desktop', 'vr'];
        
        $this->assertEquals($expected, $permutations);
        $this->assertEmpty($this->shaderCollection->getAvailablePermutations('nonexistent'));
    }

    public function testHasPermutations()
    {
        $this->assertFalse($this->shaderCollection->hasPermutations('test'));
        
        $this->shaderCollection->definePermutations('test', [
            'variant1' => ['TEST' => 1],
        ]);
        
        $this->assertTrue($this->shaderCollection->hasPermutations('test'));
        
        // empty permutations should return false
        $this->shaderCollection->definePermutations('empty', []);
        $this->assertFalse($this->shaderCollection->hasPermutations('empty'));
    }

    public function testGetPermutationWithNonexistentShader()
    {
        $this->shaderCollection->definePermutations('nonexistent', [
            'basic' => ['TEST' => 1],
        ]);

        $this->expectException(ShaderException::class);
        $this->expectExceptionMessage("Base shader 'nonexistent' does not exist and is not registered");
        
        $this->shaderCollection->getPermutation('nonexistent', 'basic');
    }

    public function testGetPermutationWithNonexistentPermutation()
    {
        $this->shaderCollection->definePermutations('test', [
            'basic' => ['TEST' => 1],
        ]);

        $this->expectException(ShaderException::class);
        $this->expectExceptionMessage("Permutation 'advanced' not defined for shader 'test'");
        
        $this->shaderCollection->getPermutation('test', 'advanced');
    }

    public function testGetPermutation()
    {
        // register a basic shader first
        $this->shaderCollection->registerFromFiles('triangle', [
            \VISU\Graphics\ShaderStage::VERTEX => PATH_TEST_RES_SHADER . '/triangle.vert.glsl',
            \VISU\Graphics\ShaderStage::FRAGMENT => PATH_TEST_RES_SHADER . '/triangle.frag.glsl',
        ]);

        $this->shaderCollection->definePermutations('triangle', [
            'a' => ['TEST' => 1],
            'b' => ['TEST' => 2],
        ]);

        // get a permutation to cache it
        $shader1 = $this->shaderCollection->getPermutation('triangle', 'a');
        $shader2 = $this->shaderCollection->getPermutation('triangle', 'b');

        $this->assertNotSame($shader1, $shader2);
    }
}