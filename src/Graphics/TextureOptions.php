<?php 

namespace VISU\Graphics;

class TextureOptions
{
    /**
     * Is SRGB
     * This is a hint so that the texture is loaded as sRGB
     */
    public bool $isSRGB = false;

    /**
     * Should generate mipmaps
     * This is a hint so that the texture mipmaps are generated on the fly when the texture is loaded
     */
    public bool $generateMipmaps = true;

    /**
     * Internal texture format 
     * This is the format the texture is stored in on the GPU
     *
     * Possible values (Base):
     *  - GL_RGB
     *  - GL_RGBA
     *  - GL_RED
     *  - GL_RG
     *  - GL_DEPTH_COMPONENT
     *  - GL_DEPTH_STENCIL
     * 
     * Possible values (SRGB):
     *  - GL_SRGB
     *  - GL_SRGB_ALPHA
     *  - GL_SRGB8
     *  - etc ...
     * 
     * Possible values (Sized):
     *  - GL_R8
     *  - GL_R16
     *  - GL_R16F
     *  - GL_R32F
     *  - GL_RG8
     *  - GL_RG16
     *  - etc ...
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexImage2D.xhtml
     */
    public ?int $internalFormat = null;

    /**
     * Texture format
     * This is the format of the texture data
     *
     * Possible values:
     *  - GL_RGB
     *  - GL_RGBA
     *  - GL_RED
     *  - GL_RG
     *  - GL_BGRA
     *  - GL_BGR_INTEGER
     *  - GL_DEPTH_COMPONENT
     *  - GL_DEPTH_STENCIL
     *  - etc ...
     *  
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexImage2D.xhtml
     */
    public ?int $dataFormat = null;

    /**
     * Texture type
     * This is the type of the texture data
     *
     * Possible values:
     *  - GL_BYTE
     *  - GL_UNSIGNED_BYTE
     *  - GL_UNSIGNED_SHORT
     *  - GL_UNSIGNED_INT
     *  - GL_FLOAT
     *  - etc ...
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexImage2D.xhtml
     */
    public ?int $dataType = null;

    /**
     * The textures width (This is only used when the texture is created from a buffer)
     */
    public ?int $width = null;

    /**
     * The textures height (This is only used when the texture is created from a buffer)
     */
    public ?int $height = null;

    /**
     * The textures minification filter
     * 
     * Possible values:
     * - GL_NEAREST
     * - GL_LINEAR
     * - GL_NEAREST_MIPMAP_NEAREST
     * - GL_LINEAR_MIPMAP_NEAREST
     * - GL_NEAREST_MIPMAP_LINEAR
     * - GL_LINEAR_MIPMAP_LINEAR
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexParameter.xhtml
     */
    public int $minFilter = GL_NEAREST_MIPMAP_LINEAR;

    /**
     * The textures magnification filter
     * 
     * Possible values:
     * - GL_NEAREST
     * - GL_LINEAR
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexParameter.xhtml
     */
    public int $magFilter = GL_LINEAR;

    /**
     * The textures wrap mode in the s direction
     * 
     * Possible values:
     * - GL_REPEAT
     * - GL_MIRRORED_REPEAT
     * - GL_CLAMP_TO_EDGE
     * - GL_CLAMP_TO_BORDER
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexParameter.xhtml
     */
    public int $wrapS = GL_REPEAT;

    /**
     * The textures wrap mode in the t direction
     * 
     * Possible values:
     * - GL_REPEAT
     * - GL_MIRRORED_REPEAT
     * - GL_CLAMP_TO_EDGE
     * - GL_CLAMP_TO_BORDER
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexParameter.xhtml
     */
    public int $wrapT = GL_REPEAT;

    /**
     * The textures wrap mode in the r direction
     * 
     * Possible values:
     * - GL_REPEAT
     * - GL_MIRRORED_REPEAT
     * - GL_CLAMP_TO_EDGE
     * - GL_CLAMP_TO_BORDER
     * 
     * @see https://registry.khronos.org/OpenGL-Refpages/gl4/html/glTexParameter.xhtml
     */
    public int $wrapR = GL_REPEAT;
}