<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\{GLM, Mat4, Vec3};
use VISU\Component\MeshRendererComponent;
use VISU\Component\PointLightComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class PointLightShadowPass extends RenderPass
{
    const MAX_SHADOW_POINT_LIGHTS = 4;
    const DEFAULT_RESOLUTION = 512;

    /**
     * Cubemap face directions and up vectors
     * @var array<array{Vec3, Vec3}>|null
     */
    private ?array $faceDirections = null;

    public function __construct(
        private ShaderProgram $depthShader,
        private EntitiesInterface $entities,
        private ModelCollection $modelCollection,
        private int $resolution = self::DEFAULT_RESOLUTION,
    ) {
    }

    /**
     * @return array<array{Vec3, Vec3}>
     */
    private function getFaceDirections(): array
    {
        if ($this->faceDirections === null) {
            $this->faceDirections = [
                [new Vec3(1, 0, 0), new Vec3(0, -1, 0)],   // +X
                [new Vec3(-1, 0, 0), new Vec3(0, -1, 0)],  // -X
                [new Vec3(0, 1, 0), new Vec3(0, 0, 1)],    // +Y
                [new Vec3(0, -1, 0), new Vec3(0, 0, -1)],  // -Y
                [new Vec3(0, 0, 1), new Vec3(0, -1, 0)],   // +Z
                [new Vec3(0, 0, -1), new Vec3(0, -1, 0)],  // -Z
            ];
        }
        return $this->faceDirections;
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $data->create(PointLightShadowData::class);
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $shadowData = $data->get(PointLightShadowData::class);
        $shadowData->resolution = $this->resolution;

        // collect shadow-casting point lights
        /** @var array<array{position: Vec3, range: float}> */
        $shadowLights = [];
        foreach ($this->entities->view(PointLightComponent::class) as $entity => $light) {
            if (!$light->castsShadows) {
                continue;
            }
            if (count($shadowLights) >= self::MAX_SHADOW_POINT_LIGHTS) {
                break;
            }

            $transform = $this->entities->get($entity, Transform::class);
            $shadowLights[] = [
                'position' => $transform->getWorldPosition($this->entities),
                'range' => $light->range,
            ];
        }

        $shadowData->shadowLightCount = count($shadowLights);
        if ($shadowData->shadowLightCount === 0) {
            return;
        }

        // lazily create GL resources (FBO + cubemap textures)
        /** @var \stdClass */
        $glRes = $resources->cacheStaticResource('point_shadow_gl', function () {
            glGenFramebuffers(1, $fbo);
            $res = new \stdClass();
            $res->fbo = $fbo;
            $res->cubemaps = [];
            $res->resolution = 0;
            $res->count = 0;
            return $res;
        });

        // recreate cubemaps if resolution changed or we need more
        if ($glRes->count < self::MAX_SHADOW_POINT_LIGHTS || $glRes->resolution !== $this->resolution) {
            // delete old cubemaps
            foreach ($glRes->cubemaps as $texId) {
                glDeleteTextures(1, $texId);
            }
            $glRes->cubemaps = [];

            for ($i = 0; $i < self::MAX_SHADOW_POINT_LIGHTS; $i++) {
                glGenTextures(1, $texId);
                glBindTexture(GL_TEXTURE_CUBE_MAP, $texId);
                for ($face = 0; $face < 6; $face++) {
                    glTexImage2D(
                        GL_TEXTURE_CUBE_MAP_POSITIVE_X + $face,
                        0,
                        GL_DEPTH_COMPONENT,
                        $this->resolution,
                        $this->resolution,
                        0,
                        GL_DEPTH_COMPONENT,
                        GL_FLOAT,
                        null
                    );
                }
                glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
                glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
                glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
                glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);
                glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_R, GL_CLAMP_TO_EDGE);
                $glRes->cubemaps[] = $texId;
            }
            $glRes->count = self::MAX_SHADOW_POINT_LIGHTS;
            $glRes->resolution = $this->resolution;
        }

        $faceDirections = $this->getFaceDirections();

        $this->depthShader->use();
        glEnable(GL_DEPTH_TEST);
        glCullFace(GL_FRONT); // peter panning prevention
        glViewport(0, 0, $this->resolution, $this->resolution);

        for ($li = 0; $li < $shadowData->shadowLightCount; $li++) {
            $lightPos = $shadowLights[$li]['position'];
            $farPlane = $shadowLights[$li]['range'];

            $shadowData->lightPositions[$li] = $lightPos;
            $shadowData->farPlanes[$li] = $farPlane;
            $shadowData->cubemapTextureIds[$li] = $glRes->cubemaps[$li];

            $projection = new Mat4();
            $projection->perspective(GLM::radians(90.0), 1.0, 0.1, $farPlane);

            $this->depthShader->setUniformVec3('u_light_pos', $lightPos);
            $this->depthShader->setUniform1f('u_far_plane', $farPlane);

            for ($face = 0; $face < 6; $face++) {
                $target = new Vec3(
                    $lightPos->x + $faceDirections[$face][0]->x,
                    $lightPos->y + $faceDirections[$face][0]->y,
                    $lightPos->z + $faceDirections[$face][0]->z,
                );
                $view = new Mat4();
                $view->lookAt($lightPos, $target, $faceDirections[$face][1]);

                /** @var Mat4 $lightSpace */
                $lightSpace = $projection * $view;

                // attach cubemap face to FBO
                glBindFramebuffer(GL_FRAMEBUFFER, $glRes->fbo);
                glFramebufferTexture2D(
                    GL_FRAMEBUFFER,
                    GL_DEPTH_ATTACHMENT,
                    GL_TEXTURE_CUBE_MAP_POSITIVE_X + $face,
                    $glRes->cubemaps[$li],
                    0
                );
                glDrawBuffer(GL_NONE);
                glReadBuffer(GL_NONE);
                glClear(GL_DEPTH_BUFFER_BIT);

                $this->depthShader->setUniformMatrix4f('u_light_space', false, $lightSpace);

                // render all shadow-casting meshes
                foreach ($this->entities->view(MeshRendererComponent::class) as $entity => $renderer) {
                    if (!$renderer->castsShadows) {
                        continue;
                    }
                    if (!$this->modelCollection->has($renderer->modelIdentifier)) {
                        continue;
                    }

                    $transform = $this->entities->get($entity, Transform::class);
                    $this->depthShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($this->entities));

                    $model = $this->modelCollection->get($renderer->modelIdentifier);
                    foreach ($model->meshes as $mesh) {
                        $mesh->draw();
                    }
                }
            }
        }

        glCullFace(GL_BACK);

        // invalidate GLState so next pass re-binds its framebuffer
        $resources->gl->currentReadFramebuffer = -1;
        $resources->gl->currentDrawFramebuffer = -1;
    }
}
