
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec2 a_uv;

out vec2 v_uv;

void main()
{
    gl_Position = vec4(a_position, 1.0);
    v_uv = a_uv;
}