#version 330 core

out vec4 FragColor;
in vec3 v_position;

uniform samplerCube u_env_cubemap;

#include "visu/constants.glsl"

void main()
{		
    vec3 N = normalize(v_position);

    vec3 irradiance = vec3(0.0);   
    
    // tangent space calculation from origin point
    vec3 up    = vec3(0.0, 1.0, 0.0);
    vec3 right = normalize(cross(up, N));
    up         = normalize(cross(N, right));
       
    float sampleDelta = 0.025;
    float samples = 0.0f;
    for(float phi = 0.0; phi < 2.0 * PI; phi += sampleDelta)
    {
        for(float theta = 0.0; theta < 0.5 * PI; theta += sampleDelta)
        {
            // spherical to cartesian (in tangent space)
            vec3 tangentSample = vec3(sin(theta) * cos(phi),  sin(theta) * sin(phi), cos(theta));
            // tangent space to world
            vec3 sample = tangentSample.x * right + tangentSample.y * up + tangentSample.z * N; 

            irradiance += texture(u_env_cubemap, sample).rgb * cos(theta) * sin(theta);
            samples++;
        }
    }
    irradiance = PI * irradiance * (1.0 / float(samples));
    
    FragColor = vec4(irradiance, 1.0);
}