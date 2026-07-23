#ifndef TONE_MAPPING_GLSL
#define TONE_MAPPING_GLSL
/**
 * Common tone mapping functions
 * ----------------------------------------------------------------------------
 */

// define TONEMAP_METHOD in as a shader option to change.
// available methods:
//   TONEMAP_NEUTRAL   (1) - Khronos PBR neutral tone mapper (default)
//   TONEMAP_ACES      (2) - ACES filmic curve
//   TONEMAP_REINHARD  (3) - basic Reinhard
//   TONEMAP_REINHARD2 (4) - Reinhard with white point
#define TONEMAP_NONE 0
#define TONEMAP_NEUTRAL 1
#define TONEMAP_ACES 2
#define TONEMAP_REINHARD 3
#define TONEMAP_REINHARD2 4

// default to the Khronos PBR neutral mapper when nothing is specified, this is
// the single source of truth for the whole scene (lit geometry and skybox).
#ifndef TONEMAP_METHOD
#define TONEMAP_METHOD TONEMAP_NEUTRAL
#endif

/**
 * ACES Filmic Tone Mapping
 * Reference: https://knarkowicz.wordpress.com/2016/01/06/aces-filmic-tone-mapping-curve/
 */
vec3 tonemap_ACESFilm(vec3 x)
{
    const float a = 2.51;
    const float b = 0.03;
    const float c = 2.43;
    const float d = 0.59;
    const float e = 0.14;
    return clamp((x * (a * x + b)) / (x * (c * x + d) + e), 0.0, 1.0);
}

/**
 * Reinhard Tone Mapping
 */
vec3 tonemap_reinhard(vec3 x)
{
    return x / (1.0 + x);
}

/**
 * Reinhard2 Tone Mapping
 */
vec3 tonemap_reinhard2(vec3 x) 
{
    const float L_white = 4.0;
    return (x * (1.0 + x / (L_white * L_white))) / (1.0 + x);
}

/**
 * Khronos PBR Neutral Tone Mapper
 * https://github.com/KhronosGroup/ToneMapping/tree/main/PBR_Neutral
 */
vec3 tonemap_neutral(vec3 color) 
{
    const float startCompression = 0.8 - 0.04;
    const float desaturation = 0.15;

    float x = min(color.r, min(color.g, color.b));
    float offset = x < 0.08 ? x - 6.25 * x * x : 0.04;
    color -= offset;

    float peak = max(color.r, max(color.g, color.b));
    if (peak < startCompression) return color;

    const float d = 1.0 - startCompression;
    float newPeak = 1.0 - d * d / (peak + d - startCompression);
    color *= newPeak / peak;

    float g = 1.0 - 1.0 / (desaturation * (peak - newPeak) + 1.0);
    return mix(color, vec3(newPeak), g);
}

/**
 * Applies the tone mapping curve selected via TONEMAP_METHOD.
 *
 * Call this instead of the individual curves so every shader in the scene
 * resolves to the exact same mapping and can be switched from one place.
 */
vec3 apply_tonemap(vec3 color)
{
#if TONEMAP_METHOD == TONEMAP_NEUTRAL
    return tonemap_neutral(color);
#elif TONEMAP_METHOD == TONEMAP_ACES
    return tonemap_ACESFilm(color);
#elif TONEMAP_METHOD == TONEMAP_REINHARD
    return tonemap_reinhard(color);
#elif TONEMAP_METHOD == TONEMAP_REINHARD2
    return tonemap_reinhard2(color);
#else
    return color;
#endif
}

#endif