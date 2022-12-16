<?php 

namespace VISU\Graphics;

use GL\Math\Vec2;
use GL\Texture\Texture2D;
use VISU\Graphics\Exception\TextureLoadException;

class Texture
{
    /**
     * The GL texture id / handle
     */
    public readonly int $id;

    /**
     * The textures width
     */
    protected int $width = 0;

    /**
     * The textures height
     */
    protected int $height = 0;

    /**
     * The textures internal format (This is the format the texture is stored in on the GPU)
     */
    protected int $internalFormat = GL_NONE;

    /**
     * The textures source format (This is the format of the texture data)
     */
    protected int $sourceFormat = GL_NONE;

    /**
     * The textures source type (This is the type of the texture data)
     */
    protected int $sourceType = GL_NONE;

    /**
     * Constructor
     * 
     * @param string $name   A unique name for the texture, used for debugging and resource management
     * @return void 
     */
    public function __construct(
        public string $name
    )
    {
        glGenTextures(1, $id);
        $this->id = $id;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        glDeleteTextures(1, $this->id);
    }

    /**
     * Returns the textures width
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Returns the textures height
     */
    public function height(): int
    {
        return $this->height;
    }

    /**
     * Returns the textures width and height as a floating point vector
     */
    public function size(): Vec2
    {
        return new Vec2($this->width, $this->height);
    }

    /**
     * Loads an image from disk into the texture
     * 
     * @param string $path Full absolute path to the image file
     * 
     * @throws TextureLoadException 
     *  
     * @return void 
     */
    public function loadFromFile(string $path, ?TextureOptions $options = null)
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new TextureLoadException("Image file not found or not accessable: {$path}");
        }

        if ($options === null) {
            $options = new TextureOptions();
        }
        
        $textureData = Texture2D::fromDisk($path);

        switch ($textureData->channels()) {
            case 4:
                $guessedInternalFormat = $options->isSRGB ? GL_SRGB_ALPHA : GL_RGBA;
                $guessedSourceFormat = GL_RGBA;
                break;
            case 3:
                $guessedInternalFormat = $options->isSRGB ? GL_SRGB : GL_RGB;
                $guessedSourceFormat = GL_RGB;
                break;
            case 2:
                $guessedInternalFormat = GL_RG;
                $guessedSourceFormat = GL_RG;
                break;
            case 1:
                $guessedInternalFormat = GL_RED;
                $guessedSourceFormat = GL_RED;
                break;
            default:
                throw new TextureLoadException("Unsupported number of channels: {$textureData->channels()}");
        } 

        $this->width = $textureData->width();
        $this->height = $textureData->height();

        if ($options->internalFormat !== null) {
            $this->internalFormat = $options->internalFormat;
        } else {
            $this->internalFormat = $guessedInternalFormat;
        }

        if ($options->format !== null) {
            $this->sourceFormat = $options->format;
        } else {
            $this->sourceFormat = $guessedSourceFormat;
        }

        if ($options->type !== null) {
            $this->sourceType = $options->type;
        } else {
            $this->sourceType = GL_UNSIGNED_BYTE;
        }
        
        glBindTexture(GL_TEXTURE_2D, $this->id);

        // not to sure about this.. I for sure had a reason for it 
        // in my engine but I can't remember why..
        if ($this->width % 2 == 0 && $this->height === $this->width) {
            glPixelStorei(GL_UNPACK_ALIGNMENT, 4);
        } else {
            glPixelStorei(GL_UNPACK_ALIGNMENT, 1);
        }

        glTexImage2D(
            GL_TEXTURE_2D,
            0,
            $this->internalFormat,
            $this->width,
            $this->height,
            0,
            $this->sourceFormat,
            $this->sourceType,
            $textureData->buffer()
        );
    }
}