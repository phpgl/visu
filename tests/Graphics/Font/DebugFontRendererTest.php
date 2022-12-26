<?php 

namespace VISU\Tests\Graphics\Font;

use VISU\Graphics\Font\BitmapFontAtlas;
use VISU\Graphics\Font\DebugFontRenderer;
use VISU\Graphics\Rendering\Renderer\DebugOverlayTextRenderer;

class DebugFontRendererTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadingDebugFontAtlas()
    {
        $atlas = DebugOverlayTextRenderer::loadDebugFontAtlas();
        $this->assertInstanceOf(BitmapFontAtlas::class, $atlas);

        // test reading some charactes props from the atlas.
        $this->assertEquals(204, $atlas->getCharacter(37)->x);
        $this->assertEquals(48, $atlas->getCharacter(37)->y);
        $this->assertEquals(7, $atlas->getCharacter(37)->width);
        $this->assertEquals(11, $atlas->getCharacter(37)->height);
        $this->assertEquals(0, $atlas->getCharacter(37)->xOffset);
        $this->assertEquals(0, $atlas->getCharacter(37)->yOffset);
        $this->assertEquals(6, $atlas->getCharacter(37)->xAdvance);
    }
}