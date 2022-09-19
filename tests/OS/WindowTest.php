<?php 

namespace VISU\Tests\OS;

use VISU\Graphics\GLState;

use VISU\OS\Exception\{WindowException, UninitializedWindowException};

use VISU\OS\Window;
use VISU\OS\WindowHints;
use VISU\Tests\GLContextTestCase;


/**
 * @group glfwinit
 */
class WindowTest extends GLContextTestCase
{
    public function testWindowCreation()
    {
        $hints = new WindowHints();

        $window = new Window('Test Window', 640, 480, $hints);
        $window->initailize(new GLState);

        $this->assertInstanceOf(Window::class, $window);
        $this->assertEquals(640, $window->getWidth());
        $this->assertEquals(480, $window->getHeight());
        $this->assertEquals('Test Window', $window->getTitle());
        $this->assertGreaterThanOrEqual(640, $window->getFramebufferWidth());
        $this->assertGreaterThanOrEqual(480, $window->getFramebufferHeight());
        $this->assertGreaterThanOrEqual(640, $window->getFramebufferSizeVec()->x);
        $this->assertGreaterThanOrEqual(480, $window->getFramebufferSizeVec()->y);
        $this->assertGreaterThanOrEqual(640, $window->getSizeVec()->x);
        $this->assertGreaterThanOrEqual(480, $window->getSizeVec()->y);
    }

    public function testWindowGetAttributeWithoutInitialization()
    {
        $this->expectException(UninitializedWindowException::class);

        $window = new Window('Test Window', 640, 480);
        $window->getAttribute(GLFW_VISIBLE);
    }

    public function testWindowGetAndSetAttribute()
    {
        $window = new Window('Test Window', 640, 480);
        $window->initailize(new GLState);

        $window->setAttribute(GLFW_FOCUS_ON_SHOW, GLFW_FALSE);

        $this->assertEquals(GLFW_FALSE, $window->getAttribute(GLFW_FOCUS_ON_SHOW));

        $window->setAttribute(GLFW_FOCUS_ON_SHOW, GLFW_TRUE);

        $this->assertEquals(GLFW_TRUE, $window->getAttribute(GLFW_FOCUS_ON_SHOW));
    }

    public function testWindowSetInvalidAttribute()
    {
        $this->expectException(WindowException::class);

        $window = new Window('Test Window', 640, 480);
        $window->initailize(new GLState);

        $window->setAttribute(999999, GLFW_TRUE);
    }

    public function testShouldClose()
    {
        $window = new Window('Test Window', 640, 480);
        $window->initailize(new GLState);

        $this->assertFalse($window->shouldClose());

        $window->setShouldClose(true);

        $this->assertTrue($window->shouldClose());
    }

}