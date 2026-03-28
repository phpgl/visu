#version 330 core

#define PBR_DISTRIBUTION_GGX
#define PBR_GEOMETRY_COOK_TORRANCE
#define MAX_POINT_LIGHTS 32
#define MAX_SPOT_LIGHTS 16
#define MAX_SHADOW_CASCADES 4
#define MAX_SHADOW_POINT_LIGHTS 4

in vec2 v_uv;
out vec4 fragment_color;

// GBuffer textures
uniform sampler2D gbuffer_position;
uniform sampler2D gbuffer_normal;
uniform sampler2D gbuffer_depth;
uniform sampler2D gbuffer_albedo;
uniform sampler2D gbuffer_ao;
uniform sampler2D gbuffer_metallic_roughness;
uniform sampler2D gbuffer_emissive;

// shadow maps (one per cascade)
uniform sampler2D shadow_map_0;
uniform sampler2D shadow_map_1;
uniform sampler2D shadow_map_2;
uniform sampler2D shadow_map_3;
uniform mat4 light_space_matrices[MAX_SHADOW_CASCADES];
uniform float cascade_splits[MAX_SHADOW_CASCADES];
uniform int num_shadow_cascades;
uniform mat4 u_view_matrix;

// camera
uniform vec3 camera_position;
uniform vec2 camera_resolution;

// directional light (sun)
uniform vec3 sun_direction;
uniform vec3 sun_color;
uniform float sun_intensity;

// point lights
struct PointLight {
    vec3 position;
    vec3 color;
    float intensity;
    float range;
    float constant;
    float linear;
    float quadratic;
};
uniform PointLight point_lights[MAX_POINT_LIGHTS];
uniform int num_point_lights;

// spot lights
struct SpotLight {
    vec3 position;
    vec3 direction;
    vec3 color;
    float intensity;
    float range;
    float constant;
    float linear;
    float quadratic;
    float innerCutoff; // cos(innerAngle)
    float outerCutoff; // cos(outerAngle)
};
uniform SpotLight spot_lights[MAX_SPOT_LIGHTS];
uniform int num_spot_lights;

// point light cubemap shadows
uniform samplerCube point_shadow_map_0;
uniform samplerCube point_shadow_map_1;
uniform samplerCube point_shadow_map_2;
uniform samplerCube point_shadow_map_3;
uniform int num_point_shadow_lights;
uniform vec3 point_shadow_positions[MAX_SHADOW_POINT_LIGHTS];
uniform float point_shadow_far_planes[MAX_SHADOW_POINT_LIGHTS];
// maps shadow light index to point_lights[] index (-1 = no shadow)
uniform int point_shadow_light_indices[MAX_SHADOW_POINT_LIGHTS];

const float gamma = 2.2;
const float PI = 3.14159265359;
const float exposure = 1.5;

vec3 fresnel(vec3 F0, float cosTheta)
{
    return F0 + (1.0 - F0) * pow(clamp(1.0 - cosTheta, 0.0, 1.0), 5.0);
}

float distribution_GGX(float NdotH, float roughness)
{
    float a = roughness * roughness;
    float a2 = a * a;
    float d = NdotH * NdotH * (a2 - 1.0) + 1.0;
    return a2 / (PI * d * d);
}

float geometry_cook_torrance(float NdotL, float NdotV, float NdotH, float VdotH)
{
    float G1 = (2.0 * NdotH * NdotV) / VdotH;
    float G2 = (2.0 * NdotH * NdotL) / VdotH;
    return min(1.0, min(G1, G2));
}

vec3 pbr_specular(vec3 N, vec3 V, vec3 H, vec3 L, vec3 F0, float roughness)
{
    float NdotH = max(0.0, dot(N, H));
    float NdotV = max(1e-7, dot(N, V));
    float NdotL = max(1e-7, dot(N, L));
    float VdotH = max(0.0, dot(V, H));

    float D = distribution_GGX(NdotH, roughness);
    float G = geometry_cook_torrance(NdotL, NdotV, NdotH, VdotH);
    vec3 F = fresnel(F0, VdotH);

    return (D * F * G) / (4.0 * NdotL * NdotV);
}

