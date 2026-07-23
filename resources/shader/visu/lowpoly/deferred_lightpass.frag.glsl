#version 330 core

// Tone mapping
// ----------------------------------------------------------------------------
//
// the tone mapping method and curves live in visu/functions/tone_mapping.glsl
// (included below). select a method by defining TONEMAP_METHOD as a shader
// option; it defaults to the Khronos PBR neutral mapper.


// Gamma correction
// ----------------------------------------------------------------------------
//
// The gamme value itself can changed by setting the "u_display_gamma" uniform.
#ifndef SHOULD_CORRECT_GAMMA
#define SHOULD_CORRECT_GAMMA 1
#endif


in vec2 v_uv;
out vec4 fragment_color;

#include "visu/functions/gamma_corr.glsl"
#include "visu/functions/tone_mapping.glsl"
#include "visu/functions/brdf.glsl"

#include "visu/gbuffer_uniform.glsl"
#include "visu/pbr/surface.glsl"
#include "visu/pbr/shade.glsl"

// camera uniforms
uniform vec3 camera_position;
uniform vec2 camera_resolution;

// sun uniforms
uniform vec3 sun_direction;
uniform vec3 sun_color;
uniform float sun_intensity;

// environment cubemap
#ifdef USE_ENV_CUBEMAP
uniform samplerCube environment_cubemap;
uniform int environment_mip_count;
#endif

// IBL 
#ifdef USE_IBL
uniform samplerCube ibl_irradiance_map;
uniform samplerCube ibl_prefilter_map;
uniform int prefilter_mip_count;
uniform sampler2D ibl_brdf_lut;
#endif

void main()
{
    // fetch gbuffer data
    GBuffer gbuffer = gbuffer_make(v_uv);

    // create PBR surface from gbuffer data
    PBRSurface s = pbr_surface_make(
        gbuffer,
        camera_position
    );

    vec3 Lo = vec3(0.0);

    // directional light (sun)
    {
        vec3 L = normalize(-sun_direction);
        vec3 radiance = sun_color * sun_intensity;
        Lo += pbr_shade(s, L, radiance);
    }

    // ambient / IBL
    vec3 ambient = vec3(0.0);
    {
        float NdotV = max(dot(s.N, s.V), 0.0);
        vec3 R = normalize(reflect(-s.V, s.N));

        vec3 F  = fresnel_schlick_roughness(s.F0, NdotV, s.roughness);
        vec3 kS = F;
        vec3 kD = (vec3(1.0) - kS) * (1.0 - s.metallic);

        // diffuse IBL
        vec3 diffuseIBL = vec3(0.0);
#ifdef USE_IBL
        vec3 irradiance = texture(ibl_irradiance_map, s.N).rgb;
        diffuseIBL = irradiance * s.albedo / PI;
#else
        // basic ambient fallback
        diffuseIBL = vec3(0.03) * s.albedo;
#endif

        // specular IBL
        vec3 specIBL = vec3(0.0);
#ifdef USE_IBL
        float maxLod = float(prefilter_mip_count - 1);
        vec3 prefiltered = textureLod(ibl_prefilter_map, R, s.roughness * maxLod).rgb;
        vec2 brdf = texture(ibl_brdf_lut, vec2(NdotV, s.roughness)).rg;
        specIBL = prefiltered * (s.F0 * brdf.x + brdf.y);
#elif defined(USE_ENV_CUBEMAP)
        float maxLod = float(max(environment_mip_count - 1, 0));
        vec3 env = textureLod(environment_cubemap, R, s.roughness * maxLod).rgb;
        specIBL = env * F; // fallback without proper LUT
#endif

        float specAO = s.ao;
        ambient = kD * diffuseIBL * s.ao + specIBL * specAO;
    }

    // compose lighting, ambient and emissive
    vec3 color = Lo + ambient + s.emissive;

    // tone mapping
    color = apply_tonemap(color);

#if SHOULD_CORRECT_GAMMA
    color = gamma_correct(color);
#endif

    fragment_color = vec4(color, 1.0);
}
