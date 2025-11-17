#version 330 core

// constants to avoid depth comparison artifacts
#define CAP_MIN_DISTANCE 0.0001
#define CAP_MAX_DISTANCE 0.005

#ifndef SSAO_MAX_SAMPLES
    #define SSAO_MAX_SAMPLES 64
#endif
#ifndef SSAO_NOISE_TEXTURE_SIZE
    #define SSAO_NOISE_TEXTURE_SIZE 4
#endif

out float frag_ao;

in vec2 v_uv;

uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_normal;
uniform sampler2D noise_texture;

uniform vec3 samples[SSAO_MAX_SAMPLES];

uniform int sample_count = SSAO_MAX_SAMPLES;
uniform float radius = 0.5;
uniform float bias = 0.025;
uniform float strength = 6.0;

uniform ivec2 screen_size;
uniform mat4 projection;
uniform mat4 inverse_projection;
uniform mat4 normal_matrix;

vec4 getViewPos(vec2 texCoord)
{
    // calculate view space position from depth texture
    float x = texCoord.s * 2.0 - 1.0;
    float y = texCoord.t * 2.0 - 1.0;
    
    // get depth from depth buffer and convert to NDC
    float z = texture(gbuffer_depth, texCoord).r * 2.0 - 1.0;
    
    vec4 posProj = vec4(x, y, z, 1.0);
    
    vec4 posView = inverse_projection * posProj;
    
    posView /= posView.w;
    
    return posView;
}

void main()
{
    vec2 noise_size = vec2(screen_size) / float(SSAO_NOISE_TEXTURE_SIZE);

    // get depth value to check if there's geometry
    float depth = texture(gbuffer_depth, v_uv).r;
    
    // skip pixels with no geometry (far plane)
    if (depth >= 1.0) {
        frag_ao = 1.0;
        return;
    }

    // calculate view space position from depth
    vec4 view_position = getViewPos(v_uv);

    // get world normal and convert to view space
    vec3 world_normal = texture(gbuffer_normal, v_uv).xyz;
    vec3 view_normal = normalize(mat3(normal_matrix) * world_normal);

    // get noise vector for kernel rotation
    vec3 noise_vec = normalize(texture(noise_texture, v_uv * noise_size).xyz * 2.0 - 1.0);

    // use Gram-Schmidt process to get orthogonal tangent vector
    vec3 tangent = normalize(noise_vec - dot(noise_vec, view_normal) * view_normal);
    vec3 bitangent = cross(view_normal, tangent);
    mat3 TBN = mat3(tangent, bitangent, view_normal);
    
    float occlusion = 0.0;
    
    for(int i = 0; i < sample_count; ++i)
    {
        // reorient sample vector in view space
        vec3 sample_vec = TBN * samples[i];
        
        // calculate sample point in view space
        vec4 sample_position = view_position + radius * vec4(sample_vec, 0.0);
        
        // project sample position to screen space
        vec4 sample_ndc = projection * sample_position;
        sample_ndc /= sample_ndc.w;
        
        // convert to texture coordinates
        vec2 sample_uv = sample_ndc.xy * 0.5 + 0.5;
        
        // // skip samples outside screen
        // if (sample_uv.x < 0.0 || sample_uv.x > 1.0 || sample_uv.y < 0.0 || sample_uv.y > 1.0) {
        //     continue;
        // }
        
        // get scene depth at sample location
        float scene_depth_ndc = texture(gbuffer_depth, sample_uv).r * 2.0 - 1.0;
        
        // calculate depth difference in NDC space
        float delta = sample_ndc.z - scene_depth_ndc;
        
        // occlusion test with distance caps to avoid artifacts
        if (delta > CAP_MIN_DISTANCE && delta < CAP_MAX_DISTANCE) {
            occlusion += 1.0;
        }
    }

    // normalize occlusion and invert (no occlusion = white, full occlusion = black)
    occlusion = 1.0 - occlusion / float(sample_count);
    
    // apply strength
    frag_ao = pow(occlusion, strength);
}



