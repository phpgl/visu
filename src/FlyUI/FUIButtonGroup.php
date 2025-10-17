<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\OS\MouseButton;

class FUIButtonGroup extends FUIView
{
    public VGColor $backgroundColor;
    public VGColor $borderColor;
    public VGColor $activeBackgroundColor;
    public VGColor $hoverBackgroundColor;
    public VGColor $activeTextColor;
    public VGColor $inactiveTextColor;

    public float $borderRadius;
    public float $buttonBorderRadius;
    public float $fontSize;
    public float $buttonSpacing;
    public float $innerOffset;

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
     * Constructs a new button group
     * 
     * @param array<string, string> $options Array of key => label pairs
     * @param string|null $selectedOption Initially selected option key
     * @param \Closure|null $onSelect Callback function called when selection changes
     * @param string|null $buttonGroupId Unique identifier for this button group
     */
    public function __construct(
        array $options,
        ?string $selectedOption = null,
        public ?\Closure $onSelect = null,
        ?string $buttonGroupId = null
    )
    {
        parent::__construct(FlyUI::$instance->theme->buttonPadding->copy());

        $this->options = $options;
        $this->selectedOption = $selectedOption;

        // Theme colors
        $this->backgroundColor = VGColor::white();
        $this->borderColor = new VGColor(0.996, 0.996, 0.996, 1.0); // #FEFEFE equivalent
        $this->activeBackgroundColor = FlyUI::$instance->theme->buttonPrimaryBackgroundColor;
        $this->hoverBackgroundColor = FlyUI::$instance->theme->buttonPrimaryHoverBackgroundColor;
        $this->activeTextColor = VGColor::white();
        $this->inactiveTextColor = VGColor::black();

        // Styling properties
        $this->borderRadius = 10.0;
        $this->buttonBorderRadius = 7.0;
        $this->fontSize = FlyUI::$instance->theme->buttonFontSize;
        $this->buttonSpacing = 30.0;
        $this->innerOffset = 4.0;

        // Generate unique ID if not provided
        $this->buttonGroupId = $buttonGroupId ?? 'btngroup_' . uniqid();
    }

    /**
     * Sets the selected option
     */
    public function setSelectedOption(?string $selectedOption): self
    {
        $this->selectedOption = $selectedOption;
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
     * Returns the estimated size of the button group
     */
    public function getEstimatedSize(FUIRenderContext $ctx): Vec2
    {
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->fontSize);

        $totalWidth = $this->buttonSpacing;
        $lineHeight = $this->fontSize * 1.8;

        foreach ($this->options as $option) {
            $buttonWidth = $ctx->vg->textBounds(0, 0, $option);
            $totalWidth += $buttonWidth + $this->buttonSpacing * 2;
        }

        $totalWidth -= $this->buttonSpacing; // Remove last spacing
        $totalHeight = $lineHeight + $this->innerOffset * 2;

        return new Vec2($totalWidth, $totalHeight);
    }

    /**
     * Renders the button group
     */
    public function render(FUIRenderContext $ctx): void
    {
        $estimatedSize = $this->getEstimatedSize($ctx);
        $totalWidth = $estimatedSize->x;
        $totalHeight = $estimatedSize->y;

        // Update container size
        $ctx->containerSize->x = $totalWidth;
        $ctx->containerSize->y = $totalHeight;

        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        // Calculate button widths and positions
        $buttonWidths = [];
        $buttonOffsets = [];
        $offsetc = $this->buttonSpacing;

        foreach ($this->options as $key => $option) {
            $buttonWidths[$key] = $ctx->vg->textBounds(0, 0, $option);
            $buttonOffsets[$key] = $offsetc;
            $offsetc += $buttonWidths[$key] + $this->buttonSpacing * 2;
        }

        // Draw the background container
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect(
            $ctx->origin->x, 
            $ctx->origin->y, 
            $totalWidth, 
            $totalHeight, 
            $this->borderRadius
        );
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->fill();

        // Draw individual buttons
        foreach ($this->options as $key => $option) {
            $currentWidth = $buttonWidths[$key];
            $currentOffset = $buttonOffsets[$key];

            $bx = $ctx->origin->x + $currentOffset - $this->buttonSpacing + $this->innerOffset;
            $bw = $currentWidth + $this->buttonSpacing * 2 - $this->innerOffset * 2;
            $by = $ctx->origin->y + $this->innerOffset;
            $bh = $totalHeight - $this->innerOffset * 2;

            $buttonId = $this->buttonGroupId . '_' . $key;

            // Check if mouse is inside this button using context helper
            $buttonPos = new Vec2($bx, $by);
            $buttonSize = new Vec2($bw, $bh);
            $isInside = $ctx->isHoveredAt($buttonPos, $buttonSize);

            $isActive = $this->selectedOption === $key;

            // Handle button click using the context's triggeredOnce helper
            $isClicked = $ctx->triggeredOnce(
                $buttonId . '_click', 
                $isInside && $ctx->input->isMouseButtonPressed(MouseButton::LEFT)
            );

            if ($isClicked) {
                $this->selectedOption = $key;
                if ($this->onSelect) {
                    ($this->onSelect)($key);
                }
            }

            // Draw button background if active or hovered
            if ($isActive || $isInside) {
                $ctx->vg->beginPath();
                $ctx->vg->roundedRect($bx, $by, $bw, $bh, $this->buttonBorderRadius);

                if ($isInside && !$isActive) {
                    $ctx->vg->fillColor($this->hoverBackgroundColor);
                } else {
                    $ctx->vg->fillColor($this->activeBackgroundColor);
                }
                $ctx->vg->fill();

                // Set text color for active/hovered buttons
                $ctx->vg->fillColor($this->activeTextColor);
            } else {
                // Set text color for inactive buttons
                $ctx->vg->fillColor($this->inactiveTextColor);
            }

            // Draw the button label
            $ctx->vg->text(
                $ctx->origin->x + $currentOffset, 
                $ctx->origin->y + $totalHeight * 0.5, 
                $option
            );
        }
    }
}