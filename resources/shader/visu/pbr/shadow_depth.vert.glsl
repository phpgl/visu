#version 330 core

layout (location = 0) in vec3 a_position;

uniform mat4 u_light_space;
uniform mat4 model;

void main()
{
    gl_Position = u_light_space * model * vec4(a_position, 1.0);
}
