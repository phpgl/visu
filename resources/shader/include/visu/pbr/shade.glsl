#ifndef PBR_SHADE_GLSL
#define PBR_SHADE_GLSL

#include "visu/functions/brdf.glsl"
#include "visu/pbr/surface.glsl"
#include "visu/constants.glsl"

vec3 pbr_shade(in PBRSurface s, vec3 L, vec3 radiance)
{
    float NdotL = max(dot(s.N, L), 0.0);
    // if (NdotL <= 0.0) return vec3(0.0);

    vec3 H = normalize(s.V + L);
    float VdotH = max(dot(s.V, H), 0.0);

    // energy conservation
    vec3 F;
    vec3 spec = pbr_specular(s.N, s.V, H, L, s.F0, s.roughness, F);
    vec3 kS = F;
    vec3 kD = (vec3(1.0) - kS) * (1.0 - s.metallic);
    vec3 diff = kD * s.albedo / PI;

    return (diff + spec) * radiance * NdotL;
}

#endif