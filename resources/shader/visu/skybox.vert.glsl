#version 330 core

layout (location = 0) in vec3 a_pos;

out vec3 tex_coords;

uniform mat4 u_projection;
uniform mat4 u_view;

void main()
{
    tex_coords = a_pos;
    // drop translation, we are in the skybox
    mat4 view = mat4(mat3(u_view));
    vec4 pos = u_projection * view * vec4(a_pos, 1.0);
    // force the skybox to the far plane so it only fills empty depth
    gl_Position = pos.xyww;
}
