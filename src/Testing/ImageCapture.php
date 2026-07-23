<?php

namespace VISU\Testing;

use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;
use VISU\Graphics\Texture;

/**
 * Reads rendered GPU images back into CPU-side Texture2D objects.
 */
class ImageCapture
{
    /**
     * Reads a color attachment texture back into an RGB Texture2D
     *
     * @param Texture $texture The color attachment texture to read (bound to GL_TEXTURE_2D)
     */
    public static function fromColorTexture(Texture $texture) : Texture2D
    {
        $buffer = new UByteBuffer();

        $texture->bind();

        // tightly pack rows; the default GL_PACK_ALIGNMENT (4) pads rows when width*3 isn't a
        // multiple of 4 (e.g. width 250 -> 750 bytes/row) which would skew the captured image
        glPixelStorei(GL_PACK_ALIGNMENT, 1);
        glGetTexImage(GL_TEXTURE_2D, 0, GL_RGB, GL_UNSIGNED_BYTE, $buffer);

        return Texture2D::fromBuffer($texture->width(), $texture->height(), $buffer, Texture2D::CHANNEL_RGB);
    }
}
