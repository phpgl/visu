#version 330 core

layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;
layout (location = 2) in vec2 a_uv;
layout (location = 3) in vec4 a_tangent;

out vec3 v_position;
out vec4 v_vposition;
out vec3 v_normal;
out vec2 v_uv;
out mat3 v_tbn;

uniform mat4 projection;
uniform mat4 view;
uniform mat4 model;

void main()
{
    vec4 world_pos = model * vec4(a_position, 1.0);
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;

    mat3 normal_matrix = mat3(model);
    vec3 N = normalize(normal_matrix * a_normal);
    vec3 T = normalize(normal_matrix * a_tangent.xyz);
    T = normalize(T - dot(T, N) * N);
    vec3 B = cross(N, T) * a_tangent.w;
    v_tbn = mat3(T, B, N);

    v_normal = N;
    v_uv = a_uv;

    gl_Position = projection * view * world_pos;
}
