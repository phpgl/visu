#version 330 core

#include "visu/gbuffer_layout.glsl"

in vec3 v_normal;
in vec3 v_position;
in vec4 v_vposition;

uniform vec3 color;

void main()
{
    gbuffer_albedo = color;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
    gbuffer_vposition = v_vposition.xyz;
}