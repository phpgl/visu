#version 330 core

out float frag_ao;

in vec2 v_uv;

uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D noise_texture;

uniform vec3 samples[64];

uniform int kernel_size = 64;
uniform float radius = 0.5;
uniform float bias = 0.025;
uniform float strength = 6.0;

uniform ivec2 screen_size;
uniform mat4 projection;
uniform mat4 normal_matrix;

void main()
{
    vec2 noise_size = screen_size / 4; // 4x4 noise texture

    vec4 view_position = texture(gbuffer_position, v_uv);

    vec3 world_normal = texture(gbuffer_normal, v_uv).xyz;
    vec3 view_normal = normalize(mat3(normal_matrix) * world_normal);

    vec3 noise_vec = normalize(texture(noise_texture, v_uv * noise_size).xyz);

    vec3 tangent = normalize(noise_vec - view_normal * dot(noise_vec, view_normal));
    vec3 bitangent = cross(view_normal, tangent);
    mat3 TBN = mat3(tangent, bitangent, view_normal);
    
    float occlusion = 0.0;
    for(int i = 0; i < kernel_size; ++i)
    {
        vec3 sample_position = TBN * samples[i];
        sample_position = view_position.xyz + sample_position * radius; 
        
        vec4 offset = vec4(sample_position, 1.0);
        offset = projection * offset;
        offset.xyz /= offset.w;
        offset.xyz = offset.xyz * 0.5 + 0.5;
        
        float sample_depth = texture(gbuffer_position, offset.xy).z;
        
        // range check
        float rangeCheck = smoothstep(0.0, 1.0, radius / abs(view_position.z - sample_depth));
        occlusion += (sample_depth >= sample_position.z + bias ? 1.0 : 0.0) * rangeCheck;           
    }

    occlusion = 1.0 - (occlusion / kernel_size);
    frag_ao = pow(occlusion, strength);
}



