#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_scene;
uniform sampler2D u_depth;

uniform mat4 u_current_vp_inverse;
uniform mat4 u_previous_vp;
uniform float u_blur_strength;
uniform int u_num_samples;

void main()
{
    float depth = texture(u_depth, TexCoords).r;

    // reconstruct world position from depth
    vec4 ndc = vec4(TexCoords * 2.0 - 1.0, depth * 2.0 - 1.0, 1.0);
    vec4 worldPos = u_current_vp_inverse * ndc;
    worldPos /= worldPos.w;

    // reproject to previous frame
    vec4 prevClip = u_previous_vp * worldPos;
    vec2 prevUV = (prevClip.xy / prevClip.w) * 0.5 + 0.5;

    // velocity
    vec2 velocity = (TexCoords - prevUV) * u_blur_strength;

    // clamp velocity magnitude
    float speed = length(velocity);
    float maxSpeed = 0.05;
    if (speed > maxSpeed) {
        velocity = velocity / speed * maxSpeed;
    }

    // accumulate samples along velocity
    vec3 color = texture(u_scene, TexCoords).rgb;
    float totalWeight = 1.0;

    for (int i = 1; i < u_num_samples; ++i)
    {
        float t = float(i) / float(u_num_samples - 1) - 0.5;
        vec2 sampleUV = TexCoords + velocity * t;
        sampleUV = clamp(sampleUV, 0.0, 1.0);

        color += texture(u_scene, sampleUV).rgb;
        totalWeight += 1.0;
    }

    FragColor = vec4(color / totalWeight, 1.0);
}
