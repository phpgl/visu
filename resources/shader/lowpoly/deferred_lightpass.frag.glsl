#version 330 core

in vec2 v_texture_cords;

out vec4 fragment_color;

uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_albedo;

void main()
{             
    // retrieve data from gbuffer
    vec3 buffer_pos = texture(gbuffer_position, v_texture_cords).rgb;
    vec3 buffer_normal = texture(gbuffer_normal, v_texture_cords).rgb;
    vec3 buffer_albedo = texture(gbuffer_albedo, v_texture_cords).rgb;

    // blinn-phong lighting
    vec3 light_dir = normalize(vec3(-1.0, 1.0, 0.0));
    vec3 light_color = vec3(1.0, 1.0, 1.0);
    vec3 view_dir = normalize(vec3(0.0, 0.0, 1.0));
    vec3 half_dir = normalize(light_dir + view_dir);
    float diffuse = max(dot(buffer_normal, light_dir), 0.0);
    float specular = pow(max(dot(buffer_normal, half_dir), 0.0), 32.0);
    vec3 color = buffer_albedo * (diffuse * light_color) + specular * light_color;

    // some distance fog (depth needs to be linearized)
    float depth = texture(gbuffer_depth, v_texture_cords).r;
    // linerize depth
    float near = 0.1;
    float far = 1000.0;
    depth = near * far / (far - depth * (far - near));
    depth = depth / far;

    if (depth < 0.9) {
        color = mix(color, vec3(1.0, 1.0, 1.0), depth);
    }

    // output to screen
    fragment_color = vec4(color, 1.0);
}