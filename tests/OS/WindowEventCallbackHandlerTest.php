<?php 

namespace VISU\Tests\OS;

use VISU\OS\Window;
use VISU\OS\WindowEventCallbackHandler;

class WindowEventCallbackHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testWindowKey()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowKey(function($window, $key, $scancode, $action, $mods) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $key);
            $this->assertEquals(3, $scancode);
            $this->assertEquals(4, $action);
            $this->assertEquals(5, $mods);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowKey($window, 2, 3, 4, 5);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowChar()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowChar(function($window, $codepoint) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $codepoint);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowChar($window, 2);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowCharMods()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowCharMods(function($window, $codepoint, $mods) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $codepoint);
            $this->assertEquals(3, $mods);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowCharMods($window, 2, 3);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowMouseButton()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowMouseButton(function($window, $button, $action, $mods) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $button);
            $this->assertEquals(3, $action);
            $this->assertEquals(4, $mods);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowMouseButton($window, 2, 3, 4);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowCursorPos()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowCursorPos(function($window, $xpos, $ypos) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $xpos);
            $this->assertEquals(3, $ypos);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowCursorPos($window, 2, 3);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowCursorEnter()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowCursorEnter(function($window, $entered) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $entered);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowCursorEnter($window, 2);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowScroll()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowScroll(function($window, $xoffset, $yoffset) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(2, $xoffset);
            $this->assertEquals(3, $yoffset);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowScroll($window, 2, 3);

        $this->assertTrue($callbackCalled);
    }

    public function testWindowDrop()
    {
        $callbackCalled = false;
    
        $handler = new WindowEventCallbackHandler();
        $handler->onWindowDrop(function($window, $paths) use(&$callbackCalled) {
            $this->assertInstanceOf(Window::class, $window);
            $this->assertEquals(['foo', 'bar'], $paths);

            $callbackCalled = true;
        });

        $this->assertFalse($callbackCalled);

        $window = $this->createMock(Window::class);
        $handler->handleWindowDrop($window, ['foo', 'bar']);

        $this->assertTrue($callbackCalled);
    }
}