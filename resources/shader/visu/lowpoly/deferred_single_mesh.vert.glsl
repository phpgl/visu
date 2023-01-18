#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;

out vec3 v_normal;
out vec3 v_position;
out vec4 v_vposition;

uniform mat4 projection;
uniform mat4 view;
uniform mat4 model;

void main()
{
    vec4 world_pos = model * vec4(a_position, 1.0);
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;

    vec3 n = normalize(mat3(model) * a_normal);
    
    v_normal = n;
    
    gl_Position = projection * view * world_pos;
}