<?php 

namespace VISU\Tests\OS;

use VISU\OS\WindowHints;

class WindowHintsTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $this->assertInstanceOf(WindowHints::class, new WindowHints);
    }
    
    public function testGetHintConstantMap()
    {
        $hints = new WindowHints;
        $map = $hints->getHintConstantMap();

        $this->assertEquals(4, $map[GLFW_CONTEXT_VERSION_MAJOR]);
        $this->assertEquals(1, $map[GLFW_CONTEXT_VERSION_MINOR]);

        // resize has no default right now
        $this->assertEquals(null, $map[GLFW_RESIZABLE]);

        $hints->resizable = true;
        $map = $hints->getHintConstantMap();

        $this->assertEquals(true, $map[GLFW_RESIZABLE]);

        // now set all values to test they are mapped correctly
        $hints->resizable = true;
        $hints->visible = true;
        $hints->decorated = true;
        $hints->focused = true;
        $hints->autoIconify = true;
        $hints->floating = true;
        $hints->maximized = true;
        $hints->centerCursor = true;
        $hints->transparentFramebuffer = true;
        $hints->focusOnShow = true;
        $hints->scaleToMonitor = true;
        $hints->samples = 42;
        $hints->refreshRate = 42;
        $hints->stereoscopic = true;
        $hints->sRGBCapable = true;
        $hints->doubleBuffer = true;
        $hints->clientAPI = 42;
        $hints->contextCreationAPI = 42;
        $hints->contextVersionMajor = 42;
        $hints->contextVersionMinor = 42;
        $hints->forwardCompatible = true;
        $hints->debugContext = true;
        $hints->profile = 42;
        $hints->robustness = 42;
        $hints->releaseBehavior = 42;
        $hints->noError = true;
        $hints->cocoaRetinaFramebuffer = true;
        $hints->cocoaframeName = "test";
        $hints->cocoaGraphicsSwitching = true;

        // assert no value is null
        $map = $hints->getHintConstantMap();
        foreach ($map as $k => $value) {
            $this->assertNotNull($value, "Value for $k is null");
        }

        // assert all values are set correctly
        $this->assertEquals(true, $map[GLFW_RESIZABLE]);
        $this->assertEquals(true, $map[GLFW_VISIBLE]);
        $this->assertEquals(true, $map[GLFW_DECORATED]);
        $this->assertEquals(true, $map[GLFW_FOCUSED]);
        $this->assertEquals(true, $map[GLFW_AUTO_ICONIFY]);
        $this->assertEquals(true, $map[GLFW_FLOATING]);
        $this->assertEquals(true, $map[GLFW_MAXIMIZED]);
        $this->assertEquals(true, $map[GLFW_CENTER_CURSOR]);
        $this->assertEquals(true, $map[GLFW_TRANSPARENT_FRAMEBUFFER]);
        $this->assertEquals(true, $map[GLFW_FOCUS_ON_SHOW]);
        $this->assertEquals(true, $map[GLFW_SCALE_TO_MONITOR]);
        $this->assertEquals(42, $map[GLFW_SAMPLES]);
        $this->assertEquals(42, $map[GLFW_REFRESH_RATE]);
        $this->assertEquals(true, $map[GLFW_STEREO]);
        $this->assertEquals(true, $map[GLFW_SRGB_CAPABLE]);
        $this->assertEquals(true, $map[GLFW_DOUBLEBUFFER]);
        $this->assertEquals(42, $map[GLFW_CLIENT_API]);
        $this->assertEquals(42, $map[GLFW_CONTEXT_CREATION_API]);
        $this->assertEquals(42, $map[GLFW_CONTEXT_VERSION_MAJOR]);
        $this->assertEquals(42, $map[GLFW_CONTEXT_VERSION_MINOR]);
        $this->assertEquals(true, $map[GLFW_OPENGL_FORWARD_COMPAT]);
        $this->assertEquals(true, $map[GLFW_OPENGL_DEBUG_CONTEXT]);
        $this->assertEquals(42, $map[GLFW_OPENGL_PROFILE]);
        $this->assertEquals(42, $map[GLFW_CONTEXT_ROBUSTNESS]);
        $this->assertEquals(42, $map[GLFW_CONTEXT_RELEASE_BEHAVIOR]);
        $this->assertEquals(true, $map[GLFW_CONTEXT_NO_ERROR]);
        $this->assertEquals(true, $map[GLFW_COCOA_RETINA_FRAMEBUFFER]);
        $this->assertEquals("test", $map[GLFW_COCOA_FRAME_NAME]);
        $this->assertEquals(true, $map[GLFW_COCOA_GRAPHICS_SWITCHING]);
    }
}
