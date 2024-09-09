<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\OS\MouseButton;

class FUIButton extends FUIView
{
    public VGColor $backgroundColor;

    public VGColor $hoverBackgroundColor;

    public VGColor $textColor;

    public float $borderRadius;

    public float $fontSize;

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
        ?string $buttonId = null
    )
    {
        parent::__construct(FlyUI::$instance->theme->buttonPadding->copy());

        $this->backgroundColor = FlyUI::$instance->theme->buttonPrimaryBackgroundColor;
        $this->hoverBackgroundColor = FlyUI::$instance->theme->buttonPrimaryHoverBackgroundColor;
        $this->textColor = FlyUI::$instance->theme->buttonPrimaryTextColor;
        $this->borderRadius = FlyUI::$instance->theme->buttonBorderRadius;
        $this->fontSize = FlyUI::$instance->theme->buttonFontSize;

        // button ID by default just the text, this means that if 
        // you have multiple buttons with the same text, you have to assign a custom ID
        $this->buttonId = $buttonId ?? 'btn_' . $this->text;
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
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight(FUIRenderContext $ctx) : float
    {
        return $this->fontSize + $this->padding->y * 2;
    }

    /**
     * Returns the width of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedWidth(FUIRenderContext $ctx) : float
    {
        if ($this->fullWidth) {
            return $ctx->containerSize->x;
        }

        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->fontSize);
        return $ctx->vg->textBounds(0, 0, $this->text) + $this->padding->x * 2;
    }

    private const BUTTON_PRESS_NONE = 0;
    private const BUTTON_PRESS_STARTED = 1;
    private const BUTTON_PRESS_ENDED = 2;

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $height = $this->getEstimatedHeight($ctx);
        $width = $this->getEstimatedWidth($ctx);

        // update the container size based on the determined height
        $ctx->containerSize->y = $height;
        $ctx->containerSize->x = $width;

        // check if the mouse is inside the button
        $isInside = $ctx->isHovered();
    
        static $fuiButtonPressStates = [];
        if (!isset($fuiButtonPressStates[$this->buttonId])) {
            $fuiButtonPressStates[$this->buttonId] = self::BUTTON_PRESS_NONE;
        }
    
        if ($isInside && $ctx->input->isMouseButtonPressed(MouseButton::LEFT)) {

            // render a ring around the button when pressed
            $ctx->vg->beginPath();
            $ctx->vg->strokeColor($this->backgroundColor);
            $ctx->vg->strokeWidth(2);
            $ringDistance = 2;
            $ctx->vg->roundedRect(
                $ctx->origin->x - $ringDistance,
                $ctx->origin->y - $ringDistance,
                $ctx->containerSize->x + $ringDistance * 2,
                $height + $ringDistance * 2,
                $this->borderRadius
            );
            $ctx->vg->stroke();

            if ($fuiButtonPressStates[$this->buttonId] === self::BUTTON_PRESS_NONE) {
                $fuiButtonPressStates[$this->buttonId] = self::BUTTON_PRESS_STARTED;
            }
        } else if ($isInside && $fuiButtonPressStates[$this->buttonId] === self::BUTTON_PRESS_STARTED) {
            $fuiButtonPressStates[$this->buttonId] = self::BUTTON_PRESS_ENDED;
            if ($this->onClick) {
                ($this->onClick)();
            }
        } else {
            $fuiButtonPressStates[$this->buttonId] = self::BUTTON_PRESS_NONE;
        }

        // render the button background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);

        if ($isInside) {
            $ctx->vg->fillColor($this->hoverBackgroundColor);
        }

        $ctx->vg->roundedRect(
            $ctx->origin->x,
            $ctx->origin->y,
            $ctx->containerSize->x,
            $height,
            $this->borderRadius
        );
        $ctx->vg->fill();

        // move origin to the center of the button
        $ctx->origin->x = $ctx->origin->x + $ctx->containerSize->x * 0.5;
        $ctx->origin->y = $ctx->origin->y + $height * 0.5;

        // we cheat a little bit here, basically the technical correct center just
        // doesnt look right because at least in my opinion the text should be in the center
        // wile ignoring letters like 'g' or 'y' that go below the baseline
        $ctx->origin->y = floor($ctx->origin->y + $this->fontSize * 0.15);
        
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->text($ctx->origin->x, $ctx->origin->y, $this->text);

        // no pass to parent, as this is a leaf element
        return $height;
    }
}