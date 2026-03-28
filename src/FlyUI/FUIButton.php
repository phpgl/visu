<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\Theme\FUIButtonStyle;
use VISU\OS\MouseButton;

class FUIButton extends FUIView
{
    /**
     * The style of the button
     */
    public FUIButtonStyle $style;

    /**
     * Button ID
     */
    public string $buttonId;

    /**
     * If set to true, the button will take the full width of the parent container
     * instead of basing its size on the text width
     */
    public bool $fullWidth = false;

    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
        public ?\Closure $onClick = null,
        ?string $buttonId = null,
        ?FUIButtonStyle $buttonStyle = null
    )
    {
        $this->style = $buttonStyle ?? FlyUI::$instance->theme->primaryButton;
        parent::__construct($this->style->padding->copy());

        // button ID by default just the text, this means that if
        // you have multiple buttons with the same text, you have to assign a custom ID
        $this->buttonId = $buttonId ?? 'btn_' . $this->text;
    }

    /**
     * Applies the given button style
     */
    public function applyStyle(FUIButtonStyle $style) : self
    {
        $this->style = $style;
        $this->padding = $style->padding->copy();
        return $this;
    }

    /**
     * Sets the button ID
     */
    public function setId(string $id) : self
    {
        $this->buttonId = $id;
        return $this;
    }

    /**
     * Sets the button to full width mode
     */
    public function setFullWidth(bool $fullWidth = true) : self
    {
        $this->fullWidth = $fullWidth;
        return $this;
    }

    /**
     * Returns the estimated size of the button
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        // we need to set font and size to get the correct bounds
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->style->fontSize);
        $bounds = new Vec4();
        $ctx->vg->textBounds(0, 0, $this->text, $bounds);

        $width = ($bounds->z - $bounds->x) + $this->padding->x + $this->padding->y;
        $height = ($bounds->w - $bounds->y) + $this->padding->z + $this->padding->w;
        if ($this->fullWidth) {
            $width = $ctx->containerSize->x;
        }
        return new Vec2($width, $height);
    }

    private const BUTTON_PRESS_NONE = 0;
    private const BUTTON_PRESS_STARTED = 1;

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $estimatedSize = $this->getEstimatedSize($ctx);
        $height = $estimatedSize->y;
        $width = $estimatedSize->x;

        // update the container size based on the determined size
        $ctx->containerSize->y = $height;
        $ctx->containerSize->x = $width;

        // check if the mouse is inside the button
        $isInside = $ctx->isHovered();

        // last press key
        $lpKey = $this->buttonId . '_lp';

        // Track press state: NONE → STARTED (on press) → fire onClick (on release inside) → NONE
        $pressKey = $this->buttonId . '_ps';
        $rawPressState = $ctx->getStaticValue($pressKey, self::BUTTON_PRESS_NONE);
        $pressState = (int) $rawPressState;

        // Defensive: reset corrupt press state (can happen during display mode transitions)
        if ($pressState !== self::BUTTON_PRESS_NONE && $pressState !== self::BUTTON_PRESS_STARTED) {
            $pressState = self::BUTTON_PRESS_NONE;
            $ctx->setStaticValue($pressKey, self::BUTTON_PRESS_NONE);
        }

        $mousePressed = $ctx->input->isMouseButtonPressed(MouseButton::LEFT);
        $mouseReleased = !$mousePressed;

        if ($isInside && $mousePressed && $pressState === self::BUTTON_PRESS_NONE)
        {
            $ctx->setStaticValue($lpKey, glfwGetTime());
            $ctx->setStaticValue($pressKey, self::BUTTON_PRESS_STARTED);
        } else if ($isInside && $mouseReleased && $pressState === self::BUTTON_PRESS_STARTED) {
            $ctx->setStaticValue($pressKey, self::BUTTON_PRESS_NONE);
            if ($this->onClick) {
                ($this->onClick)();
            }
        } else if ($pressState === self::BUTTON_PRESS_STARTED && $mouseReleased) {
            // Released outside button bounds — cancel
            $ctx->setStaticValue($pressKey, self::BUTTON_PRESS_NONE);
        }

        // ring fade animation disabled — causes white line artifacts
        $ctx->clearStaticValue($lpKey);

        // render the button background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->style->backgroundColor);
        if ($isInside) {
            $ctx->vg->fillColor($this->style->hoverBackgroundColor);
        }
        $ctx->vg->roundedRect(
            $ctx->origin->x,
            $ctx->origin->y,
            $ctx->containerSize->x,
            $height,
            $this->style->cornerRadius
        );
        $ctx->vg->fill();

        // prepare font for text metrics
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->style->fontSize);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->fillColor($this->style->textColor);

        // get text metrics for visual centering with TOP alignment
        $ascender = 0.0;
        $descender = 0.0;
        $lineHeight = 0.0;
        $ctx->vg->textMetrics($ascender, $descender, $lineHeight);

        $visualCenterY = $ctx->origin->y + $height * 0.5;
        $visualCenterY += (-$descender) * 0.25;

        $ctx->vg->fillColor($this->style->textColor);
        $ctx->vg->text(
            $ctx->origin->x + $ctx->containerSize->x * 0.5,
            floor($visualCenterY),
            $this->text
        );

        // no pass to parent, as this is a leaf element
    }
}
