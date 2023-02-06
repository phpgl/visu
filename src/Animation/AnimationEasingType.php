<?php

namespace VISU\Animation;

/**
 * Easing types for animations.
 */
enum AnimationEasingType
{
    /**
     * Linear easing.
     */
    case LINEAR;

    /**
     * Ease in easing.
     */
    case EASE_IN;

    /**
     * Ease out easing.
     */
    case EASE_OUT;

    /**
     * Ease in and out easing.
     */
    case EASE_IN_OUT;
}
