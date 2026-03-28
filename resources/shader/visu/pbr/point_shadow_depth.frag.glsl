#version 330 core

in vec3 v_world_pos;

uniform vec3 u_light_pos;
uniform float u_far_plane;

void main()
{
    // write linear distance normalized by far plane
    float dist = length(v_world_pos - u_light_pos);
    gl_FragDepth = dist / u_far_plane;
}
