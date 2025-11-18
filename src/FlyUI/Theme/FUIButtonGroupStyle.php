<?php

namespace VISU\FlyUI\Theme;

use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\FlyUI;

class FUIButtonGroupStyle
{
    /*
     * ------------------------------- General --------------------------------
     */
    
    /**
     * Padding for button group container
     */
    public Vec4 $padding;

    /**
     * The corner radius for the button group container
     */
    public float $cornerRadius;

    /**
     * The corner radius for individual buttons within the group
     */
    public float $buttonCornerRadius;

    /**
     * The font size for button group text
     */
    public float $fontSize;

    /**
     * Spacing between buttons
     */
    public float $buttonSpacing;

    /**
     * Inner offset (padding between container and buttons)
     */
    public float $innerOffset;

    /**
     * Animation speed for highlight box transitions (higher = faster)
     */
    public float $animationSpeed;

    /*
     * ------------------------------- Colors --------------------------------
     */

    /**
     * The background color for the button group container
     */
    public VGColor $backgroundColor;

    /**
     * The border color for the button group container
     */
    public VGColor $borderColor;

    /**
     * The background color for active buttons
     */
    public VGColor $activeBackgroundColor;

    /**
     * The background color for buttons when hovered
     */
    public VGColor $hoverBackgroundColor;

    /**
     * The text color for active buttons
     */
    public VGColor $activeTextColor;

    /**
     * The text color for inactive buttons
     */
    public VGColor $inactiveTextColor;

    /**
     * The text color for buttons when hovered
     */
    public VGColor $hoverTextColor;

    /**
     * The gray overlay color for hover effects
     */
    public VGColor $hoverOverlayColor;
}