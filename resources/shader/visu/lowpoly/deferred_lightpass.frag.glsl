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
#define PBR_GEOMETRY_COOK_TORRANCE

in vec2 v_texture_cords;
out vec4 fragment_color;

// gbuffer textures
uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_albedo;
uniform sampler2D gbuffer_ao;

// camera uniforms
uniform vec3 camera_position;
uniform vec2 camera_resolution;

// sun uniforms
uniform vec3 sun_direction;
uniform vec3 sun_color;
uniform float sun_intensity;

const float gamma = 2.2;
const float PI = 3.14159265359;
const float exposure = 1.5;

vec3 fresnel(vec3 F0, float b) 
{
    return F0 + (1.0 - F0) *  pow(clamp(1.0 - b, 0.0, 1.0), 5.0);
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

vec3 pbr_specular(vec3 N, vec3 V, vec3 H, vec3 L, vec3 F0, float roughness) 
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
    
    vec3 F = fresnel(F0, VdotH);
    
    return (D * F * G) / (4.0 * NdotL * NdotV);
}

vec3 tone_mapping_ACESFilm(vec3 x)
{
    x *= exposure;

    float a = 2.51f;
    float b = 0.03f;
    float c = 2.43f;
    float d = 0.59f;
    float e = 0.14f;

    return clamp((x*(a*x+b))/(x*(c*x+d)+e), 0.0, 1.0);
}

vec3 gamma_correct(vec3 color)
{
    return pow(color, vec3(1.0 / gamma));
}

void main()
{             
    // retrieve data from gbuffer
    vec3 buffer_pos = texture(gbuffer_position, v_texture_cords).rgb;
    vec3 buffer_normal = texture(gbuffer_normal, v_texture_cords).rgb;
    vec3 buffer_albedo = texture(gbuffer_albedo, v_texture_cords).rgb;
    vec3 buffer_ao = texture(gbuffer_ao, v_texture_cords).rgb;
    vec3 buffer_emissive = vec3(0.0);
    float buffer_metal = 0.0;
    float buffer_roughness = 1.0;

    float inverse_metal = 1.0f - buffer_metal;

    // lighting
    vec3 N = normalize(buffer_normal);
    vec3 V = normalize(camera_position - buffer_pos);
    vec3 L = normalize(sun_direction);
    vec3 R = normalize(reflect(-L, N));
    vec3 H = normalize(L + V);

    float visibility = 1.0;
    float attenuation = 1.0;
    vec3 radiance = sun_color * sun_intensity * attenuation;

    vec3 F0 = mix(vec3(0.04), buffer_albedo, buffer_metal);
    vec3 F = fresnel(F0, max(0.0, dot(H, V)));
    vec3 specular = pbr_specular(N, V, H, L, F0, buffer_roughness);

    float NdotL = max(dot(N, L), 0.0);     	
    vec3 kD = (1.0 - F) * inverse_metal; 

    vec3 Lo = (kD * buffer_albedo / PI + specular) * radiance * NdotL;

    vec3 ambient = vec3(0.05) * buffer_albedo * buffer_ao.r;

    // also apply ao to Lo
    Lo *= buffer_ao.r;

    vec3 fragment = ambient + Lo;

    // HDR tonemapping
    fragment = tone_mapping_ACESFilm(fragment);
    fragment = gamma_correct(fragment);

    // // tmp blueish sky if albedo is 0 
    // // this is a hack till we build a proper skybox renderer
    // if (buffer_albedo == vec3(0.0)) {
    //     fragment = vec3(0.654, 0.68, 0.8);
    // }

    fragment_color = vec4(fragment, 1.0);
}