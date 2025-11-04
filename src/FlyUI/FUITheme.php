<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\Theme\FUIButtonStyle;
use VISU\FlyUI\Theme\FUIButtonGroupStyle;

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
     * Monospace font.
     */
    public string $monospaceFont = 'inconsolata-regular';

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
     * Space after a section
     */
    public float $sectionBottomSpace;

    /**
     * Section header text color
     */
    public VGColor $sectionHeaderTextColor;

    /**
     * Section header font size
     */
    public float $sectionHeaderFontSize;

    /*
     * ------------------------------ Labels -----------------------------------
     */

    /**
     * Label text color
     */
    public VGColor $labelTextColor;

    /**
     * Label font size
     */
    public float $labelFontSize;

    /**
     * Label height 
     */
    public float $labelHeight = 24.0;

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
    public Vec4 $cardPadding;

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
    public Vec4 $windowPadding;

    /*
     * ------------------------------- Components -------------------------------
     */

    // button styles
    public FUIButtonStyle $primaryButton;
    public FUIButtonStyle $secondaryButton;

    // button group style
    public FUIButtonGroupStyle $buttonGroup;


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
        $this->sectionBottomSpace = $this->spacing * 4;
        $this->sectionHeaderTextColor = $this->mutedTextColor->copy();
        $this->sectionHeaderFontSize = $this->smallFontSize;

        // labels
        $this->labelTextColor = VGColor::black();
        $this->labelFontSize = $this->fontSize;

        // card
        $this->cardPadding = new Vec4($this->padding, $this->padding, $this->padding, $this->padding);
        $this->cardBorderRadius = $this->borderRadius;
        $this->cardBackgroundColor = VGColor::white();

        // window
        $this->windowPadding = new Vec4($this->padding, $this->padding, $this->padding, $this->padding);

        // buttons
        // --------------------------------------------------------------------

        // primary button
        $this->primaryButton = new FUIButtonStyle();
        $this->primaryButton->padding = new Vec4(round($this->padding * 1.2), round($this->padding * 1.2), round($this->padding * 0.6), round($this->padding * 0.6));
        $this->primaryButton->cornerRadius = $this->borderRadius;
        $this->primaryButton->fontSize = $this->fontSize;
        $this->primaryButton->backgroundColor = new VGColor(0.256, 0.271, 0.906, 1.0);
        $this->primaryButton->hoverBackgroundColor = $this->primaryButton->backgroundColor->lighten(0.05);
        $this->primaryButton->textColor = VGColor::white();
        $this->primaryButton->hoverTextColor = VGColor::white();
        $this->primaryButton->disabledBackgroundColor = $this->primaryButton->backgroundColor->withAlpha(0.5);

        // secondary button
        $this->secondaryButton = clone $this->primaryButton;
        $this->secondaryButton->backgroundColor = VGColor::black();
        $this->secondaryButton->hoverBackgroundColor = $this->secondaryButton->backgroundColor->lighten(0.2);
        $this->secondaryButton->textColor = VGColor::white();
        $this->secondaryButton->hoverTextColor = VGColor::white();
        $this->secondaryButton->disabledBackgroundColor = VGColor::darkGray();

        // checkboxes
        $this->checkboxBackgroundColor = new VGColor(0.902, 0.902, 0.901, 1.0);
        $this->checkboxHoverBackgroundColor = $this->checkboxBackgroundColor->lighten(0.05);

        // button group
        // --------------------------------------------------------------------
        $this->buttonGroup = new FUIButtonGroupStyle();
        $this->buttonGroup->padding = new Vec4(round($this->padding * 0.4), round($this->padding * 0.4), round($this->padding * 0.4), round($this->padding * 0.4));
        $this->buttonGroup->cornerRadius = $this->borderRadius * 2.0;
        $this->buttonGroup->buttonCornerRadius = $this->borderRadius * 1.4;
        $this->buttonGroup->fontSize = $this->fontSize;
        $this->buttonGroup->buttonSpacing = 30.0;
        $this->buttonGroup->innerOffset = 4.0;
        $this->buttonGroup->animationSpeed = 8.0;
        $this->buttonGroup->backgroundColor = VGColor::white();
        $this->buttonGroup->borderColor = VGColor::black()->withAlpha(0.05);
        $this->buttonGroup->activeBackgroundColor = $this->primaryButton->backgroundColor;
        $this->buttonGroup->hoverBackgroundColor = $this->primaryButton->hoverBackgroundColor;
        $this->buttonGroup->activeTextColor = VGColor::white();
        $this->buttonGroup->inactiveTextColor = VGColor::black();
        $this->buttonGroup->hoverTextColor = new VGColor(0.3, 0.3, 0.3, 1.0); // Dark gray
        $this->buttonGroup->hoverOverlayColor = new VGColor(0.0, 0.0, 0.0, 0.1); // Light gray overlay
        $this->buttonGroup->disabledBackgroundColor = new VGColor(0.9, 0.9, 0.9, 1.0);
        $this->buttonGroup->disabledTextColor = new VGColor(0.6, 0.6, 0.6, 1.0);
    }
}