#ifndef PBR_SURFACE_GLSL
#define PBR_SURFACE_GLSL

struct PBRSurface
{
    vec3 P;
    vec3 N;
    vec3 V;

    vec3 albedo;
    float metallic;
    float roughness;
    float ao;

    vec3 emissive;
    vec3 F0;
};


PBRSurface pbr_surface_make(
    GBuffer gbuffer,
    vec3 camera_position
)
{
    PBRSurface s;

    s.P = gbuffer.P;
    s.N = normalize(gbuffer.N);
    s.V = normalize(camera_position - s.P);

    s.albedo    = max(gbuffer.albedo, vec3(0.0));
    s.metallic  = clamp(gbuffer.metallic, 0.0, 1.0);
    s.roughness = clamp(gbuffer.roughness, 0.04, 1.0);
    s.ao        = clamp(gbuffer.ao, 0.0, 1.0);
    s.emissive  = gbuffer.emissive;

    s.F0 = mix(vec3(0.04), s.albedo, s.metallic);

    return s;
}

#endif
