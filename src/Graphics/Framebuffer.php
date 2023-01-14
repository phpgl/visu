<?php

namespace VISU\Graphics;

class Framebuffer extends AbstractFramebuffer
{
    /**
     * An array of renderbuffet attachments
     * 
     * @var array<int, int> maps: attachment -> render buffer object
     */
    private array $renderbufferAttachments = [];

    /**
     * Creates a new framebuffer object
     * 
     * @param GLState $gl
     */
    final public function __construct(GLState $gl)
    {
        glGenFramebuffers(1, $id);
        parent::__construct($gl, $id);
    }

    /**
     * Destructor 
     */
    final public function __destruct()
    {
        glDeleteFramebuffers(1, $this->id);
    }

    /**
     * Creates a render buffer attachent
     */
    public function createRenderbufferAttachment(int $format, int $attachment, int $width, int $height) : void
    {
        // delete and remove an old renderbuffer attachment when it already exists
        if (isset($this->renderbufferAttachments[$attachment])) {
            glDeleteRenderbuffers(1, $this->renderbufferAttachments[$attachment]);
            unset($this->renderbufferAttachments[$attachment]);
        }   

        glGenRenderbuffers(1, $rbo);
        glBindRenderbuffer(GL_RENDERBUFFER, $rbo);
        glRenderbufferStorage(GL_RENDERBUFFER, $format, $width, $height);
        glFramebufferRenderbuffer(GL_FRAMEBUFFER, $attachment, GL_RENDERBUFFER, $rbo);

        $this->renderbufferAttachments[$attachment] = $rbo;
    }
}
