<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\{GLM, Mat4, Vec3, Vec4};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\MeshRendererComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\TextureOptions;

class ShadowMapPass extends RenderPass
{
    const DEFAULT_CASCADE_COUNT = 4;
    const DEFAULT_RESOLUTION = 2048;

    /**
     * Controls the split scheme between logarithmic (1.0) and uniform (0.0)
     */
    private float $cascadeLambda = 0.5;

    public function __construct(
        private ShaderProgram $depthShader,
        private DirectionalLightComponent $sun,
        private EntitiesInterface $entities,
        private ModelCollection $modelCollection,
        private int $cascadeCount = self::DEFAULT_CASCADE_COUNT,
        private int $resolution = self::DEFAULT_RESOLUTION,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $shadowData = $data->create(ShadowMapData::class);
        $shadowData->cascadeCount = $this->cascadeCount;
        $shadowData->resolution = $this->resolution;

        for ($i = 0; $i < $this->cascadeCount; $i++) {
            $rt = $pipeline->createRenderTarget(
                "shadow_cascade_{$i}",
                $this->resolution,
                $this->resolution,
            );

            $depthOptions = new TextureOptions();
            $depthOptions->internalFormat = GL_DEPTH_COMPONENT;
            $depthOptions->dataFormat = GL_DEPTH_COMPONENT;
            $depthOptions->dataType = GL_FLOAT;
            $depthOptions->minFilter = GL_LINEAR;
            $depthOptions->magFilter = GL_LINEAR;
            $depthOptions->wrapS = GL_CLAMP_TO_EDGE;
            $depthOptions->wrapT = GL_CLAMP_TO_EDGE;

            $shadowData->renderTargets[] = $rt;
            $shadowData->depthTextures[] = $pipeline->createDepthAttachment($rt, $depthOptions);
        }
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $shadowData = $data->get(ShadowMapData::class);
        $cameraData = $data->get(CameraData::class);

        $camera = $cameraData->frameCamera;
        $near = $camera->nearPlane;
        $far = min($camera->farPlane, 200.0);

        $splits = $this->computeCascadeSplits($near, $far, $this->cascadeCount);
        $shadowData->cascadeSplits = $splits;

        $lightDir = new Vec3(
            $this->sun->direction->x,
            $this->sun->direction->y,
            $this->sun->direction->z,
        );
        $lightDir->normalize();

        $this->depthShader->use();

        for ($i = 0; $i < $this->cascadeCount; $i++) {
            $cascadeNear = $i === 0 ? $near : $splits[$i - 1];
            $cascadeFar = $splits[$i];

            $lightSpaceMatrix = $this->computeLightSpaceMatrix(
                $cameraData, $lightDir, $cascadeNear, $cascadeFar
            );
            $shadowData->lightSpaceMatrices[$i] = $lightSpaceMatrix;

            $target = $resources->activateRenderTarget($shadowData->renderTargets[$i]);
            $target->framebuffer()->clear(GL_DEPTH_BUFFER_BIT);

            glEnable(GL_DEPTH_TEST);
            glCullFace(GL_FRONT); // peter panning prevention

            $this->depthShader->setUniformMatrix4f('u_light_space', false, $lightSpaceMatrix);

            foreach ($this->entities->view(MeshRendererComponent::class) as $entity => $renderer) {
                if (!$renderer->castsShadows) continue;
                if (!$this->modelCollection->has($renderer->modelIdentifier)) continue;

                $transform = $this->entities->get($entity, Transform::class);
                $this->depthShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($this->entities));

                $model = $this->modelCollection->get($renderer->modelIdentifier);
                foreach ($model->meshes as $mesh) {
                    $mesh->draw();
                }
            }

            glCullFace(GL_BACK);
        }
    }

    /**
     * @return array<float>
     */
    private function computeCascadeSplits(float $near, float $far, int $cascadeCount): array
    {
        $splits = [];
        for ($i = 1; $i <= $cascadeCount; $i++) {
            $p = $i / $cascadeCount;
            $log = $near * pow($far / $near, $p);
            $uniform = $near + ($far - $near) * $p;
            $splits[] = $this->cascadeLambda * $log + (1.0 - $this->cascadeLambda) * $uniform;
        }
        return $splits;
    }

    private function computeLightSpaceMatrix(
        CameraData $cameraData,
        Vec3 $lightDir,
        float $cascadeNear,
        float $cascadeFar,
    ): Mat4 {
        $camera = $cameraData->frameCamera;
        $aspect = $cameraData->resolutionX / max(1, $cameraData->resolutionY);

        // build cascade-specific projection matrix
        $cascadeProj = new Mat4();
        $cascadeProj->perspective($camera->fieldOfView, $aspect, $cascadeNear, $cascadeFar);

        // cascade projection-view matrix, then invert to get frustum corners in world space
        /** @var Mat4 */
        $cascadePV = $cascadeProj * $cameraData->view;
        $invPV = Mat4::inverted($cascadePV);

        // 8 corners of the cascade frustum in world space
        $corners = [];
        for ($x = 0; $x < 2; $x++) {
            for ($y = 0; $y < 2; $y++) {
                for ($z = 0; $z < 2; $z++) {
                    $pt = new Vec4(
                        2.0 * $x - 1.0,
                        2.0 * $y - 1.0,
                        2.0 * $z - 1.0,
                        1.0,
                    );
                    /** @var Vec4 $pt */
                    $pt = $invPV * $pt;
                    $corners[] = new Vec3($pt->x / $pt->w, $pt->y / $pt->w, $pt->z / $pt->w);
                }
            }
        }

        // frustum center
        $cx = 0.0; $cy = 0.0; $cz = 0.0;
        foreach ($corners as $c) {
            $cx += $c->x;
            $cy += $c->y;
            $cz += $c->z;
        }
        $center = new Vec3($cx / 8.0, $cy / 8.0, $cz / 8.0);

        // light view matrix
        $eye = new Vec3(
            $center->x - $lightDir->x * 50.0,
            $center->y - $lightDir->y * 50.0,
            $center->z - $lightDir->z * 50.0,
        );
        $lightView = new Mat4();
        $lightView->lookAt($eye, $center, new Vec3(0, 1, 0));

        // bounding box in light space
        $minX = PHP_FLOAT_MAX;
        $maxX = -PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        $minZ = PHP_FLOAT_MAX;
        $maxZ = -PHP_FLOAT_MAX;

        foreach ($corners as $corner) {
            $v = new Vec4($corner->x, $corner->y, $corner->z, 1.0);
            /** @var Vec4 $v */
            $v = $lightView * $v;
            $minX = min($minX, $v->x);
            $maxX = max($maxX, $v->x);
            $minY = min($minY, $v->y);
            $maxY = max($maxY, $v->y);
            $minZ = min($minZ, $v->z);
            $maxZ = max($maxZ, $v->z);
        }

        // extend Z to capture shadow casters behind the frustum
        $zExtend = ($maxZ - $minZ) * 2.0;
        $minZ -= $zExtend;

        $lightProj = new Mat4();
        $lightProj->ortho($minX, $maxX, $minY, $maxY, $minZ, $maxZ);

        /** @var Mat4 */
        return $lightProj * $lightView;
    }
}
