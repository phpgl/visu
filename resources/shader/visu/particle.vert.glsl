#version 330 core

// quad vertices (shared geometry)
layout (location = 0) in vec2 a_quad_pos; // [-0.5, 0.5]

// per-instance data
layout (location = 1) in vec3 i_position;  // world position
layout (location = 2) in vec4 i_color;     // RGBA
layout (location = 3) in float i_size;     // billboard size

uniform mat4 u_view;
uniform mat4 u_projection;

out vec4 v_color;
out vec2 v_uv;

void main()
{
    v_color = i_color;
    v_uv = a_quad_pos + 0.5; // [0, 1]

    // extract camera right and up from view matrix for billboarding
    vec3 cam_right = vec3(u_view[0][0], u_view[1][0], u_view[2][0]);
    vec3 cam_up    = vec3(u_view[0][1], u_view[1][1], u_view[2][1]);

    vec3 world_pos = i_position
        + cam_right * a_quad_pos.x * i_size
        + cam_up    * a_quad_pos.y * i_size;

    gl_Position = u_projection * u_view * vec4(world_pos, 1.0);
}
