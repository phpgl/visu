#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;
layout (location = 2) in vec3 a_color;
layout (location = 3) in float a_roughness;
layout (location = 4) in float a_metallic;
layout (location = 5) in vec3 a_emissive;
layout (location = 6) in mat4 a_model;

out vec3 v_normal;
out vec3 v_position;
out vec4 v_vposition;
out vec3 v_color;
out float v_roughness;
out float v_metallic;
out vec3 v_emissive;

uniform mat4 projection;
uniform mat4 view;

void main()
{
    vec4 world_pos = a_model * vec4(a_position, 1.0);
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;
    v_color = a_color;
    v_roughness = a_roughness;
    v_metallic = a_metallic;
    v_emissive = a_emissive;

    vec3 n = normalize(mat3(a_model) * a_normal);
    
    v_normal = n;
    
    gl_Position = projection * view * world_pos;
}