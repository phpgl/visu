#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_texture;
uniform float u_threshold;
uniform float u_soft_threshold;

void main()
{
    vec4 color = texture(u_texture, TexCoords);
    float brightness = dot(color.rgb, vec3(0.2126, 0.7152, 0.0722));

    // soft knee threshold
    float knee = u_threshold * u_soft_threshold;
    float soft = brightness - u_threshold + knee;
    soft = clamp(soft, 0.0, 2.0 * knee);
    soft = soft * soft / (4.0 * knee + 0.00001);

    float contribution = max(soft, brightness - u_threshold);
    contribution /= max(brightness, 0.00001);

    FragColor = color * contribution;
}
