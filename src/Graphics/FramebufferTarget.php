<?php 

namespace VISU\Graphics;

enum FramebufferTarget
{
    case READ;
    case DRAW;
    case READ_DRAW;
}