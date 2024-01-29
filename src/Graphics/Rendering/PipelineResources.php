<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Exception\PipelineResourceException;
use VISU\Graphics\Framebuffer;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class PipelineResources
{
    /**
     * Internal array of render targets
     * 
     * @var array<string, RenderTarget>
     */
    private array $renderTargets = [];

    /**
     * Currently bound render target
     * 
     * @var RenderTarget|null
     */
    private ?RenderTarget $activeRenderTarget = null;

    /**
     * Internal array of textures
     * 
     * @var array<string, Texture>
     */
    private array $textures = [];

    /**
     * Holder of mixed generic static resources 
     * 
     * @var array<string, mixed>
     */
    private array $staticStorage = [];

    /**
     * The current ticke index the resources are accessed with
     * 
     * @var int
     */
    private int $tickIndex = 0;
    
    /**
     * A map storing the tick each resource was last accessed
     * 
     * @var array<string, int>
     */
    private array $resourceUseTick = [];

    /**
     * Constructor
     * 
     * @param GLState $gl 
     * @return void 
     */
    public function __construct(
        public GLState $gl
    )
    {
    }

    /**
     * Sets the current tick index
     * 
     * @param int $index
     * @return void
     */
    public function setCurrentTick(int $index): void
    {
        $this->tickIndex = $index;
    }

    /**
     * Sets a render target to the given handle
     * 
     * @param RenderResource $resource
     * @param RenderTarget $target
     * 
     * @return void
     */
    public function setRenderTarget(RenderResource $resource, RenderTarget $target): void
    {
        $this->renderTargets[$resource->name] = $target;
    }

    /**
     * Creates a missing render target for the given resource
     * 
     * @param RenderTargetResource $resource
     * 
     * @return void
     */
    private function createRenderTarget(RenderTargetResource $resource) : void
    {
        $target = new RenderTarget($resource->width, $resource->height, new Framebuffer($this->gl));
        $target->contentScaleX = $resource->contentScaleX;
        $target->contentScaleY = $resource->contentScaleY;

        $drawBuffers = [];

        // attach color attachments
        foreach($resource->colorAttachments as $i => $colorAttachmentTextureResource) {
            $texture = new Texture($this->gl, $colorAttachmentTextureResource->name);
            $options = $colorAttachmentTextureResource->options ?? new TextureOptions;

            // if min filter is using a mipmap, fallback to linear
            if ($options->minFilter === GL_NEAREST_MIPMAP_NEAREST || $options->minFilter === GL_NEAREST_MIPMAP_LINEAR) {
                $options->minFilter = GL_NEAREST;
            } elseif ($options->minFilter === GL_LINEAR_MIPMAP_NEAREST || $options->minFilter === GL_LINEAR_MIPMAP_LINEAR) {
                $options->minFilter = GL_LINEAR;
            }
            $options->generateMipmaps = false;
            $texture->allocateEmpty(
                $colorAttachmentTextureResource->width, 
                $colorAttachmentTextureResource->height,
                $options
            );

            // store the texture
            $this->textures[$colorAttachmentTextureResource->name] = $texture;

            $target->framebuffer()->bind();

            glFramebufferTexture2D(GL_FRAMEBUFFER, GL_COLOR_ATTACHMENT0 + $i, $texture->target, $texture->id, 0);  

            $drawBuffers[] = GL_COLOR_ATTACHMENT0 + $i;
        }

        if (!empty($drawBuffers)) {
            glDrawBuffers(count($drawBuffers), ...$drawBuffers);
        }


        // attach depth attachment
        if ($resource->depthAttachment !== null) {
            $texture = new Texture($this->gl, $resource->depthAttachment->name);

            // default depth texture options
            if ($resource->depthAttachment->options === null) {
                $options = new TextureOptions;
                $options->internalFormat = GL_DEPTH_COMPONENT;
                $options->dataFormat = GL_DEPTH_COMPONENT;
                $options->dataType = GL_FLOAT;
                $options->minFilter = GL_NEAREST;
                $options->magFilter = GL_NEAREST;
                $options->wrapS = GL_CLAMP_TO_EDGE;
                $options->wrapT = GL_CLAMP_TO_EDGE;
                $resource->depthAttachment->options = $options;
            }

            $options = $resource->depthAttachment->options;
            $options->generateMipmaps = false;

            $texture->allocateEmpty(
                $resource->depthAttachment->width, 
                $resource->depthAttachment->height,
                $options
            );

            // store the texture
            $this->textures[$resource->depthAttachment->name] = $texture;

            $target->framebuffer()->bind();

            glFramebufferTexture2D(GL_FRAMEBUFFER, GL_DEPTH_ATTACHMENT, $texture->target, $texture->id, 0);  
        }

        // create a renderbuffer attachment
        if ($resource->createRenderbufferDepthStencil || $resource->createRenderbufferColor) {
            $framebuffer = $target->framebuffer();
            
            if (!$framebuffer instanceof Framebuffer) {
                throw new PipelineResourceException("Cannot attach a renderbuffer to the given type of framebuffer.");
            } 

            // bind the frame buffer before attaching additon render buffers
            $framebuffer->bind();
            if ($resource->createRenderbufferDepthStencil) {
                $framebuffer->createRenderbufferAttachment(GL_DEPTH24_STENCIL8, GL_DEPTH_STENCIL_ATTACHMENT, $resource->width, $resource->height);
            } elseif ($resource->createRenderbufferColor) {
                $framebuffer->createRenderbufferAttachment(GL_RGBA, GL_COLOR_ATTACHMENT0, $resource->width, $resource->height);
            }
        }

        if (!$target->framebuffer()->isValid($status, $error)) {
            throw new PipelineResourceException("Failed to create render target: {$status} - {$error}");
        }

        $this->setRenderTarget($resource, $target);
    }

    /**
     * Destroys a render target
     * 
     * @param RenderTargetResource $resource
     * 
     * @return void
     */
    public function destroyRenderTarget(RenderTargetResource $resource): void
    {
        // destroy all color attachments
        foreach($resource->colorAttachments as $colorAttachmentTextureResource) {
            unset($this->textures[$colorAttachmentTextureResource->name]);
        }

        if (isset($this->renderTargets[$resource->name])) {
            unset($this->renderTargets[$resource->name]);
        }
    }

    /**
     * Returns the render target for the given resource
     * 
     * @param RenderTargetResource $resource
     * 
     * @return RenderTarget
     */
    public function getRenderTarget(RenderTargetResource $resource): RenderTarget
    {
        $this->resourceUseTick[$resource->name] = $this->tickIndex;

        // render target not found, create it
        if (!isset($this->renderTargets[$resource->name])) {
            $this->createRenderTarget($resource);
        }

        // if it exists, check if it the dimensions match otherwise destroy and recreate
        $target = $this->renderTargets[$resource->name];
        if ($target->width() !== $resource->width || $target->height() !== $resource->height) {
            $this->destroyRenderTarget($resource);
            $this->createRenderTarget($resource);
        }

        return $this->renderTargets[$resource->name];
    }

    /**
     * Activates the given render target
     * 
     * @param RenderTargetResource $resource
     * 
     * @return RenderTarget
     */
    public function activateRenderTarget(RenderTargetResource $resource): RenderTarget
    {
        $target = $this->getRenderTarget($resource);
        $target->preparePass();
        $this->activeRenderTarget = $target;
        return $target;
    }

    /**
     * Returns the currently active render target, throws an exception if none is active
     * 
     * @return RenderTarget
     */
    public function getActiveRenderTarget(): RenderTarget
    {
        if ($this->activeRenderTarget === null) {
            throw new PipelineResourceException("No active render target");
        }

        return $this->activeRenderTarget;
    }

    /**
     * Sets a texture to the given handle
     * 
     * @param RenderResource $resource
     * @param Texture $texture
     * 
     * @return void
     */
    public function setTexture(RenderResource $resource, Texture $texture): void
    {
        $this->textures[$resource->name] = $texture;
    }

    /**
     * Returns a texture for the given resource
     * 
     * @param RenderResource $resource
     * @return Texture
     */
    public function getTexture(RenderResource $resource): Texture
    {
        $this->resourceUseTick[$resource->name] = $this->tickIndex;

        if (!isset($this->textures[$resource->name])) {
            throw new PipelineResourceException("Texture not found for resource handle: " . $resource->handle . ' name: ' . $resource->name);
        }

        return $this->textures[$resource->name];
    }

    /**
     * Returns a texture ID for the given resource
     * The texture ID is the raw GL handle
     * 
     * @param RenderResource $resource
     * @return int
     */
    public function getTextureID(RenderResource $resource): int
    {
        return $this->getTexture($resource)->id;
    }

    /**
     * Stores a generic resource / value or object by name
     * 
     * @param string $name
     * @param mixed $value
     */
    public function setStaticResource(string $name, mixed $value): void
    {
        $this->staticStorage[$name] = $value;
    }

    /**
     * Returns a generic resource / value or object by name
     * 
     * @param string $name
     * 
     * @return mixed
     */
    public function getStaticResource(string $name): mixed
    {
        if (!isset($this->staticStorage[$name])) {
            throw new PipelineResourceException("Static resource not found for name: " . $name);
        }

        return $this->staticStorage[$name];
    }

    /**
     * Cacehs a generic resource / value or object by name
     * This is the same as getStaticResource but you provide a callback which is called if the resource is not found.
     * 
     * @param string $name
     * @param callable $callback
     * 
     * @return mixed
     */
    public function cacheStaticResource(string $name, callable $callback): mixed
    {
        if (!isset($this->staticStorage[$name])) {
            $this->staticStorage[$name] = $callback($this->gl);
        }

        return $this->staticStorage[$name];
    }

    /**
     * Collects all garbage and removes all unused resources
     * 
     * @return void
     */
    public function collectGarbage(): void
    {
        foreach ($this->resourceUseTick as $name => $tick) {
            if ($tick < $this->tickIndex) {
                unset($this->renderTargets[$name]);
                unset($this->resourceUseTick[$name]);
            }
        }
    }
}
