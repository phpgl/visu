<?php

namespace VISU\Graphics\Font;

class BitmapFontCharacter
{
    /**
     * Character constructor 
     * 
     * @param int $x The X position of the character in the font texture.
     * @param int $y The Y position of the character in the font texture.
     * @param int $width The width of the character in the font texture.
     * @param int $height The height of the character in the font texture.
     * @param int $xOffset The X offset that should be applied when rendering the character.
     * @param int $yOffset The Y offset that should be applied when rendering the character.
     * @param int $xAdvance The X advance that should be applied for the next character.
     */
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        public int $xOffset,
        public int $yOffset,
        public int $xAdvance,
    ) {}
}