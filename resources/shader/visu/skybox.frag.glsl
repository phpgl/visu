#version 330 core

out vec4 frag_color;

in vec3 tex_coords;

uniform samplerCube u_skybox;

#include "visu/functions/tone_mapping.glsl"
#include "visu/functions/gamma_corr.glsl"

void main()
{
    // the environment cubemap holds linear HDR radiance, tone map and gamma
    // correct it with the exact same curve the deferred light pass uses so the
    // skybox and the lit geometry (and its reflections) read consistently.
    vec3 color = texture(u_skybox, tex_coords).rgb;
    color = apply_tonemap(color);
    color = gamma_correct(color);

    frag_color = vec4(color, 1.0);
}
