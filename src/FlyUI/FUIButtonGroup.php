<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use VISU\FlyUI\Theme\FUIButtonGroupStyle;
use VISU\OS\MouseButton;

class FUIButtonGroup extends FUIView
{
    /**
     * The style of the button group
     */
    public FUIButtonGroupStyle $style;

    /**
     * Button group ID
     */
    public string $buttonGroupId;

    /**
     * @var array<string, string> Array of key => label pairs
     */
    public array $options;

    /**
     * The currently selected option key
     */
    public ?string $selectedOption = null;

    /**
     * Reference to external selected option variable (if provided)
     */
    private ?string $selectedOptionRef = null;



    /**
     * Constructs a new button group
     * 
     * @param string $name The name/identifier for this button group
     * @param array<string, string> $options Array of key => label pairs
     * @param string|null $selectedOption Initially selected option key (passed by reference)
     * @param \Closure|null $onSelect Callback function called when selection changes
     * @param string|null $buttonGroupId Unique identifier for this button group
     * @param FUIButtonGroupStyle|null $buttonGroupStyle Custom style for the button group
     */
    public function __construct(
        string $name,
        array $options,
        ?string &$selectedOption = null,
        public ?\Closure $onSelect = null,
        ?string $buttonGroupId = null,
        ?FUIButtonGroupStyle $buttonGroupStyle = null
    )
    {
        $this->style = $buttonGroupStyle ?? FlyUI::$instance->theme->buttonGroup;
        parent::__construct(clone $this->style->padding);

        $this->options = $options;
        $this->selectedOption = $selectedOption;
        $this->selectedOptionRef = &$selectedOption;

        // button group ID, based on name if not provided
        $this->buttonGroupId = $buttonGroupId ?? 'btngrp_' . $name;
    }

    /**
     * Applies the given button group style
     */
    public function applyStyle(FUIButtonGroupStyle $style): self
    {
        $this->style = $style;
        $this->padding = clone $style->padding;
        return $this;
    }

    /**
     * Sets the selected option
     */
    public function setSelectedOption(?string $selectedOption): self
    {
        $this->selectedOption = $selectedOption;
        // update the external reference if it exists
        if (isset($this->selectedOptionRef)) {
            $this->selectedOptionRef = $selectedOption;
        }
        return $this;
    }

    /**
     * Gets the selected option
     */
    public function getSelectedOption(): ?string
    {
        return $this->selectedOption;
    }

    /**
     * Sets the button group ID
     */
    public function setId(string $id): self
    {
        $this->buttonGroupId = $id;
        return $this;
    }

    /**
     * Sets the animation speed for the highlight box transitions
     */
    public function setAnimationSpeed(float $speed): self
    {
        $this->style->animationSpeed = $speed;
        return $this;
    }

    /**
     * Sets the hover overlay color (the gray fade effect)
     */
    public function setHoverOverlayColor(\GL\VectorGraphics\VGColor $color): self
    {
        $this->style->hoverOverlayColor = $color;
        return $this;
    }

    /**
     * Sets the hover text color
     */
    public function setHoverTextColor(\GL\VectorGraphics\VGColor $color): self
    {
        $this->style->hoverTextColor = $color;
        return $this;
    }

    /**
     * Returns the estimated size of the button group
     */
    public function getEstimatedSize(FUIRenderContext $ctx): Vec2
    {
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->style->fontSize);

        $totalWidth = $this->style->buttonSpacing;
        $buttonSpacing = $this->style->buttonSpacing;
        
        // calculate total width in single pass
        foreach ($this->options as $option) {
            $buttonWidth = $ctx->vg->textBounds(0, 0, $option);
            $totalWidth += $buttonWidth + $buttonSpacing * 2;
        }
        
        $totalWidth -= $buttonSpacing; // remove last spacing
        $totalHeight = $this->style->fontSize * 1.8 + $this->style->innerOffset * 2;

