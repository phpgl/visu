<?php

namespace VISU\Graphics\Rendering\Pass;

enum DeferredLightPassPermutation: string
{
    /**
     * Basic lighting with sun + hardcoded ambient.
     * No environment cubemap or IBL.
     */
    case basic = 'basic';

    /**
     * Environment cubemap for specular reflections.
     * No IBL irradiance/prefilter maps.
     */
    case envCubemap = 'env_cubemap';

    /**
     * Full image-based lighting (IBL).
     * Includes irradiance map, prefiltered environment, and BRDF LUT.
     */
    case IBL = 'ibl';

    /**
     * Returns the shader defines for this permutation
     *
     * @return array<string, int>
     */
    public function getDefines(): array
    {
        return match ($this) {
            self::basic => [],
            self::envCubemap => ['USE_ENV_CUBEMAP' => 1],
            self::IBL => ['USE_IBL' => 1],
        };
    }

    /**
     * Returns all permutations as an array suitable for ShaderCollection::definePermutations()
     *
     * @return array<string, array<string, int>>
     */
    public static function getAllPermutationDefines(): array
    {
        $permutations = [];
        foreach (self::cases() as $case) {
            $permutations[$case->value] = $case->getDefines();
        }
        return $permutations;
    }
}
