#version 330 core

layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;

#include "visu/matrix_operations.glsl"

out vec3 v_normal;

uniform mat4 model;
uniform mat4 view;
uniform vec3 view_position;
uniform mat4 projection;

void main()
{
    // distance from model translation to view_position
    float dist = distance(model[3].xyz, view_position);

    // scale the model by the distance
    mat4 scaled_model = scale_matrix(model, vec3(dist) / 96);

    vec4 world_pos = scaled_model * vec4(a_position, 1.0);
    vec3 n = normalize(mat3(scaled_model) * a_normal);
    v_normal = n;

    gl_Position = projection * view * world_pos;
}