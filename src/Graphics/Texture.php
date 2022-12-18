<?php 

namespace VISU\Graphics;

use GL\Buffer\BufferInterface;
use GL\Buffer\UByteBuffer;
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
     * The textures width (copied from the options)
     */
    private int $width = 0;

    /**
     * The textures height (copied from the options)
     */
    private int $height = 0;

    /**
     * The texture options object
     */
    protected ?TextureOptions $options = null;

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
     * Applies the textures minification and magnification filter parameters
     */
    private function applyFilterParameters(): void
    {
        // to avoid incomplete textures ensure that mipmaps are generated
        // when the min filter is set to mipmapped
        if ($this->options->generateMipmaps === false) {
            if ($this->options->minFilter === GL_LINEAR_MIPMAP_LINEAR ||
                $this->options->minFilter === GL_LINEAR_MIPMAP_NEAREST ||
                $this->options->minFilter === GL_NEAREST_MIPMAP_LINEAR ||
                $this->options->minFilter === GL_NEAREST_MIPMAP_NEAREST) {
                throw new TextureLoadException("Mipmapped minification filter set but mipmaps are not generated, this results in incomplete textures");
            }
        }

        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, $this->options->minFilter);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, $this->options->magFilter);
    }

    /**
     * Applies the textures wrap parameters
     */
    private function applyWrapParameters(): void
    {
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_S, $this->options->wrapS);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_T, $this->options->wrapT);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_R, $this->options->wrapR);
    }

    /**
     * Uploads a buffer of raw data, expectes the internal properties to be set correctly. 
     * 
     * @param TextureOptions $options
     * @param BufferInterface $buffer 
     * @return void 
     */
    private function uploadBuffer(TextureOptions $options, BufferInterface $buffer) : void
    {
        // store the options
        $this->options = $options;

        // copy the size from the options
        $this->width = $options->width;
        $this->height = $options->height;

        // bind 
        glBindTexture(GL_TEXTURE_2D, $this->id);

        // apply the texture paramters
        $this->applyFilterParameters();
        $this->applyWrapParameters();

        // not to sure about this.. I for sure had a reason for it 
        // in my engine but I can't remember why..
        if ($this->width % 2 == 0 && $this->height === $this->width) {
            glPixelStorei(GL_UNPACK_ALIGNMENT, 4);
        } else {
            glPixelStorei(GL_UNPACK_ALIGNMENT, 1);
        }

        // validate the internal format in the options
        if ($options->internalFormat === null) {
            throw new TextureLoadException("Internal format not set in texture options, cannot upload buffer to texture.");
        }

        // validate the source format in the options
        if ($options->dataFormat === null) {
            throw new TextureLoadException("Source format not set in texture options, cannot upload buffer to texture.");
        }

        // validate the source type in the options
        if ($options->dataType === null) {
            throw new TextureLoadException("Source type not set in texture options, cannot upload buffer to texture.");
        }

        glTexImage2D(
            GL_TEXTURE_2D,
            0,
            $this->options->internalFormat,
            $this->width,
            $this->height,
            0,
            $this->options->dataFormat,
            $this->options->dataType,
            $buffer
        );

        // generate mipmaps if requested
        if ($this->options->generateMipmaps) {
            glGenerateMipmap(GL_TEXTURE_2D);
        }
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

        if ($options->internalFormat === null) {
            $options->internalFormat = $guessedInternalFormat;
        }

        if ($options->dataFormat === null) {
            $options->dataFormat = $guessedSourceFormat;
        }

        if ($options->dataType === null) {
            $options->dataType = GL_UNSIGNED_BYTE;
        }

        $options->width = $textureData->width();
        $options->height = $textureData->height();

        $this->uploadBuffer($options, $textureData->buffer());
    }
}