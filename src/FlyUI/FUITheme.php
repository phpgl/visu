<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

class FUITheme
{   
    /*
     * ------------------------------ General ---------------------------------
     */

    /**
     * Default font used for text, buttons, etc.
     * FlyUI loads the `inter` font automatically, if you want to use a different font you
     * are responsible for loading it yourself.
     */
    public string $regularFont = 'inter-regular';

    /**
     * Default "semi bold" font.
     * FlyUI loads the `inter` font automatically, if you want to use a different font you
     * are responsible for loading it yourself.
     */
    public string $semiBoldFont = 'inter-semibold';

    /**
     * The general padding used to space elements
     */
    public float $padding = 10.0;

    /**
     * General border radius used for elements
     */
    public float $borderRadius = 5.0;

    /**
     * General font size used for text
     */
    public float $fontSize = 13.0;

    /**
     * (small) font size used for text
     */
    public float $smallFontSize = 10.0;

    /**
     * (large) font size used for text
     */
    public float $largeFontSize = 16.0;

    /**
     * Spacing between elements
     */
    public float $spacing = 5.0;

    /*
     * ------------------------------ Cards -----------------------------------
     */

     /**
      * The corner / border radius used for cards
      */
    public float $cardBorderRadius;

    /**
     * The padding (inset space) used for cards
     */
    public Vec2 $cardPadding;

    /**
     * The background color used for cards
     */
    public VGColor $cardBackgroundColor;

    /**
     * The color of the border around the card (if null, no border is drawn)
     */
    public ?VGColor $cardBorderColor = null;

    /**
     * The width of the border around the card
     */
    public float $cardBorderWidth = 1.0;

    /**
     * Vertical spacing between card elements
     */
    public float $cardSpacing = 5.0;

    /*
     * ------------------------------ Window ----------------------------------
     */

    /**
     * The padding (inset space) used for windows
     */
    public Vec2 $windowPadding;

    /*
     * ------------------------------- Buttons --------------------------------
     */

    /**
     * The background color for primary buttons
     */
    public VGColor $buttonPrimaryBackgroundColor;

    /**
     * The background color for primary buttons when hovered
     */
    public VGColor $buttonPrimaryHoverBackgroundColor;

    /**
     * The text color for primary buttons
     */
    public VGColor $buttonPrimaryTextColor;

    /**
     * The background color for secondary buttons
     */
    public VGColor $buttonSecondaryBackgroundColor;

    /**
     * The background color for secondary buttons when hovered
     */
    public VGColor $buttonSecondaryHoverBackgroundColor;

    /**
     * The text color for secondary buttons
     */
    public VGColor $buttonSecondaryTextColor;
    
    /**
     * Padding for buttons
     */
    public Vec2 $buttonPadding;

    /**
     * The border radius for buttons
     */
    public float $buttonBorderRadius;

    /**
     * The font size for buttons
     */
    public float $buttonFontSize;

    /**
     * ------------------------------ Checkboxes ------------------------------
     */

    public VGColor $checkboxBackgroundColor;

    public VGColor $checkboxHoverBackgroundColor;

    public VGColor $checkboxActiveBackgroundColor;


    /**
     * Constructs a new theme
     */
    public function __construct() 
    {
        $this->applyGenerals();
    }

    /**
     * Applies the general theme settings to all elements
     * This will override any custom settings you have set except the general settings
     */
    public function applyGenerals() : void
    {
        // card
        $this->cardPadding = new Vec2($this->padding, $this->padding);
        $this->cardBorderRadius = $this->borderRadius;
        $this->cardBackgroundColor = VGColor::white();

        // window
        $this->windowPadding = new Vec2($this->padding, $this->padding);

        // buttons
        $this->buttonPrimaryBackgroundColor = new VGColor(0.256, 0.271, 0.906, 1.0);
        $this->buttonPrimaryHoverBackgroundColor = $this->buttonPrimaryBackgroundColor->lighten(0.05);
        $this->buttonPrimaryTextColor = VGColor::white();
        $this->buttonSecondaryBackgroundColor = VGColor::black();
        $this->buttonSecondaryHoverBackgroundColor = VGColor::black()->lighten(0.1);
        $this->buttonSecondaryTextColor = VGColor::white();
        $this->buttonPadding = new Vec2(round($this->padding * 1.2), round($this->padding * 0.6));
        $this->buttonBorderRadius = $this->borderRadius;
        $this->buttonFontSize = $this->fontSize;

        // checkboxes
        $this->checkboxBackgroundColor = new VGColor(0.902, 0.902, 0.901, 1.0);
        $this->checkboxHoverBackgroundColor = $this->checkboxBackgroundColor->lighten(0.05);
        $this->checkboxActiveBackgroundColor =$this->buttonPrimaryBackgroundColor;
    }
}