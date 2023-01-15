#version 330 core

/**
 * The PBR Distrubition & Geometry functions
 * 
 *     PBR_DISTRIBUTION_GGX
 *     PBR_DISTRIBUTION_BECKMANN
 * 
 *     PBR_GEOMETRY_SCHLICK
 *     PBR_GEOMETRY_COOK_TORRANCE
 *     PBR_GEOMETRY_KELEMAN
 */
#define PBR_DISTRIBUTION_GGX
#define PBR_GEOMETRY_SCHLICK

in vec2 v_texture_cords;
out vec4 fragment_color;

uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_albedo;

// camera uniforms
uniform vec3 camera_position;
uniform vec2 camera_resolution;

// sun uniforms
uniform vec3 sun_direction;
uniform vec3 sun_color;
uniform float sun_intensity;

const float PI = 3.14159265359;

vec3 fresnel(vec3 albedo, float metalness, float b) 
{
    vec3 F0 = mix(vec3(0.04), albedo, metalness);
    return F0 + (1.0 - F0) * pow(1.0 - b, 5.0);
}

float GGX(float NdotH, float roughness) 
{
    float a = roughness * roughness;
    float a2 = a * a;
    float d = NdotH * NdotH * (a2 - 1.0) + 1.0;
    return a2 / (PI * d * d);
}

float distribution_beckmann(float NdotH, float roughness) 
{
    float a = roughness * roughness;
    float a2 = a * a;
    float r1 = 1.0 / (4.0 * a2 * pow(NdotH, 4.0));
    float r2 = (NdotH * NdotH - 1.0) / (a2 * NdotH * NdotH);
    return r1 * exp(r2);
}

float geometry_schlick(float NdotL, float NdotV, float roughness) 
{
    float a = roughness + 1.0;
    float k = a * a * 0.125;
    float G1 = NdotL / (NdotL * (1.0 - k) + k);
    float G2 = NdotV / (NdotV * (1.0 - k) + k);
    return G1 * G2;
}

float geometry_cook_torrance(float NdotL, float NdotV, float NdotH, float VdotH) 
{
    float G1 = (2.0 * NdotH * NdotV) / VdotH;
    float G2 = (2.0 * NdotH * NdotL) / VdotH;
    return min(1.0, min(G1, G2));
}

float geometry_kelman(float NdotL, float NdotV, float VdotH) 
{
    return (NdotL * NdotV) / (VdotH * VdotH);
}

vec3 cook_torrance(vec3 N, vec3 V, vec3 H, vec3 L, vec3 albedo, float metalness, float roughness) 
{
    float NdotH = max(0.0, dot(N, H));
    float NdotV = max(1e-7, dot(N, V));
    float NdotL = max(1e-7, dot(N, L));
    float VdotH = max(0.0, dot(V, H));

#ifdef PBR_DISTRIBUTION_GGX
    float D = GGX(NdotH, roughness);
#endif
#ifdef PBR_DISTRIBUTION_BECKMANN
    float D = distribution_beckmann(NdotH, roughness);
#endif
    
#ifdef PBR_GEOMETRY_SCHLICK
    float G = geometry_schlick(NdotL, NdotV, roughness);
#endif
#ifdef PBR_GEOMETRY_COOK_TORRANCE
    float G = geometry_cook_torrance(NdotL, NdotV, NdotH, VdotH);
#endif
#ifdef PBR_GEOMETRY_KELEMAN
    float G = geometry_kelman(NdotL, NdotV, VdotH);
#endif
    
    vec3 F = fresnel(albedo, metalness, VdotH);
    
    return (D * F * G) / (4.0 * NdotL * NdotV);
}

void main()
{             
    // retrieve data from gbuffer
    vec3 buffer_pos = texture(gbuffer_position, v_texture_cords).rgb;
    vec3 buffer_normal = texture(gbuffer_normal, v_texture_cords).rgb;
    vec3 buffer_albedo = texture(gbuffer_albedo, v_texture_cords).rgb;
    vec3 buffer_emissive = vec3(0.0);
    float buffer_metal = 0.0;
    float buffer_roughness = 0.5;
    float buffer_ao = 1.0f;

    // lighting
    vec3 N = normalize(buffer_normal);
    vec3 V = normalize(camera_position - buffer_pos);
    vec3 L = normalize(sun_direction);
    vec3 R = normalize(reflect(-L, N));

    vec3 Lo = sun_color * sun_intensity;
    vec3 radiance = vec3(0.0f);
    float visibility = 1;
    float attenuation = 1;

    float inverse_metal = 1.0f - buffer_metal;

    vec3 lambertBRDF = (buffer_albedo / PI) * inverse_metal;

    attenuation = max(0, dot(N, L));
    
    // @todo calculate visibility
    // shadows ...

    vec3 H = normalize(L + V);

    vec3 CookBRDF = clamp(cook_torrance(N, V, H, L, buffer_albedo, buffer_metal, buffer_roughness), 0, 1);

    radiance += (lambertBRDF + CookBRDF) * Lo * attenuation;

    // ambient
    vec3 ambient_diffuse_color = buffer_albedo * inverse_metal;
    vec3 specular_color = mix(vec3(0.04), buffer_albedo, buffer_metal);

    // we currently have no probes setup 
    // vec3 irradiance = texture(env_irradiance, N).rgb;
    // so we simulate irradiance with ambient color
    vec3 irradiance = vec3(0.3);

    vec3 indirect_diffuse = ambient_diffuse_color * irradiance;

    vec3 diffuse = (indirect_diffuse + buffer_emissive) * buffer_ao;
    vec3 color = diffuse + (radiance * visibility) * visibility;

    // gamma correction
    // color = color / (color + vec3(1.0));
    // color = pow(color, vec3(1.0/2.2));  
   
    fragment_color = vec4(color, 1.0);

    // fragment_color = vec4(vec3(attenuation), 1.0f);

    // // some distance fog (depth needs to be linearized)
    // float depth = texture(gbuffer_depth, v_texture_cords).r;
    // // linerize depth
    // float near = 0.1;
    // float far = 1000.0;
    // depth = near * far / (far - depth * (far - near));
    // depth = depth / far;

    // if (depth < 0.9) {
    //     color = mix(color, vec3(1.0, 1.0, 1.0), depth);
    // }

    // output to screen
    // fragment_color = vec4(color, 1.0);
}