        return new Vec2($totalWidth, $totalHeight);
    }

    /**
     * Renders the button group
     */
    public function render(FUIRenderContext $ctx): void
    {
        // cache frequently accessed style properties to avoid repeated property access
        $style = $this->style;
        $buttonSpacing = $style->buttonSpacing;
        $buttonSpacingDouble = $buttonSpacing * 2;
        $innerOffset = $style->innerOffset;
        $innerOffsetDouble = $innerOffset * 2;
        
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($style->fontSize);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        // calculate dimensions and button metrics in single pass
        $totalWidth = $buttonSpacing;
        $buttonWidths = [];
        $buttonOffsets = [];
        $offsetc = $buttonSpacing;

        foreach ($this->options as $key => $option) {
            $width = $ctx->vg->textBounds(0, 0, $option);
            $buttonWidths[$key] = $width;
            $buttonOffsets[$key] = $offsetc;
            $offsetc += $width + $buttonSpacingDouble;
            $totalWidth += $width + $buttonSpacingDouble;
        }
        
        $totalWidth -= $buttonSpacing; // remove last spacing
        $totalHeight = $style->fontSize * 1.8 + $innerOffsetDouble;

        // update container size
        $ctx->containerSize->x = $totalWidth;
        $ctx->containerSize->y = $totalHeight;

        // draw the background container
        $originX = $ctx->origin->x;
        $originY = $ctx->origin->y;
        
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect($originX, $originY, $totalWidth, $totalHeight, $style->cornerRadius);
        $ctx->vg->strokeWidth(2.0);
        $ctx->vg->fillColor($style->backgroundColor);
        $ctx->vg->strokeColor($style->borderColor);
        $ctx->vg->stroke();
        $ctx->vg->fill();

        // calculate and draw animated highlight box for selected option
        $selectedOption = $this->selectedOption;
        if ($selectedOption !== null && isset($buttonOffsets[$selectedOption], $buttonWidths[$selectedOption])) {
            $targetOffset = $buttonOffsets[$selectedOption];
            $targetWidth = $buttonWidths[$selectedOption];
            
            $targetX = $originX + $targetOffset - $buttonSpacing + $innerOffset;
            $targetW = $targetWidth + $buttonSpacingDouble - $innerOffsetDouble;
            
            $animKeyX = $this->buttonGroupId . '_highlight_x';
            $animKeyW = $this->buttonGroupId . '_highlight_w';
            
            // get current animated position, default to target if first frame
            $currentX = $ctx->getStaticValue($animKeyX, $targetX);
            $currentW = $ctx->getStaticValue($animKeyW, $targetW);
            
            // smooth interpolation towards target (assume 60 FPS)
            $lerpFactor = 1.0 - exp(-$style->animationSpeed * 0.016666667);
            
            $newX = $currentX + ($targetX - $currentX) * $lerpFactor;
            $newW = $currentW + ($targetW - $currentW) * $lerpFactor;
            
            // store new animated values
            $ctx->setStaticValue($animKeyX, $newX);
            $ctx->setStaticValue($animKeyW, $newW);
            
            // draw the animated highlight box
            $highlightY = $originY + $innerOffset;
            $highlightH = $totalHeight - $innerOffsetDouble;
            
            $ctx->vg->beginPath();
            $ctx->vg->roundedRect($newX, $highlightY, $newW, $highlightH, $style->buttonCornerRadius);
            $ctx->vg->fillColor($style->activeBackgroundColor);
            $ctx->vg->fill();
        }

        // cache mouse state for all buttons
        $mousePressed = $ctx->input->isMouseButtonPressed(MouseButton::LEFT);
        $totalHeightHalf = $totalHeight * 0.5;
        $buttonGroupId = $this->buttonGroupId;
        $selectedOptionRef = &$this->selectedOptionRef;
        $onSelectCallback = $this->onSelect;
        
        // first pass: render all text labels with inactive/hover colors (underneath the highlight)
        foreach ($this->options as $key => $option) {
            $currentWidth = $buttonWidths[$key];
            $currentOffset = $buttonOffsets[$key];

            $bx = $originX + $currentOffset - $buttonSpacing + $innerOffset;
            $bw = $currentWidth + $buttonSpacingDouble - $innerOffsetDouble;
            $by = $originY + $innerOffset;
            $bh = $totalHeight - $innerOffsetDouble;

            $buttonId = $buttonGroupId . '_' . $key;

            // check if mouse is inside this button using context helper
            $buttonPos = new Vec2($bx, $by);
            $buttonSize = new Vec2($bw, $bh);
            $isInside = $ctx->isHoveredAt($buttonPos, $buttonSize);

            $isActive = $selectedOption === $key;

            // handle button click using the context's triggeredOnce helper
            $isClicked = $ctx->triggeredOnce($buttonId . '_click', $isInside && $mousePressed);

            if ($isClicked) {
                $oldSelection = $this->selectedOption;
                $this->selectedOption = $key;
                
                // update the external reference if it exists
                if (isset($selectedOptionRef)) {
                    $selectedOptionRef = $key;
                }
                
                // if this is the first selection, initialize animation position immediately
                if ($oldSelection === null) {
                    $animKeyX = $buttonGroupId . '_highlight_x';
                    $animKeyW = $buttonGroupId . '_highlight_w';
                    $initX = $originX + $currentOffset - $buttonSpacing + $innerOffset;
                    $initW = $currentWidth + $buttonSpacingDouble - $innerOffsetDouble;
                    $ctx->setStaticValue($animKeyX, $initX);
                    $ctx->setStaticValue($animKeyW, $initW);
                }
                
                if ($onSelectCallback) {
                    $onSelectCallback($key);
                }
            }

            // draw hover background for non-active buttons with fade animation
            if (!$isActive) {
                $hoverAnimKey = $buttonGroupId . '_hover_' . $key;
                
                // get current hover alpha (0.0 to 1.0)
                $currentAlpha = $ctx->getStaticValue($hoverAnimKey, 0.0);
                
                // target alpha based on hover state
                $targetAlpha = $isInside ? 1.0 : 0.0;
                
                // smooth fade interpolation (faster fade)
                $fadeLerpFactor = 1.0 - exp(-$style->animationSpeed * 1.5 * 0.016666667);
                $newAlpha = $currentAlpha + ($targetAlpha - $currentAlpha) * $fadeLerpFactor;
                
                // store new alpha value
                $ctx->setStaticValue($hoverAnimKey, $newAlpha);
                
                // only draw if there's visible alpha
                if ($newAlpha > 0.01) {
                    $ctx->vg->beginPath();
                    $ctx->vg->roundedRect($bx, $by, $bw, $bh, $style->buttonCornerRadius);
                    
                    // use configurable gray color for hover with animated alpha
                    $baseColor = $style->hoverOverlayColor;
                    $hoverColor = new \GL\VectorGraphics\VGColor(
                        $baseColor->r ?? 0.0, 
                        $baseColor->g ?? 0.0, 
                        $baseColor->b ?? 0.0, 
                        ($baseColor->a ?? 0.1) * $newAlpha
                    );
                    $ctx->vg->fillColor($hoverColor);
                    $ctx->vg->fill();
                }
            }

            // set text color based on state (inactive or hover)
            if ($isInside && !$isActive) {
                $ctx->vg->fillColor($style->hoverTextColor);
            } else {
                $ctx->vg->fillColor($style->inactiveTextColor);
            }

            // draw the button label
            $ctx->vg->text($originX + $currentOffset, $originY + $totalHeightHalf, $option);
        }

        // second pass: render active text with clipping mask over the highlight box
        if ($selectedOption !== null && isset($buttonOffsets[$selectedOption], $buttonWidths[$selectedOption])) {
            // get the animated highlight box position from earlier calculation
            $animKeyX = $buttonGroupId . '_highlight_x';
            $animKeyW = $buttonGroupId . '_highlight_w';
            $highlightX = $ctx->getStaticValue($animKeyX, 0.0);
            $highlightW = $ctx->getStaticValue($animKeyW, 0.0);
            $highlightY = $originY + $innerOffset;
            $highlightH = $totalHeight - $innerOffsetDouble;

            // set up scissor clipping to the highlight box area
            $ctx->vg->scissor($highlightX, $highlightY, $highlightW, $highlightH);

            // render all text again with active text color, but only visible in clipped area
            $ctx->vg->fillColor($style->activeTextColor);
            foreach ($this->options as $key => $option) {
                $currentOffset = $buttonOffsets[$key];
                $ctx->vg->text($originX + $currentOffset, $originY + $totalHeightHalf, $option);
            }

            // reset scissor clipping
            $ctx->vg->resetScissor();
        }
    }
}