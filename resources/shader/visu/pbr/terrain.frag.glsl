#version 330 core

#include "visu/gbuffer_layout_pbr.glsl"

in vec3 v_position;
in vec4 v_vposition;
in vec3 v_normal;
in vec2 v_uv;
in mat3 v_tbn;

// blend map: R = layer 0, G = layer 1, B = layer 2, A = layer 3
uniform sampler2D u_blend_map;

// terrain layer textures
uniform sampler2D u_layer_0;
uniform sampler2D u_layer_1;
uniform sampler2D u_layer_2;
uniform sampler2D u_layer_3;

// tiling for each layer
uniform vec4 u_layer_tiling; // x,y,z,w = tiling for layers 0-3

// which layers are active (bitmask)
uniform int u_active_layers;

// material properties
uniform float u_metallic;
uniform float u_roughness;

void main()
{
    vec4 blend = texture(u_blend_map, v_uv);

    // normalize blend weights (in case they don't sum to 1)
    float total = blend.r + blend.g + blend.b + blend.a;
    if (total > 0.001) {
        blend /= total;
    } else {
        blend = vec4(1.0, 0.0, 0.0, 0.0); // fallback to layer 0
    }

    // sample each layer at tiled UVs and blend
    vec4 albedo = vec4(0.0);

    if ((u_active_layers & 1) != 0) {
        albedo += texture(u_layer_0, v_uv * u_layer_tiling.x) * blend.r;
    }
    if ((u_active_layers & 2) != 0) {
        albedo += texture(u_layer_1, v_uv * u_layer_tiling.y) * blend.g;
    }
    if ((u_active_layers & 4) != 0) {
        albedo += texture(u_layer_2, v_uv * u_layer_tiling.z) * blend.b;
    }
    if ((u_active_layers & 8) != 0) {
        albedo += texture(u_layer_3, v_uv * u_layer_tiling.w) * blend.a;
    }

    // fallback: if no layers active, use blend map as color
    if (u_active_layers == 0) {
        albedo = vec4(0.3, 0.5, 0.2, 1.0); // default green
    }

    vec3 N = normalize(v_normal);

    // write to GBuffer
    gbuffer_position = v_position;
    gbuffer_vposition = v_vposition.xyz;
    gbuffer_normal = N;
    gbuffer_albedo = albedo;
    gbuffer_metallic_roughness = vec2(u_metallic, u_roughness);
    gbuffer_emissive = vec3(0.0);
}
