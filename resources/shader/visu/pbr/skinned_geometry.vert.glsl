#version 330 core

#define MAX_BONES 128

layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;
layout (location = 2) in vec2 a_uv;
layout (location = 3) in vec4 a_tangent; // xyz = tangent, w = handedness
layout (location = 4) in vec4 a_bone_indices; // stored as float, cast to int
layout (location = 5) in vec4 a_bone_weights;

out vec3 v_position;
out vec4 v_vposition;
out vec3 v_normal;
out vec2 v_uv;
out mat3 v_tbn;

uniform mat4 projection;
uniform mat4 view;
uniform mat4 model;
uniform mat4 u_bone_matrices[MAX_BONES];
uniform int u_skinned; // 1 = apply skinning, 0 = static mesh

void main()
{
    vec4 local_pos = vec4(a_position, 1.0);
    vec3 local_normal = a_normal;
    vec3 local_tangent = a_tangent.xyz;

    if (u_skinned == 1) {
        ivec4 bone_ids = ivec4(a_bone_indices);
        vec4 w = a_bone_weights;

        mat4 bone_transform = u_bone_matrices[bone_ids.x] * w.x
                            + u_bone_matrices[bone_ids.y] * w.y
                            + u_bone_matrices[bone_ids.z] * w.z
                            + u_bone_matrices[bone_ids.w] * w.w;

        local_pos = bone_transform * local_pos;
        local_normal = mat3(bone_transform) * local_normal;
        local_tangent = mat3(bone_transform) * local_tangent;
    }

    vec4 world_pos = model * local_pos;
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;

    mat3 normal_matrix = mat3(model);
    vec3 N = normalize(normal_matrix * local_normal);
    vec3 T = normalize(normal_matrix * local_tangent);
    T = normalize(T - dot(T, N) * N);
    vec3 B = cross(N, T) * a_tangent.w;
    v_tbn = mat3(T, B, N);

    v_normal = N;
    v_uv = a_uv;

    gl_Position = projection * view * world_pos;
}