vec3 calculate_light(vec3 L, vec3 radiance, vec3 N, vec3 V, vec3 albedo, float metallic, float roughness)
{
    vec3 H = normalize(L + V);
    vec3 F0 = mix(vec3(0.04), albedo, metallic);
    vec3 F = fresnel(F0, max(0.0, dot(H, V)));
    vec3 specular = pbr_specular(N, V, H, L, F0, roughness);

    float NdotL = max(dot(N, L), 0.0);
    vec3 kD = (1.0 - F) * (1.0 - metallic);

    return (kD * albedo / PI + specular) * radiance * NdotL;
}

// PCF shadow sampling (3x3 kernel)
float sampleShadowMap(sampler2D shadowMap, vec4 lightSpacePos, float bias)
{
    // perspective divide
    vec3 projCoords = lightSpacePos.xyz / lightSpacePos.w;
    projCoords = projCoords * 0.5 + 0.5; // to [0,1]

    // outside shadow map = fully lit
    if (projCoords.z > 1.0) return 1.0;
    if (projCoords.x < 0.0 || projCoords.x > 1.0 || projCoords.y < 0.0 || projCoords.y > 1.0) return 1.0;

    float currentDepth = projCoords.z;

    // PCF 3x3
    float shadow = 0.0;
    vec2 texelSize = 1.0 / textureSize(shadowMap, 0);
    for (int x = -1; x <= 1; x++) {
        for (int y = -1; y <= 1; y++) {
            float pcfDepth = texture(shadowMap, projCoords.xy + vec2(x, y) * texelSize).r;
            shadow += currentDepth - bias > pcfDepth ? 0.0 : 1.0;
        }
    }
    return shadow / 9.0;
}

float computeShadow(vec3 worldPos, vec3 N, vec3 L)
{
    if (num_shadow_cascades == 0) return 1.0;

    // determine which cascade this fragment belongs to by view-space depth
    vec4 viewPos = u_view_matrix * vec4(worldPos, 1.0);
    float depth = abs(viewPos.z);

    int cascadeIndex = num_shadow_cascades - 1;
    for (int i = 0; i < num_shadow_cascades; i++) {
        if (depth < cascade_splits[i]) {
            cascadeIndex = i;
            break;
        }
    }

    // transform to light space
    vec4 lightSpacePos = light_space_matrices[cascadeIndex] * vec4(worldPos, 1.0);

    // slope-scaled bias (larger for steeper angles)
    float bias = max(0.003 * (1.0 - dot(N, L)), 0.001);
    // increase bias for farther cascades
    bias *= float(cascadeIndex + 1) * 0.5;

    // select the correct shadow map sampler
    float shadow;
    if (cascadeIndex == 0) shadow = sampleShadowMap(shadow_map_0, lightSpacePos, bias);
    else if (cascadeIndex == 1) shadow = sampleShadowMap(shadow_map_1, lightSpacePos, bias);
    else if (cascadeIndex == 2) shadow = sampleShadowMap(shadow_map_2, lightSpacePos, bias);
    else shadow = sampleShadowMap(shadow_map_3, lightSpacePos, bias);

    return shadow;
}

float samplePointShadow(samplerCube shadowMap, vec3 fragToLight, float farPlane)
{
    float closestDepth = texture(shadowMap, fragToLight).r;
    closestDepth *= farPlane;
    float currentDepth = length(fragToLight);

    // bias based on distance to prevent shadow acne
    float bias = max(0.05 * (1.0 - currentDepth / farPlane), 0.005);

    return currentDepth - bias > closestDepth ? 0.0 : 1.0;
}

