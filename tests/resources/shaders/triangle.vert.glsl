layout (location = 0) in vec3 position;
layout (location = 1) in vec3 color;
out vec4 pcolor;
void main()
{
    pcolor = vec4(color, 1.0f);
    gl_Position = vec4(position, 1.0f);
}