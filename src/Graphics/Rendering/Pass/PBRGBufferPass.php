<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\TextureOptions;

/**
 * Extended GBuffer pass that creates all PBR attachments including
 * metallic/roughness and emissive. Does NOT extend GBufferPass::setup()
 * because the parent uses GL_SRGB for albedo which is not color-renderable
 * on many drivers when combined with additional attachments.
 */
class PBRGBufferPass extends GBufferPass
{
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $cameraData = $data->get(CameraData::class);
        $gbufferData = $data->create(GBufferPassData::class);

        $gbufferData->renderTarget = $pipeline->createRenderTarget(
            'gbuffer', $cameraData->resolutionX, $cameraData->resolutionY
        );

        // depth
        $gbufferData->depthTexture = $pipeline->createDepthAttachment($gbufferData->renderTarget);

        // world-space position (RGB32F)
        $spaceTextureOptions = new TextureOptions;
        $spaceTextureOptions->internalFormat = GL_RGB32F;
        $spaceTextureOptions->generateMipmaps = false;
        $gbufferData->worldSpacePositionTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'position', $spaceTextureOptions
        );

        // view-space position (RGB32F)
        $gbufferData->viewSpacePositionTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'view_position', $spaceTextureOptions
        );

        // normals (RGB16F)
        $normalTextureOptions = new TextureOptions;
        $normalTextureOptions->internalFormat = GL_RGB16F;
        $normalTextureOptions->dataFormat = GL_RGB;
        $normalTextureOptions->dataType = GL_FLOAT;
        $normalTextureOptions->generateMipmaps = false;
        $gbufferData->normalTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'normal', $normalTextureOptions
        );

        // albedo (RGBA8 — must be color-renderable, NOT GL_SRGB)
        $albedoTextureOptions = new TextureOptions;
        $albedoTextureOptions->internalFormat = GL_RGBA8;
        $albedoTextureOptions->generateMipmaps = false;
        $gbufferData->albedoTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'albedo', $albedoTextureOptions
        );

        // PBR-specific attachments
        $pbrData = $data->create(PBRGBufferData::class);

        // metallic (R) + roughness (G)
        $mrOptions = new TextureOptions;
        $mrOptions->internalFormat = GL_RG16F;
        $mrOptions->dataFormat = GL_RG;
        $mrOptions->dataType = GL_FLOAT;
        $mrOptions->generateMipmaps = false;
        $pbrData->metallicRoughnessTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'metallic_roughness', $mrOptions
        );

        // emissive (RGB16F for HDR bloom support)
        $emissiveOptions = new TextureOptions;
        $emissiveOptions->internalFormat = GL_RGB16F;
        $emissiveOptions->dataFormat = GL_RGB;
        $emissiveOptions->dataType = GL_FLOAT;
        $emissiveOptions->generateMipmaps = false;
        $pbrData->emissiveTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'emissive', $emissiveOptions
        );
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        // parent clears color + depth
        parent::execute($data, $resources);
    }
}
