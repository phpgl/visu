<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Asset\AssetManager;
use VISU\Component\Tilemap;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Texture;

class TilemapPass extends RenderPass
{
    /**
     * NanoVG image handles cache.
     *
     * @var array<string, int>
     */
    private array $nvgImages = [];

    public function __construct(
        private RenderTargetResource $renderTargetRes,
        private EntitiesInterface $entities,
        private AssetManager $assetManager,
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

        $screenW = (int)($width / $dpi);
        $screenH = (int)($height / $dpi);

        $camX = $this->cameraData->x;
        $camY = $this->cameraData->y;
        $camZoom = $this->cameraData->zoom;

        $cx = $screenW / 2.0;
        $cy = $screenH / 2.0;

        $vg->beginFrame($screenW, $screenH, $dpi);

        foreach ($this->entities->view(Tilemap::class) as $entityId => $tilemap) {
            if ($tilemap->tileset === '' || $tilemap->width === 0 || $tilemap->height === 0) {
                continue;
            }

            $texture = $this->assetManager->getTexture($tilemap->tileset);
            if ($texture === null) {
                try {
                    $texture = $this->assetManager->loadTexture($tilemap->tileset);
                } catch (\Throwable) {
                    continue;
                }
            }

            $nvgImage = $this->getNvgImage($vg, $tilemap->tileset, $texture);
            if ($nvgImage === 0) {
                continue;
            }

            // Transform offset
            $transform = $this->entities->tryGet($entityId, Transform::class);
            $offsetX = $transform ? $transform->position->x : 0.0;
            $offsetY = $transform ? $transform->position->y : 0.0;

            $tileSize = $tilemap->tileSize;
            $drawTileSize = $tileSize * $camZoom;

            // Calculate visible tile range for culling
            $worldLeft = $camX - $cx / $camZoom;
            $worldTop = $camY - $cy / $camZoom;
            $worldRight = $camX + $cx / $camZoom;
            $worldBottom = $camY + $cy / $camZoom;

            $startX = max(0, (int)(($worldLeft - $offsetX) / $tileSize) - 1);
            $startY = max(0, (int)(($worldTop - $offsetY) / $tileSize) - 1);
            $endX = min($tilemap->width - 1, (int)(($worldRight - $offsetX) / $tileSize) + 1);
            $endY = min($tilemap->height - 1, (int)(($worldBottom - $offsetY) / $tileSize) + 1);

            $texW = $texture->width();
            $texH = $texture->height();

            for ($ty = $startY; $ty <= $endY; $ty++) {
                for ($tx = $startX; $tx <= $endX; $tx++) {
                    // Resolve auto-tiling if enabled, otherwise use raw tile ID
                    $tileId = $tilemap->autoTile
                        ? $tilemap->resolveAutoTile($tx, $ty)
                        : $tilemap->getTile($tx, $ty);
                    if ($tileId <= 0) {
                        continue;
                    }

                    $uv = $tilemap->getTileUV($tileId, $texW, $texH);

                    // World position of this tile
                    $worldTileX = $offsetX + $tx * $tileSize;
                    $worldTileY = $offsetY + $ty * $tileSize;

                    // Screen position
                    $screenTileX = $cx + ($worldTileX - $camX) * $camZoom;
                    $screenTileY = $cy + ($worldTileY - $camY) * $camZoom;

                    // Image pattern: offset so the UV rect maps correctly
                    $patternX = $screenTileX - $uv[0] * $texW * $camZoom;
                    $patternY = $screenTileY - $uv[1] * $texH * $camZoom;

                    $paint = $vg->imagePattern(
                        $patternX,
                        $patternY,
                        $texW * $camZoom,
                        $texH * $camZoom,
                        0,
                        $nvgImage,
                        1.0
                    );

                    $vg->beginPath();
                    $vg->rect($screenTileX, $screenTileY, $drawTileSize, $drawTileSize);
                    $vg->fillPaint($paint);
                    $vg->fill();
                }
            }
        }

        $vg->endFrame();
        $resources->gl->reset();
    }

    private function getNvgImage(\GL\VectorGraphics\VGContext $vg, string $path, Texture $texture): int
    {
        if (isset($this->nvgImages[$path])) {
            return $this->nvgImages[$path];
        }

        $imgId = $vg->createImageFromHandle($texture->id, $texture->width(), $texture->height(), 0);
        $this->nvgImages[$path] = $imgId;
        return $imgId;
    }
}
