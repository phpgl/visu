#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_scene;
uniform sampler2D u_bloom;
uniform float u_bloom_intensity;

void main()
{
    vec3 scene = texture(u_scene, TexCoords).rgb;
    vec3 bloom = texture(u_bloom, TexCoords).rgb;

    FragColor = vec4(scene + bloom * u_bloom_intensity, 1.0);
}
