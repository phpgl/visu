<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Asset\AssetManager;
use VISU\Component\SpriteRenderer;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\SortingLayer;
use VISU\Graphics\Texture;

class SpriteBatchPass extends RenderPass
{
    /**
     * NanoVG image handles cache: texture path => NanoVG image ID.
     *
     * @var array<string, int>
     */
    private array $nvgImages = [];

    public function __construct(
        private RenderTargetResource $renderTargetRes,
        private EntitiesInterface $entities,
        private AssetManager $assetManager,
        private SortingLayer $sortingLayer,
        private Camera2DData $cameraData,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->writes($this, $this->renderTargetRes);
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $renderTarget = $resources->getRenderTarget($this->renderTargetRes);
        $resources->activateRenderTarget($this->renderTargetRes);

        $vg = \GL\VectorGraphics\VGContext::getInstance();
        if ($vg === null) {
            return;
        }

        $width = $renderTarget->width();
        $height = $renderTarget->height();
        $dpi = $renderTarget->contentScaleX;

        // Collect sprites with sort keys
        $sprites = [];
        foreach ($this->entities->view(SpriteRenderer::class) as $entityId => $sprite) {
            $transform = $this->entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }

            $sortKey = $this->sortingLayer->getSortKey(
                $sprite->sortingLayer,
                $sprite->orderInLayer,
                $transform->position->y
            );

            $sprites[] = [
                'sortKey' => $sortKey,
                'sprite' => $sprite,
                'transform' => $transform,
            ];
        }

        // Sort by key (lower = rendered first = behind)
        usort($sprites, fn($a, $b) => $a['sortKey'] <=> $b['sortKey']);

        // Get camera offset for world-to-screen transformation
        $camX = $this->cameraData->x;
        $camY = $this->cameraData->y;
        $camZoom = $this->cameraData->zoom;

        // Screen center
        $cx = $width / (2 * $dpi);
        $cy = $height / (2 * $dpi);

        $vg->beginFrame((int)($width / $dpi), (int)($height / $dpi), $dpi);

        foreach ($sprites as $entry) {
            /** @var SpriteRenderer $sprite */
            $sprite = $entry['sprite'];
            /** @var Transform $transform */
            $transform = $entry['transform'];

            if ($sprite->sprite === '') {
                continue;
            }

            // Load texture via AssetManager
            $texture = $this->assetManager->getTexture($sprite->sprite);
            if ($texture === null) {
                try {
                    $texture = $this->assetManager->loadTexture($sprite->sprite);
                } catch (\Throwable) {
                    continue;
                }
            }

            // Get or create NanoVG image
            $nvgImage = $this->getNvgImage($vg, $sprite->sprite, $texture);
            if ($nvgImage === 0) {
                continue;
            }

            // Determine sprite dimensions
            $spriteW = $sprite->width > 0 ? $sprite->width : $texture->width();
            $spriteH = $sprite->height > 0 ? $sprite->height : $texture->height();

            // Apply UV rect for sprite sheets
            if ($sprite->uvRect !== null) {
                $spriteW = (int)($texture->width() * $sprite->uvRect[2]);
                $spriteH = (int)($texture->height() * $sprite->uvRect[3]);
            }

            // Apply scale
            $scaleX = $transform->scale->x * ($sprite->flipX ? -1 : 1);
            $scaleY = $transform->scale->y * ($sprite->flipY ? -1 : 1);
            $drawW = $spriteW * abs($scaleX) * $camZoom;
            $drawH = $spriteH * abs($scaleY) * $camZoom;

            // World position to screen
            $screenX = $cx + ($transform->position->x - $camX) * $camZoom;
            $screenY = $cy + ($transform->position->y - $camY) * $camZoom;

            // Draw centered
            $drawX = $screenX - $drawW / 2;
            $drawY = $screenY - $drawH / 2;

            $vg->save();
            $vg->globalAlpha($sprite->opacity * $sprite->color[3]);

            // Rotation
            $euler = $transform->getLocalEulerAngles();
            if ($euler->z != 0.0) {
                $vg->translate($screenX, $screenY);
                $vg->rotate($euler->z);
                $vg->translate(-$screenX, -$screenY);
            }

            // Flip via negative scale
            if ($scaleX < 0 || $scaleY < 0) {
                $vg->translate($screenX, $screenY);
                $vg->scale($scaleX < 0 ? -1 : 1, $scaleY < 0 ? -1 : 1);
                $vg->translate(-$screenX, -$screenY);
            }

            // Create image paint
            if ($sprite->uvRect !== null) {
                $uvX = $sprite->uvRect[0] * $texture->width();
                $uvY = $sprite->uvRect[1] * $texture->height();
                $paint = $vg->imagePattern(
                    $drawX - $uvX * $camZoom,
                    $drawY - $uvY * $camZoom,
                    $texture->width() * $camZoom,
                    $texture->height() * $camZoom,
                    0,
                    $nvgImage,
                    1.0
                );
            } else {
                $paint = $vg->imagePattern(
                    $drawX,
                    $drawY,
                    $drawW,
                    $drawH,
                    0,
                    $nvgImage,
                    1.0
                );
            }

            $vg->beginPath();
            $vg->rect($drawX, $drawY, $drawW, $drawH);
            $vg->fillPaint($paint);
            $vg->fill();

            $vg->restore();
        }

        $vg->endFrame();
        $resources->gl->reset();
    }

    /**
     * Gets or creates a NanoVG image handle for a texture.
     */
    private function getNvgImage(\GL\VectorGraphics\VGContext $vg, string $path, Texture $texture): int
    {
        if (isset($this->nvgImages[$path])) {
            return $this->nvgImages[$path];
        }

        // Create NanoVG image from GL texture
        $imgId = $vg->createImageFromHandle($texture->id, $texture->width(), $texture->height(), 0);
        $this->nvgImages[$path] = $imgId;
        return $imgId;
    }
}
