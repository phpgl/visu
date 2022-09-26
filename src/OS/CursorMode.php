<?php 

namespace VISU\OS;

enum CursorMode: int
{
    case NORMAL = 0x00034001; //GLFW_CURSOR_NORMAL;
    case HIDDEN = 0x00034002; //GLFW_CURSOR_HIDDEN;
    case DISABLED = 0x00034003; //GLFW_CURSOR_DISABLED;
}