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
    public ?int $internalFormat = NULL;

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
    public ?int $format = NULL;

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
    public ?int $type = NULL;
}