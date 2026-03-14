<?php

namespace VISU\Tests\Benchmark;

use GL\Math\{GLM, Mat4, Vec3};
use VISU\Graphics\Rendering\Pass\PointLightShadowData;
use VISU\Graphics\Rendering\Pass\ShadowMapData;

class ShadowBench
{
    private ShadowMapData $csmData;
    private PointLightShadowData $pointShadowData;

    /** @var array<array{Vec3, Vec3}> */
    private array $faceDirections;

    public function setUp(): void
    {
        // CSM data setup
        $this->csmData = new ShadowMapData();
        $this->csmData->cascadeCount = 4;
        $this->csmData->cascadeSplits = [10.0, 30.0, 70.0, 200.0];
        for ($i = 0; $i < 4; $i++) {
            $this->csmData->lightSpaceMatrices[$i] = new Mat4();
        }

        // Point shadow data setup
        $this->pointShadowData = new PointLightShadowData();
        $this->pointShadowData->resolution = 512;
        $this->pointShadowData->shadowLightCount = 4;
        for ($i = 0; $i < 4; $i++) {
            $this->pointShadowData->cubemapTextureIds[$i] = 100 + $i;
            $this->pointShadowData->farPlanes[$i] = 20.0 + $i * 10.0;
            $this->pointShadowData->lightPositions[$i] = new Vec3($i * 5.0, 3.0, 0.0);
        }

        // Cubemap face directions (same as PointLightShadowPass)
        $this->faceDirections = [
            [new Vec3(1, 0, 0), new Vec3(0, -1, 0)],
            [new Vec3(-1, 0, 0), new Vec3(0, -1, 0)],
            [new Vec3(0, 1, 0), new Vec3(0, 0, 1)],
            [new Vec3(0, -1, 0), new Vec3(0, 0, -1)],
            [new Vec3(0, 0, 1), new Vec3(0, -1, 0)],
            [new Vec3(0, 0, -1), new Vec3(0, -1, 0)],
        ];
    }

    /**
     * Benchmark cascade selection by view-space depth (done per-pixel in shader, simulated here).
     *
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchCascadeSelection(): void
    {
        $splits = $this->csmData->cascadeSplits;
        $cascadeCount = $this->csmData->cascadeCount;

        // Simulate 100 fragments at varying depths
        for ($f = 0; $f < 100; $f++) {
            $depth = $f * 2.5; // 0..250 range
            $cascadeIndex = $cascadeCount - 1;
            for ($i = 0; $i < $cascadeCount; $i++) {
                if ($depth < $splits[$i]) {
                    $cascadeIndex = $i;
                    break;
                }
            }
        }
    }

    /**
     * Benchmark cubemap face view matrix computation (done per-light per-frame).
     *
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchCubemapFaceMatrices(): void
    {
        $lightPos = new Vec3(5.0, 3.0, 0.0);
        $farPlane = 25.0;
        $projection = new Mat4();
        $projection->perspective(GLM::radians(90.0), 1.0, 0.1, $farPlane);

        // 6 faces per light
        for ($face = 0; $face < 6; $face++) {
            $target = new Vec3(
                $lightPos->x + $this->faceDirections[$face][0]->x,
                $lightPos->y + $this->faceDirections[$face][0]->y,
                $lightPos->z + $this->faceDirections[$face][0]->z,
            );
            $view = new Mat4();
            $view->lookAt($lightPos, $target, $this->faceDirections[$face][1]);
            /** @var Mat4 $lightSpace */
            $lightSpace = $projection * $view;
        }
    }

    /**
     * Benchmark computing all face matrices for 4 shadow-casting point lights.
     *
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchAllPointLightMatrices(): void
    {
        for ($li = 0; $li < $this->pointShadowData->shadowLightCount; $li++) {
            $lightPos = $this->pointShadowData->lightPositions[$li];
            $farPlane = $this->pointShadowData->farPlanes[$li];

            $projection = new Mat4();
            $projection->perspective(GLM::radians(90.0), 1.0, 0.1, $farPlane);

            for ($face = 0; $face < 6; $face++) {
                $target = new Vec3(
                    $lightPos->x + $this->faceDirections[$face][0]->x,
                    $lightPos->y + $this->faceDirections[$face][0]->y,
                    $lightPos->z + $this->faceDirections[$face][0]->z,
                );
                $view = new Mat4();
                $view->lookAt($lightPos, $target, $this->faceDirections[$face][1]);
                /** @var Mat4 $lightSpace */
                $lightSpace = $projection * $view;
            }
        }
    }

    /**
     * Benchmark point shadow lookup simulation (finding which shadow map matches a light index).
     *
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchShadowLightMapping(): void
    {
        // Simulate mapping 32 point lights to shadow indices (as done in shader)
        $shadowMapping = [0 => 0, 1 => 3, 2 => 5, 3 => 7];
        $numShadows = 4;

        for ($i = 0; $i < 32; $i++) {
            $hasShadow = false;
            for ($s = 0; $s < $numShadows; $s++) {
                if ($shadowMapping[$s] === $i) {
                    $hasShadow = true;
                    break;
                }
            }
        }
    }
}