float computePointShadow(int pointLightIndex, vec3 fragPos)
{
    // check each shadow-casting light to see if it matches this point light index
    for (int s = 0; s < num_point_shadow_lights; s++) {
        if (point_shadow_light_indices[s] != pointLightIndex) continue;

        vec3 fragToLight = fragPos - point_shadow_positions[s];
        float farPlane = point_shadow_far_planes[s];

        if (s == 0) return samplePointShadow(point_shadow_map_0, fragToLight, farPlane);
        else if (s == 1) return samplePointShadow(point_shadow_map_1, fragToLight, farPlane);
        else if (s == 2) return samplePointShadow(point_shadow_map_2, fragToLight, farPlane);
        else return samplePointShadow(point_shadow_map_3, fragToLight, farPlane);
    }
    return 1.0; // no shadow map for this light
}

vec3 tone_mapping_ACESFilm(vec3 x)
{
    x *= exposure;
    float a = 2.51;
    float b = 0.03;
    float c = 2.43;
    float d = 0.59;
    float e = 0.14;
    return clamp((x*(a*x+b))/(x*(c*x+d)+e), 0.0, 1.0);
}

void main()
{
    vec3 pos = texture(gbuffer_position, v_uv).rgb;
    vec3 normal = texture(gbuffer_normal, v_uv).rgb;
    vec3 albedo = texture(gbuffer_albedo, v_uv).rgb;
    float ao = texture(gbuffer_ao, v_uv).r;
    vec2 mr = texture(gbuffer_metallic_roughness, v_uv).rg;
    vec3 emissive = texture(gbuffer_emissive, v_uv).rgb;

    float metallic = mr.r;
    float roughness = mr.g;

    vec3 N = normalize(normal);
    vec3 V = normalize(camera_position - pos);
    vec3 L_sun = normalize(sun_direction);

    // shadow for directional light
    float shadow = computeShadow(pos, N, L_sun);

    // directional light (with shadow)
    vec3 Lo = calculate_light(L_sun, sun_color * sun_intensity, N, V, albedo, metallic, roughness) * shadow;

    // point lights (with cubemap shadows)
    for (int i = 0; i < num_point_lights; i++) {
        vec3 light_vec = point_lights[i].position - pos;
        float distance = length(light_vec);

        if (distance > point_lights[i].range) continue;

        vec3 L = light_vec / distance;
        float attenuation = 1.0 / (
            point_lights[i].constant +
            point_lights[i].linear * distance +
            point_lights[i].quadratic * distance * distance
        );
        float smooth_falloff = 1.0 - smoothstep(point_lights[i].range * 0.75, point_lights[i].range, distance);
        attenuation *= smooth_falloff;

        float point_shadow = computePointShadow(i, pos);

        vec3 radiance = point_lights[i].color * point_lights[i].intensity * attenuation;
        Lo += calculate_light(L, radiance, N, V, albedo, metallic, roughness) * point_shadow;
    }

    // spot lights
    for (int i = 0; i < num_spot_lights; i++) {
        vec3 light_vec = spot_lights[i].position - pos;
        float distance = length(light_vec);

        if (distance > spot_lights[i].range) continue;

        vec3 L = light_vec / distance;

        // cone attenuation
        float theta = dot(L, normalize(-spot_lights[i].direction));
        float epsilon = spot_lights[i].innerCutoff - spot_lights[i].outerCutoff;
        float spot_intensity = clamp((theta - spot_lights[i].outerCutoff) / epsilon, 0.0, 1.0);

        if (spot_intensity <= 0.0) continue;

        float attenuation = 1.0 / (
            spot_lights[i].constant +
            spot_lights[i].linear * distance +
            spot_lights[i].quadratic * distance * distance
        );
        float smooth_falloff = 1.0 - smoothstep(spot_lights[i].range * 0.75, spot_lights[i].range, distance);
        attenuation *= smooth_falloff * spot_intensity;

        vec3 radiance = spot_lights[i].color * spot_lights[i].intensity * attenuation;
        Lo += calculate_light(L, radiance, N, V, albedo, metallic, roughness);
    }

    // ambient
    vec3 ambient = vec3(0.03) * albedo * ao;
    Lo *= ao;

    vec3 color = ambient + Lo + emissive;

    // HDR tone mapping + gamma
    color = tone_mapping_ACESFilm(color);
    color = pow(color, vec3(1.0 / gamma));

    fragment_color = vec4(color, 1.0);
}
