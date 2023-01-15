#version 330 core
layout (location = 0) out vec3 enitity_color;
//out uint fragment_color;

uniform int entity_id; 

vec3 id_to_color(int color)
{
    int r = (color & 0x000000FF) >>  0;
    int g = (color & 0x0000FF00) >>  8;
    int b = (color & 0x00FF0000) >> 16;

    return vec3(r/255.0f, g/255.0f, b/255.0f);	
}

void main()
{ 	
    enitity_color = id_to_color(entity_id);
}