#version 330 core

#include "visu/gbuffer_layout.glsl"

in vec3 v_normal;
in vec3 v_position;

uniform vec3 color;

void main()
{
    gbuffer_albedo = color;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
}