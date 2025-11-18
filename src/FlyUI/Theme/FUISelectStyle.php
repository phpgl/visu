<?php

namespace VISU\FlyUI\Theme;

use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;

class FUISelectStyle
{
    /*
     * ------------------------------- General --------------------------------
     */
    
    /**
     * Padding for select container
     */
    public Vec4 $padding;

    /**
     * The corner radius for the select container
     */
    public float $cornerRadius;

    /**
     * The corner radius for the dropdown list
     */
    public float $dropdownCornerRadius;

    /**
     * The font size for select text
     */
    public float $fontSize;

    /**
     * The height of each option in the dropdown
     */
    public float $optionHeight;

    /**
     * The maximum height of the dropdown before scrolling
     */
    public float $maxDropdownHeight;

    /**
     * Nothing selected text (Placeholder
     */
    public string $nothingSelectedText = 'Please select...';

    /*
     * ------------------------------- Colors --------------------------------
     */

    /**
     * The background color for the select container
     */
    public VGColor $backgroundColor;

    /**
     * The background color for the select container when hovered
     */
    public VGColor $hoverBackgroundColor;

    /**
     * The border color for the select container
     */
    public VGColor $borderColor;

    /**
     * The background color for the dropdown
     */
    public VGColor $dropdownBackgroundColor;

    /**
     * The border color for the dropdown
     */
    public VGColor $dropdownBorderColor;

    /**
     * The background color for hovered options
     */
    public VGColor $optionHoverBackgroundColor;

    /**
     * The background color for selected option
     */
    public VGColor $optionSelectedBackgroundColor;

    /**
     * The text color for the select
     */
    public VGColor $textColor;

    /**
     * The text color for the select
     */
    public VGColor $textPlaceholderColor;

    /**
     * The text color for options
     */
    public VGColor $optionTextColor;

    /**
     * The text color for hovered options
     */
    public VGColor $optionHoverTextColor;

    /**
     * The text color for selected option
     */
    public VGColor $optionSelectedTextColor;

    /**
     * The color of the dropdown arrow
     */
    public VGColor $arrowColor;
}