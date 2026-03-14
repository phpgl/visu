#version 330 core

in vec4 v_color;
in vec2 v_uv;

out vec4 fragment_color;

uniform sampler2D u_texture;
uniform int u_has_texture;

void main()
{
    vec4 color = v_color;

    if (u_has_texture == 1) {
        color *= texture(u_texture, v_uv);
    } else {
        // soft circular falloff for untextured particles
        float dist = length(v_uv - vec2(0.5));
        float alpha = 1.0 - smoothstep(0.3, 0.5, dist);
        color.a *= alpha;
    }

    if (color.a < 0.01) discard;

    fragment_color = color;
}
