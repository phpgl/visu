<?php

namespace VISU\FlyUI\Theme;

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\FlyUI;

class FUIButtonStyle
{
    /**
     * Returns the primary button style (This is just an alias for FlyUI::$instance->theme->primaryButton)
     */
    public static function primary() : self {
        return FlyUI::$instance->theme->primaryButton;
    }

    public static function secondary() : self {
        return FlyUI::$instance->theme->secondaryButton;
    }

    /*
     * ------------------------------- General --------------------------------
     */
    
    /**
     * Padding for buttons
     */
    public Vec4 $padding;

    /**
     * The corner radius for buttons
     */
    public float $cornerRadius;

    /**
     * The font size for buttons
     */
    public float $fontSize;

    /*
     * ------------------------------- Colors --------------------------------
     */

    /**
     * The background color for primary buttons
     */
    public VGColor $backgroundColor;

    /**
     * The background color for buttons when hovered
     */
    public VGColor $hoverBackgroundColor;

    /**
     * The text color for buttons
     */
    public VGColor $textColor;

    /**
     * the text color for the button in hover state
     */
    public VGColor $hoverTextColor;

    /**
     * The disabled button background color
     */
    public VGColor $disabledBackgroundColor;

    /**
     * The disabled button text color
     */
    public VGColor $disabledTextColor;
}