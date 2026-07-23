/**
 * Common tone mapping functions
 * ----------------------------------------------------------------------------
 */

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