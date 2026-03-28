#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_scene;
uniform sampler2D u_depth;
uniform sampler2D u_blurred;

uniform float u_focus_distance;
uniform float u_focus_range;
uniform float u_near_plane;
uniform float u_far_plane;
uniform float u_max_blur;

float linearizeDepth(float d)
{
    float z_ndc = d * 2.0 - 1.0;
    return (2.0 * u_near_plane * u_far_plane) / (u_far_plane + u_near_plane - z_ndc * (u_far_plane - u_near_plane));
}

void main()
{
    float depth = texture(u_depth, TexCoords).r;
    float linearDepth = linearizeDepth(depth);

    // circle of confusion based on distance from focus plane
    float coc = abs(linearDepth - u_focus_distance) / u_focus_range;
    coc = clamp(coc, 0.0, u_max_blur);

    vec3 sharp = texture(u_scene, TexCoords).rgb;
    vec3 blurred = texture(u_blurred, TexCoords).rgb;

    FragColor = vec4(mix(sharp, blurred, coc), 1.0);
}
