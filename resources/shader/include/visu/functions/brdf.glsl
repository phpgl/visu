#ifndef BRDF_GLSL
#define BRDF_GLSL

/**
 * The PBR Distrubition & Geometry functions
 * 
 *     PBR_DISTRIBUTION_GGX
 *     PBR_DISTRIBUTION_BECKMANN
 * 
 *     PBR_GEOMETRY_SCHLICK
 *     PBR_GEOMETRY_COOK_TORRANCE
 *     PBR_GEOMETRY_KELEMEN
 *     PBR_GEOMETRY_SMITH_GGX_CORRELATED
 */
#define PBR_DISTRIBUTION_GGX
#define PBR_GEOMETRY_SMITH_GGX_CORRELATED

#include "visu/constants.glsl"

const float EPS_DOT = 1e-5;
const float EPS_DENOM = 1e-6;

/**
 * I've used a bunch of function from "optimized-ggx.hlsl" by John Hable
 * http://filmicworlds.com/blog/optimizing-ggx-shaders-with-dotlh/
 * Released under the unlicense
 */

/**
 * Fresnel Functions
 * ----------------------------------------------------------------------------
 */

vec3 fresnel_schlick(vec3 F0, float cosTheta) 
{
	return F0 + (1.0 - F0) * pow(1.0 - cosTheta, 5.0);
}

vec3 fresnel_schlick_roughness(vec3 F0, float cosTheta, float roughness)
{
	return F0 + (max(vec3(1.0 - roughness), F0) - F0) * pow(1.0 - cosTheta, 5.0);
}

float distribution_GGX(float NdotH, float roughness) 
{
    float alpha = roughness*roughness;
	float alphaSqr = alpha*alpha;
	float denom = NdotH * NdotH *(alphaSqr-1.0) + 1.0f;

	float D = alphaSqr/(PI * denom * denom);
	return D;
}

float distribution_beckmann(float NdotH, float roughness) 
{
    float a = roughness * roughness;
    float a2 = a * a;
    float r1 = 1.0 / (PI * a2 * pow(NdotH, 4.0));
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

float geometry_smith_ggx_correlated(float NdotL, float NdotV, float roughness) 
{
    float a  = roughness * roughness;
    float a2 = a * a;

    float gv = NdotL * sqrt(a2 + (1.0 - a2) * NdotV * NdotV);
    float gl = NdotV * sqrt(a2 + (1.0 - a2) * NdotL * NdotL);

    return (2.0 * NdotL * NdotV) / max(gv + gl, EPS_DENOM);
}

float geometry_kelemen(float NdotL, float NdotV, float VdotH) 
{
    return (NdotL * NdotV) / (VdotH * VdotH);
}

vec3 pbr_specular(vec3 N, vec3 V, vec3 H, vec3 L, vec3 F0, float roughness, out vec3 fresnel) 
{
    float NdotH = max(EPS_DOT, dot(N, H));
    float NdotV = max(dot(N, V), 0.0);
    float NdotL = max(dot(N, L), 0.0);
    float VdotH = max(EPS_DOT, dot(V, H));
    
    if (NdotV <= 0.0 || NdotL <= 0.0) {
        fresnel = vec3(0.0);
        return vec3(0.0);
    }

#ifdef PBR_DISTRIBUTION_GGX
    float D = distribution_GGX(NdotH, roughness);
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
#ifdef PBR_GEOMETRY_KELEMEN
    float G = geometry_kelemen(NdotL, NdotV, VdotH);
#endif
#ifdef PBR_GEOMETRY_SMITH_GGX_CORRELATED
    float G = geometry_smith_ggx_correlated(NdotL, NdotV, roughness);
#endif
    
    fresnel = fresnel_schlick(F0, VdotH);
    
    float denom = max(4.0 * NdotV * NdotL, EPS_DENOM);
    
    return (D * fresnel * G) / denom;
}

#endif
