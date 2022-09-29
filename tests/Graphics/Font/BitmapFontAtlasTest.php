<?php 

namespace VISU\Tests\Graphics\Font;

use VISU\Graphics\Font\BitmapFontAtlas;
use VISU\Graphics\Font\BitmapFontCharacter;

class BitmapFontAtlasTest extends \PHPUnit\Framework\TestCase
{
    public function testProperties()
    {
        $atlas = new BitmapFontAtlas(512, 512, null);  

        $this->assertEquals(512, $atlas->textureWidth);
        $this->assertEquals(512, $atlas->textureHeight);
        $this->assertNull($atlas->texturePath);
    }

    public function testGetCharacterViaCodepoint()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            42 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
        ]);  

        $this->assertEquals(1, $atlas->getCharacter(42)->x);
        $this->assertEquals(2, $atlas->getCharacter(42)->y);
    }

    public function testGetCharacterViaCharacter()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            42 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
        ]);  

        $this->assertEquals(1, $atlas->getCharacterForC('*')->x);
        $this->assertEquals(2, $atlas->getCharacterForC('*')->y);
    }

    public function testGetCharacterViaCharacterWithInvalidCharacter()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            42 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
        ]);  

        $this->assertNull($atlas->getCharacterForC('a'));
    }

    public function testGetCharactersForString()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            50 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
            52 => new BitmapFontCharacter(11, 12, 13, 14, 15, 16, 17),
        ]);  

        $characters = $atlas->getCharactersForString('42');

        $this->assertCount(2, $characters);
        $this->assertEquals(11, $characters[0]->x);
        $this->assertEquals(12, $characters[0]->y);
        $this->assertEquals(1, $characters[1]->x);
        $this->assertEquals(2, $characters[1]->y);
    }

    public function testGetCharactersForStringWithInvalidCharacter()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            50 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
            52 => new BitmapFontCharacter(11, 12, 13, 14, 15, 16, 17),
        ]);  

        $characters = $atlas->getCharactersForString('4a');

        $this->assertCount(1, $characters);
        $this->assertEquals(11, $characters[0]->x);
        $this->assertEquals(12, $characters[0]->y);
    }

    public function testGetCharactersForStringWithEmptyString()
    {
        $atlas = new BitmapFontAtlas(512, 512, null, [
            50 => new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7),
            52 => new BitmapFontCharacter(11, 12, 13, 14, 15, 16, 17),
        ]);  

        $characters = $atlas->getCharactersForString('');

        $this->assertCount(0, $characters);
    }

    public function testSetCharaterViaCodepoint()
    {
        $atlas = new BitmapFontAtlas(512, 512, null);  

        $atlas->setCharacter(42, new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7));

        $this->assertEquals(1, $atlas->getCharacter(42)->x);
        $this->assertEquals(2, $atlas->getCharacter(42)->y);
    }

    public function testSetCharaterViaCharacter()
    {
        $atlas = new BitmapFontAtlas(512, 512, null);  

        $atlas->setCharacterForC('*', new BitmapFontCharacter(1, 2, 3, 4, 5, 6, 7));

        $this->assertEquals(1, $atlas->getCharacter(42)->x);
        $this->assertEquals(2, $atlas->getCharacter(42)->y);
    }
}