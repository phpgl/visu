<?php

namespace VISU\Graphics\Font;

class BitmapFontAtlas
{
    /**
     * The font atlas texture width.
     * 
     * @var int 
     */
    public readonly int $textureWidth;

    /**
     * The font atlas texture height.
     * 
     * @var int 
     */
    public readonly int $textureHeight;

    /**
     * The font atlas texture path on disk.
     * This is optional as you might wont to generate the texture at runtime in memory..
     * 
     * @var null|string
     */
    public readonly ?string $texturePath;

    /**
     * The font atlas characters.
     * character are indexed by their unicode codepoint.
     * 
     * @var array<int, BitmapFontCharacter>
     */
    private array $characters = [];

    /**
     * Constructor
     * 
     * @param int $textureWidth The font atlas texture width.
     * @param int $textureHeight The font atlas texture height.
     * @param string|null $texturePath The font atlas texture path on disk.
     * @param array<int, BitmapFontCharacter> $characters The font atlas characters.
     */
    public function __construct(
        int $textureWidth,
        int $textureHeight,
        ?string $texturePath,
        array $characters = []
    ) {
        $this->textureWidth = $textureWidth;
        $this->textureHeight = $textureHeight;
        $this->texturePath = $texturePath;
        $this->characters = $characters;
    }

    /**
     * Adds a character to the font atlas.
     *
     * @param int $codepoint The character codepoint.
     * @param BitmapFontCharacter $character The character to add.
     */
    public function setCharacter(int $codepoint, BitmapFontCharacter $character) : void
    {
        $this->characters[$codepoint] = $character;
    }

    /**
     * Sets a character for the given string character.
     * 
     * @param string $char The character to add.
     * @param BitmapFontCharacter $character The character to add.
     */
    public function setCharacterForC(string $char, BitmapFontCharacter $character) : void
    {
        $this->setCharacter(mb_ord($char), $character);
    }

    /**
     * Returns a character from the font atlas for the given codepoint.
     * 
     * @param int $codepoint The unicode codepoint of the character.
     * @return BitmapFontCharacter|null The character or null if it does not exist.
     */
    public function getCharacter(int $codepoint) : ?BitmapFontCharacter
    {
        return $this->characters[$codepoint] ?? null;
    }
    
    /**
     * Returns a character from the font atlas for the given string character.
     * 
     * @param string $character The character.
     * @return BitmapFontCharacter|null The character or null if it does not exist.
     */
    public function getCharacterForC(string $character) : ?BitmapFontCharacter
    {
        return $this->getCharacter(\mb_ord($character));
    }

    /**
     * Returns an array of characters from the font atlas for the given string.
     * 
     * @param string $string The string.
     * @return array<BitmapFontCharacter> The characters.
     */
    public function getCharactersForString(string $string) : array
    {
        $characters = [];
        $length = \mb_strlen($string);
        for ($i = 0; $i < $length; $i++) {
            if ($character = $this->getCharacterForC($string[$i])) {
                $characters[] = $character;
            }
        }
        return $characters;
    }
}