#version 330 core

layout (location = 0) out vec3 fragment_color;

uniform vec3 color;

void main()
{
    fragment_color = color;
}