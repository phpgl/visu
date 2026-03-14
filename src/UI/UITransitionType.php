<?php

namespace VISU\UI;

enum UITransitionType: string
{
    case FadeIn = 'fadeIn';
    case FadeOut = 'fadeOut';
    case SlideInLeft = 'slideInLeft';
    case SlideInRight = 'slideInRight';
    case SlideInTop = 'slideInTop';
    case SlideInBottom = 'slideInBottom';
    case ScaleIn = 'scaleIn';
    case ScaleOut = 'scaleOut';
}
