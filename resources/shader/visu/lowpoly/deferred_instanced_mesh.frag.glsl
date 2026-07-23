#version 330 core

#include "visu/gbuffer_layout.glsl"

in vec3 v_normal;
in vec3 v_position;
in vec4 v_vposition;
in vec3 v_color;
in float v_roughness;
in float v_metallic;
in vec3 v_emissive;

void main()
{
    gbuffer_albedo = v_color;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
    gbuffer_vposition = v_vposition.xyz;
    gbuffer_metallic = v_metallic;
    gbuffer_roughness = v_roughness;
    gbuffer_emissive = v_emissive;
}