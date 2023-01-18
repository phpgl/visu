#version 330 core

in vec2 v_uv;

uniform sampler2D ssao_noisy;

out float fragment_ao;

void main() 
{
    vec2 texel_size = 1.0 / vec2(textureSize(ssao_noisy, 0));
    float result = 0.0;
    for (int x = -2; x < 2; ++x) 
    {
        for (int y = -2; y < 2; ++y) 
        {
            vec2 offset = vec2(float(x), float(y)) * texel_size;
            result += texture(ssao_noisy, v_uv + offset).r;
        }
    }

    fragment_ao = result / (4.0 * 4.0);
}  