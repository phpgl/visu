
layout (location = 0) in vec3 a_position;

out vec3 v_position;

uniform mat4 projection;
uniform mat4 view;

void main()
{
    v_position = a_position;  
    gl_Position =  projection * view * vec4(v_position, 1.0);
}