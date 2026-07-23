/**
 * GBuffer Uniforms
 * ----------------------------------------------------------------------------
 */
#ifndef GBUFFER_UNIFORM_GLSL
#define GBUFFER_UNIFORM_GLSL

uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_albedo;
uniform sampler2D gbuffer_metallic;
uniform sampler2D gbuffer_roughness;
uniform sampler2D gbuffer_emissive;
uniform sampler2D gbuffer_ao;

struct GBuffer
{
    vec3 P;
    vec3 N;
    vec3 albedo;
    float metallic;
    float roughness;
    float ao;
    vec3 emissive;
};

/**
 * Fetches data from the GBuffer uniforms at the given UV coordinates.
 * 
 * Note: this really just gives you raw data, no normalisation or clamping is done.
 */
GBuffer gbuffer_make(vec2 uv)
{
    GBuffer gbuffer;

    gbuffer.P         = texture(gbuffer_position, uv).rgb;
    gbuffer.N         = texture(gbuffer_normal, uv).rgb;
    gbuffer.albedo    = texture(gbuffer_albedo, uv).rgb;
    gbuffer.metallic  = texture(gbuffer_metallic, uv).r;
    gbuffer.roughness = texture(gbuffer_roughness, uv).r;
    gbuffer.ao        = texture(gbuffer_ao, uv).r;
    gbuffer.emissive  = texture(gbuffer_emissive, uv).rgb;

    return gbuffer;
}

#endif
