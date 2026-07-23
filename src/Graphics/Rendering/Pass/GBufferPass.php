<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\TextureOptions;

class GBufferPass extends RenderPass
{
    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $cameraData = $data->get(CameraData::class);
        $gbufferData = $data->create(GBufferPassData::class);

        $gbufferData->renderTarget = $pipeline->createRenderTarget('gbuffer', $cameraData->resolutionX, $cameraData->resolutionY);

        // depth
        $gbufferData->depthTexture = $pipeline->createDepthAttachment($gbufferData->renderTarget);

        $spaceTextureOptions = new TextureOptions;
        $spaceTextureOptions->internalFormat = GL_RGB32F;
        $spaceTextureOptions->generateMipmaps = false;
        $gbufferData->worldSpacePositionTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'position', $spaceTextureOptions);
        $gbufferData->viewSpacePositionTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'view_position', $spaceTextureOptions);

        $normalTextureOptions = new TextureOptions;
        $normalTextureOptions->internalFormat = GL_RGB16F;
        $normalTextureOptions->dataFormat = GL_RGB;
        $normalTextureOptions->dataType = GL_FLOAT;
        $normalTextureOptions->generateMipmaps = false;
        $gbufferData->normalTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'normal', $normalTextureOptions);

        $albedoTextureOptions = new TextureOptions;
        $albedoTextureOptions->internalFormat = GL_SRGB;
        $gbufferData->albedoTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'albedo', $albedoTextureOptions);

        $metallicTextureOptions = new TextureOptions;
        $metallicTextureOptions->dataFormat = GL_RED;
        $metallicTextureOptions->dataType = GL_UNSIGNED_BYTE;
        $metallicTextureOptions->internalFormat = GL_R8;
        $metallicTextureOptions->generateMipmaps = false;
        $gbufferData->metallicTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'metallic', $metallicTextureOptions);

        $roughnessTextureOptions = new TextureOptions;
        $roughnessTextureOptions->dataFormat = GL_RED;
        $roughnessTextureOptions->dataType = GL_UNSIGNED_BYTE;
        $roughnessTextureOptions->internalFormat = GL_R8;
        $roughnessTextureOptions->generateMipmaps = false;
        $gbufferData->roughnessTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'roughness', $roughnessTextureOptions);

        $emissiveTextureOptions = new TextureOptions;
        $emissiveTextureOptions->internalFormat = GL_RGB16F;
        $emissiveTextureOptions->dataFormat = GL_RGB;
        $emissiveTextureOptions->dataType = GL_FLOAT;
        $emissiveTextureOptions->generateMipmaps = false;
        $gbufferData->emissiveTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'emissive', $emissiveTextureOptions);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);

        $renderTarget = $resources->getRenderTarget($gbufferData->renderTarget);
        $renderTarget->preparePass();
        
        // clear the framebuffer
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    }
}
