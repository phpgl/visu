<?php

namespace VISU\Graphics\Heightmap;

use GL\Buffer\FloatBuffer;
use GL\Math\GLM;
use GL\Math\Mat4;
use GL\Math\Vec3;
use VISU\ECS\EntitiesInterface;
use VISU\Exception\VISUException;
use VISU\Graphics\Framebuffer;
use VISU\Graphics\GLState;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\ShaderProgram;

class GPUHeightmapRenderer
{
    private Framebuffer $framebuffer;

    /**
     * The number of pixels per unit in the heightmap
     * 
     * 1.0 means 1 pixel equals one unit in the heightmap
     * if you want to have a higher resolution heightmap, set this to a higher value
     * 
     * Example: 
     *  resolution: 1024x1024 with ppu = 1.0, will capture the are in world space of -512 to 512 on the x and z axis
     *  resolution: 1024x1024 with ppu = 2.0, will capture the are in world space of -256 to 256 on the x and z axis
     *  resolution: 2048x2048 with ppu = 2.0, will capture the are in world space of -512 to 512 on the x and z axis <- double res
     * 
     */
    public float $ppu = 1.0;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private int $width,
        private int $height,
    )
    {
        $this->initalizeFramebuffer();
    }

    private function initalizeFramebuffer() : void
    {
        $this->framebuffer = new Framebuffer($this->gl);
        $this->framebuffer->bind();
        $this->framebuffer->createRenderbufferAttachment(GL_DEPTH_COMPONENT, GL_DEPTH_ATTACHMENT, $this->width, $this->height);
        $this->framebuffer->createRenderbufferAttachment(GL_R32F, GL_COLOR_ATTACHMENT0, $this->width, $this->height);

        if (!$this->framebuffer->isValid($status, $error)) {
            throw new VISUException("Framebuffer is invalid: $error");
        }
    }

    /**
     * Captures the heightmap from the given height geometry producers
     * 
     * @param EntitiesInterface $entities
     * @param array<GPUHeightmapGeometryPassInterface> $heightGeometryProducers 
     * @return Heightmap 
     */
    public function caputreHeightmap(EntitiesInterface $entities, array $heightGeometryProducers) : Heightmap
    {
        $renderTarget = new RenderTarget($this->width, $this->height, $this->framebuffer);
        $renderTarget->preparePass();

        // create a orthographic projection matrix looking down top down on the y axis
        $projectionMatrix = new Mat4;
        $halfx = ($this->width / 2) / $this->ppu;
        $halfy = ($this->height / 2) / $this->ppu;
        $projectionMatrix->ortho(-$halfx, $halfx, -$halfy, $halfy, 1, 32000);
        $viewMatrix = new Mat4;
        $viewMatrix->translate(new Vec3(0, -16000, 0));
        $viewMatrix->rotate(GLM::radians(90), new Vec3(1, 0, 0));
        $viewMatrix->inverse();

        $vp = $projectionMatrix * $viewMatrix;

        // pass to all height geometry producers
        foreach ($heightGeometryProducers as $heightGeometryProducer) {
            $heightGeometryProducer->renderToHeightmap($entities, $renderTarget, $vp);
        }

        $heightmapData = new FloatBuffer();
        glReadPixels(0, 0, $this->width, $this->height, GL_RED, GL_FLOAT, $heightmapData);

        return new Heightmap($heightmapData, $this->width, $this->height, $this->ppu);
    }
}