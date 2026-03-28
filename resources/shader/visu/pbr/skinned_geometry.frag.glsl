#version 330 core

#include "visu/gbuffer_layout_pbr.glsl"

in vec3 v_position;
in vec4 v_vposition;
in vec3 v_normal;
in vec2 v_uv;
in mat3 v_tbn;

// material uniforms
uniform vec4 u_albedo_color;
uniform float u_metallic;
uniform float u_roughness;
uniform vec3 u_emissive_color;

// texture flags (bitmask)
uniform int u_texture_flags;

// textures
uniform sampler2D u_albedo_map;        // flag bit 0
uniform sampler2D u_normal_map;        // flag bit 1
uniform sampler2D u_metallic_roughness_map; // flag bit 2
uniform sampler2D u_ao_map;            // flag bit 3
uniform sampler2D u_emissive_map;      // flag bit 4

void main()
{
    // albedo
    vec4 albedo = u_albedo_color;
    if ((u_texture_flags & 1) != 0) {
        albedo *= texture(u_albedo_map, v_uv);
    }

    // alpha test for MASK mode
    // (alphaCutoff is baked into the check on CPU side by not rendering if below)

    // normal
    vec3 N = normalize(v_normal);
    if ((u_texture_flags & 2) != 0) {
        vec3 tangent_normal = texture(u_normal_map, v_uv).rgb * 2.0 - 1.0;
        N = normalize(v_tbn * tangent_normal);
    }

    // metallic + roughness
    float metallic = u_metallic;
    float roughness = u_roughness;
    if ((u_texture_flags & 4) != 0) {
        vec4 mr = texture(u_metallic_roughness_map, v_uv);
        metallic *= mr.b;   // glTF convention: blue channel = metallic
        roughness *= mr.g;  // glTF convention: green channel = roughness
    }

    // emissive
    vec3 emissive = u_emissive_color;
    if ((u_texture_flags & 16) != 0) {
        emissive *= texture(u_emissive_map, v_uv).rgb;
    }

    // write to GBuffer
    gbuffer_position = v_position;
    gbuffer_vposition = v_vposition.xyz;
    gbuffer_normal = N;
    gbuffer_albedo = albedo;
    gbuffer_metallic_roughness = vec2(metallic, roughness);
    gbuffer_emissive = emissive;
}
