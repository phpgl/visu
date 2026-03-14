// PBR GBuffer layout — extends the standard layout with metallic/roughness + emissive
layout (location = 0) out vec3 gbuffer_position;
layout (location = 1) out vec3 gbuffer_vposition;
layout (location = 2) out vec3 gbuffer_normal;
layout (location = 3) out vec4 gbuffer_albedo;          // RGB = albedo, A = alpha
layout (location = 4) out vec2 gbuffer_metallic_roughness; // R = metallic, G = roughness
layout (location = 5) out vec3 gbuffer_emissive;
