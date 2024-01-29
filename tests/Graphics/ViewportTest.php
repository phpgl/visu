<?php 

namespace VISU\Tests\Graphics;

use GL\Math\Vec2;
use VISU\Graphics\Viewport;

class ViewportTest extends \PHPUnit\Framework\TestCase
{
    public function testContains()
    {
        $viewportYUp = new Viewport(
            left: -50,
            right: 50,
            bottom: -50,
            top: 50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertTrue($viewportYUp->contains(new Vec2(0, 0)));
        $this->assertTrue($viewportYUp->contains(new Vec2(0, 45)));
        $this->assertTrue($viewportYUp->contains(new Vec2(0, -45)));

        $viewportYDown = new Viewport(
            left: -50,
            right: 50,
            bottom: 50,
            top: -50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertTrue($viewportYDown->contains(new Vec2(0, 0)));
        $this->assertTrue($viewportYDown->contains(new Vec2(0, 45)));
        $this->assertTrue($viewportYDown->contains(new Vec2(0, -45)));
    }

    public function testScreenSpaceToViewSpace()
    {
        $viewportYUp = new Viewport(
            left: -50,
            right: 50,
            bottom: -50,
            top: 50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertEquals(new Vec2(0, 0), $viewportYUp->screenSpaceToViewSpace(new Vec2(500, 500)));
        $this->assertEquals(new Vec2(0, 50), $viewportYUp->screenSpaceToViewSpace(new Vec2(500, 0)));
        $this->assertEquals(new Vec2(0, -50), $viewportYUp->screenSpaceToViewSpace(new Vec2(500, 1000)));

        $viewportYDown = new Viewport(
            left: -50,
            right: 50,
            bottom: 50,
            top: -50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertEquals(new Vec2(0, 0), $viewportYDown->screenSpaceToViewSpace(new Vec2(500, 500)));
        $this->assertEquals(new Vec2(0, 50), $viewportYDown->screenSpaceToViewSpace(new Vec2(500, 1000)));
        $this->assertEquals(new Vec2(0, -50), $viewportYDown->screenSpaceToViewSpace(new Vec2(500, 0)));
    }

    public function testViewSpaceToScreenSpace()
    {
        $viewportYUp = new Viewport(
            left: -50,
            right: 50,
            bottom: -50,
            top: 50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertEquals(new Vec2(500, 500), $viewportYUp->viewSpaceToScreenSpace(new Vec2(0, 0)));
        $this->assertEquals(new Vec2(500, 0), $viewportYUp->viewSpaceToScreenSpace(new Vec2(0, 50)));
        $this->assertEquals(new Vec2(500, 1000), $viewportYUp->viewSpaceToScreenSpace(new Vec2(0, -50)));

        $viewportYDown = new Viewport(
            left: -50,
            right: 50,
            bottom: 50,
            top: -50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertEquals(new Vec2(500, 500), $viewportYDown->viewSpaceToScreenSpace(new Vec2(0, 0)));
        $this->assertEquals(new Vec2(500, 1000), $viewportYDown->viewSpaceToScreenSpace(new Vec2(0, 50)));
        $this->assertEquals(new Vec2(500, 0), $viewportYDown->viewSpaceToScreenSpace(new Vec2(0, -50)));
    }
    public function testAnchorPoints()
    {
        $viewportYUp = new Viewport(
            left: -50,
            right: 50,
            bottom: -50,
            top: 50,
            screenSpaceWidth: 1000,
            screenSpaceHeight: 1000
        );

        $this->assertEquals(new Vec2(-50, 50), $viewportYUp->getTopLeft());
        $this->assertEquals(new Vec2(50, 50), $viewportYUp->getTopRight());
        $this->assertEquals(new Vec2(-50, -50), $viewportYUp->getBottomLeft());
        $this->assertEquals(new Vec2(50, -50), $viewportYUp->getBottomRight());
        $this->assertEquals(new Vec2(0, -50), $viewportYUp->getBottomCenter());
        $this->assertEquals(new Vec2(0, 50), $viewportYUp->getTopCenter());
        $this->assertEquals(new Vec2(-50, 0), $viewportYUp->getLeftCenter());
        $this->assertEquals(new Vec2(50, 0), $viewportYUp->getRightCenter());

    }
}