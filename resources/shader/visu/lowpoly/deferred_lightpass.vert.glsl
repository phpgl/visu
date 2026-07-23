#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec2 a_texture_cords;

out vec2 v_uv;

void main()
{
    v_uv = a_texture_cords;
    gl_Position = vec4(a_position, 1.0);
}