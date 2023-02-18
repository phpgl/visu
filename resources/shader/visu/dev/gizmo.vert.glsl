#version 330 core

layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;
layout (location = 2) in vec3 a_color;

out vec3 v_normal;
out vec3 v_color;

uniform mat4 model;
uniform mat4 view;
uniform mat4 projection;

void main()
{
    vec4 world_pos = model * vec4(a_position, 1.0);
    vec3 n = normalize(mat3(model) * a_normal);
    v_normal = n;
    v_color = a_color;

    gl_Position = projection * view * world_pos;
}