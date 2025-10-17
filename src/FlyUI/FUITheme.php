<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\Theme\FUIButtonStyle;

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
     * ------------------------------ Colors ---------------------------------
     */

    /**
     * Base text color
     */
    public VGColor $textColor;

    /**
     * Muted text color
     */
    public VGColor $mutedTextColor;

    /*
     * ------------------------------ Sections ---------------------------------
     */

    /**
     * Section spacing between title and content
     */
    public float $sectionSpacing;

    /**
     * Section header text color
     */
    public VGColor $sectionHeaderTextColor;

    /**
     * Section header font size
     */
    public float $sectionHeaderFontSize;

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
     * ------------------------------- Components -------------------------------
     */

    // button styles
    public FUIButtonStyle $primaryButton;
    public FUIButtonStyle $secondaryButton;


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
        // general
        // --------------------------------------------------------------------
        $this->textColor = VGColor::black();
        $this->mutedTextColor =  $this->textColor->lighten(0.4);

        // section
        $this->sectionSpacing = $this->spacing * 2;
        $this->sectionHeaderTextColor = $this->mutedTextColor->copy();
        $this->sectionHeaderFontSize = $this->smallFontSize;

        // card
        $this->cardPadding = new Vec2($this->padding, $this->padding);
        $this->cardBorderRadius = $this->borderRadius;
        $this->cardBackgroundColor = VGColor::white();

        // window
        $this->windowPadding = new Vec2($this->padding, $this->padding);

        // buttons
        // --------------------------------------------------------------------

        // primary button
        $this->primaryButton = new FUIButtonStyle();
        $this->primaryButton->padding = new Vec2(round($this->padding * 1.2), round($this->padding * 0.6));
        $this->primaryButton->cornerRadius = $this->borderRadius;
        $this->primaryButton->fontSize = $this->fontSize;
        $this->primaryButton->backgroundColor = new VGColor(0.256, 0.271, 0.906, 1.0);
        $this->primaryButton->hoverBackgroundColor = $this->primaryButton->backgroundColor->lighten(0.05);
        $this->primaryButton->textColor = VGColor::white();
        $this->primaryButton->hoverTextColor = VGColor::white();
        $this->primaryButton->disabledBackgroundColor = new VGColor(
            $this->primaryButton->backgroundColor->r,
            $this->primaryButton->backgroundColor->g,
            $this->primaryButton->backgroundColor->b,
            0.5
        );

        // secondary button
        $this->secondaryButton = clone $this->primaryButton;
        $this->secondaryButton->backgroundColor = VGColor::black();
        $this->secondaryButton->hoverBackgroundColor = $this->secondaryButton->backgroundColor->lighten(0.1);
        $this->secondaryButton->textColor = VGColor::white();
        $this->secondaryButton->hoverTextColor = VGColor::white();
        $this->secondaryButton->disabledBackgroundColor = VGColor::darkGray();

        // checkboxes
        $this->checkboxBackgroundColor = new VGColor(0.902, 0.902, 0.901, 1.0);
        $this->checkboxHoverBackgroundColor = $this->checkboxBackgroundColor->lighten(0.05);
    }
}