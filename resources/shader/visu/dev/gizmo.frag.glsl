#version 330 core

layout (location = 0) out vec3 fragment_color;

in vec3 v_color;

void main()
{
    fragment_color = v_color;
}