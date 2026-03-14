#version 330 core

layout (location = 0) in vec3 a_position;

uniform mat4 model;
uniform mat4 u_light_space;

out vec3 v_world_pos;

void main()
{
    vec4 worldPos = model * vec4(a_position, 1.0);
    v_world_pos = worldPos.xyz;
    gl_Position = u_light_space * worldPos;
}
