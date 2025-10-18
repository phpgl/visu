<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
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
     * Constructs a new button group
     * 
     * @param array<string, string> $options Array of key => label pairs
     * @param string|null $selectedOption Initially selected option key
     * @param \Closure|null $onSelect Callback function called when selection changes
     * @param string|null $buttonGroupId Unique identifier for this button group
     * @param FUIButtonGroupStyle|null $buttonGroupStyle Custom style for the button group
     */
    public function __construct(
        array $options,
        ?string $selectedOption = null,
        public ?\Closure $onSelect = null,
        ?string $buttonGroupId = null,
        ?FUIButtonGroupStyle $buttonGroupStyle = null
    )
    {
        $this->style = $buttonGroupStyle ?? FlyUI::$instance->theme->buttonGroup;
        parent::__construct(clone $this->style->padding);

        $this->options = $options;
        $this->selectedOption = $selectedOption;

        // Generate unique ID if not provided
        $this->buttonGroupId = $buttonGroupId ?? 'btngroup_' . uniqid();
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
        $ctx->vg->fontSize($this->style->fontSize);

        $totalWidth = $this->style->buttonSpacing;
        $lineHeight = $this->style->fontSize * 1.8;

        foreach ($this->options as $option) {
            $buttonWidth = $ctx->vg->textBounds(0, 0, $option);
            $totalWidth += $buttonWidth + $this->style->buttonSpacing * 2;
        }

        $totalWidth -= $this->style->buttonSpacing; // Remove last spacing
        $totalHeight = $lineHeight + $this->style->innerOffset * 2;

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
        $ctx->vg->fontSize($this->style->fontSize);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        // Calculate button widths and positions
        $buttonWidths = [];
        $buttonOffsets = [];
        $offsetc = $this->style->buttonSpacing;

        foreach ($this->options as $key => $option) {
            $buttonWidths[$key] = $ctx->vg->textBounds(0, 0, $option);
            $buttonOffsets[$key] = $offsetc;
            $offsetc += $buttonWidths[$key] + $this->style->buttonSpacing * 2;
        }

        // Draw the background container
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect(
            $ctx->origin->x, 
            $ctx->origin->y, 
            $totalWidth, 
            $totalHeight, 
            $this->style->cornerRadius
        );
        $ctx->vg->fillColor($this->style->backgroundColor);
        $ctx->vg->strokeColor($this->style->borderColor);
        $ctx->vg->fill();

        // Draw individual buttons
        foreach ($this->options as $key => $option) {
            $currentWidth = $buttonWidths[$key];
            $currentOffset = $buttonOffsets[$key];

            $bx = $ctx->origin->x + $currentOffset - $this->style->buttonSpacing + $this->style->innerOffset;
            $bw = $currentWidth + $this->style->buttonSpacing * 2 - $this->style->innerOffset * 2;
            $by = $ctx->origin->y + $this->style->innerOffset;
            $bh = $totalHeight - $this->style->innerOffset * 2;

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
                $ctx->vg->roundedRect($bx, $by, $bw, $bh, $this->style->buttonCornerRadius);

                if ($isInside && !$isActive) {
                    $ctx->vg->fillColor($this->style->hoverBackgroundColor);
                } else {
                    $ctx->vg->fillColor($this->style->activeBackgroundColor);
                }
                $ctx->vg->fill();

                // Set text color for active/hovered buttons
                if ($isInside && !$isActive) {
                    $ctx->vg->fillColor($this->style->hoverTextColor);
                } else {
                    $ctx->vg->fillColor($this->style->activeTextColor);
                }
            } else {
                // Set text color for inactive buttons
                $ctx->vg->fillColor($this->style->inactiveTextColor);
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