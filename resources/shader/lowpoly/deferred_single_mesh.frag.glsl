#version 330 core

#include "visu/gbuffer_layout.glsl"

in vec3 v_normal;
in vec3 v_position;

uniform vec3 color;

void main()
{
    // basic phong lighting
    vec3 lightDir = normalize(vec3(0.0f, 1.0f, 1.0f));
    float diffuse = max(dot(v_normal, lightDir), 0.0f);

    gbuffer_albedo = vec4(color, 1.0f) * diffuse;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
}