#version 330 core

out vec4 FragColor;
in vec3 v_position;

uniform samplerCube u_env_cubemap;
uniform float u_roughness;
uniform float u_source_resolution;

const float PI = 3.14159265359;

// ----------------------------------------------------------------------------
float distribution_ggx(vec3 N, vec3 H, float roughness)
{
    float a = roughness*roughness;
    float a2 = a*a;
    float NdotH = max(dot(N, H), 0.0);
    float NdotH2 = NdotH*NdotH;

    float nom   = a2;
    float denom = (NdotH2 * (a2 - 1.0) + 1.0);
    denom = PI * denom * denom;

    return nom / denom;
}

// ----------------------------------------------------------------------------
// http://holger.dammertz.org/stuff/notes_HammersleyOnHemisphere.html
// efficient VanDerCorpus calculation.
float radical_inverse_vdc(uint bits) 
{
     bits = (bits << 16u) | (bits >> 16u);
     bits = ((bits & 0x55555555u) << 1u) | ((bits & 0xAAAAAAAAu) >> 1u);
     bits = ((bits & 0x33333333u) << 2u) | ((bits & 0xCCCCCCCCu) >> 2u);
     bits = ((bits & 0x0F0F0F0Fu) << 4u) | ((bits & 0xF0F0F0F0u) >> 4u);
     bits = ((bits & 0x00FF00FFu) << 8u) | ((bits & 0xFF00FF00u) >> 8u);
     return float(bits) * 2.3283064365386963e-10; // / 0x100000000
}

// ----------------------------------------------------------------------------
vec2 hammersley(uint i, uint N)
{
	return vec2(float(i)/float(N), radical_inverse_vdc(i));
}

// ----------------------------------------------------------------------------
vec3 importance_sample_ggx(vec2 Xi, vec3 N, float roughness)
{
	float a = roughness*roughness;
	
	float phi = 2.0 * PI * Xi.x;
	float cos_theta = sqrt((1.0 - Xi.y) / (1.0 + (a*a - 1.0) * Xi.y));
	float sin_theta = sqrt(1.0 - cos_theta*cos_theta);
	
	// from spherical coordinates to cartesian coordinates - halfway vector
	vec3 H;
	H.x = cos(phi) * sin_theta;
	H.y = sin(phi) * sin_theta;
	H.z = cos_theta;
	
	// from tangent-space H vector to world-space sample vector
	vec3 up        = abs(N.z) < 0.999 ? vec3(0.0, 0.0, 1.0) : vec3(1.0, 0.0, 0.0);
	vec3 tangent   = normalize(cross(up, N));
	vec3 bitangent = cross(N, tangent);
	
	vec3 sample = tangent * H.x + bitangent * H.y + N * H.z;
	return normalize(sample);
}

// ----------------------------------------------------------------------------
void main()
{
    vec3 N = normalize(v_position);
    vec3 V = N;

    float rough = max(u_roughness, 1e-4);

    const uint SAMPLE_COUNT = 4096u;
    vec3 prefiltered = vec3(0.0);
    float weight = 0.0;

    for (uint i = 0u; i < SAMPLE_COUNT; ++i)
    {
        vec2 Xi = hammersley(i, SAMPLE_COUNT);
        vec3 H  = importance_sample_ggx(Xi, N, rough);
        vec3 L  = normalize(2.0 * dot(V, H) * H - V);

        float NdotL = max(dot(N, L), 0.0);
        if (NdotL > 0.0)
        {
            float D     = distribution_ggx(N, H, rough);
            float NdotH = max(dot(N, H), 0.0);
            float HdotV = max(dot(H, V), 0.0);

            float pdf = D * NdotH / max(4.0 * HdotV, 1e-6) + 1e-4;

            float saTexel  = 4.0 * PI / (6.0 * u_source_resolution * u_source_resolution);
            float saSample = 1.0 / (float(SAMPLE_COUNT) * pdf + 1e-4);

            float mip = (u_roughness < 1e-4) ? 0.0 : 0.5 * log2(saSample / saTexel);

            prefiltered += textureLod(u_env_cubemap, L, mip).rgb * NdotL;
            weight      += NdotL;
        }
    }

    prefiltered /= max(weight, 1e-6);
    FragColor = vec4(prefiltered, 1.0);
}
