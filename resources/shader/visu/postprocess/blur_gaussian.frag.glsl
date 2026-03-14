#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_texture;
uniform vec2 u_direction; // (1/w, 0) or (0, 1/h)

// 9-tap Gaussian weights
const float weights[5] = float[](0.227027, 0.1945946, 0.1216216, 0.054054, 0.016216);

void main()
{
    vec3 result = texture(u_texture, TexCoords).rgb * weights[0];

    for (int i = 1; i < 5; ++i)
    {
        vec2 offset = u_direction * float(i);
        result += texture(u_texture, TexCoords + offset).rgb * weights[i];
        result += texture(u_texture, TexCoords - offset).rgb * weights[i];
    }

    FragColor = vec4(result, 1.0);
}
