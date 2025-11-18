<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\Theme\FUISelectStyle;
use VISU\OS\MouseButton;

class FUISelect extends FUIView
{
    /**
     * The style of the select
     */
    public FUISelectStyle $style;

    /**
     * Select ID
     */
    public string $selectId;

    /**
     * @var array<string, string> Array of key => label pairs
     */
    public array $options;

    /**
     * A Label string that will be rendered before the select
     */
    public ?string $label = null;

    /**
     * The currently selected option key
     */
    public ?string $selectedOption = null;

    /**
     * Reference to external selected option variable (if provided)
     */
    private ?string $selectedOptionRef = null;

    /**
     * Whether the dropdown is currently open (stored in context for persistence)
     */
    private function isOpen(FUIRenderContext $ctx): bool
    {
        return $ctx->getStaticValue($this->selectId . '_is_open', false);
    }

    /**
     * Sets the dropdown open state
     */
    private function setOpen(FUIRenderContext $ctx, bool $open): void
    {
        $ctx->setStaticValue($this->selectId . '_is_open', $open);
    }

    /**
     * Constructs a new select
     * 
     * @param string $name The name of the select, will also be used for the ID, so must be unique
     * @param array<string, string> $options Array of key => label pairs
     */
    public function __construct(
        string $name,
        array $options,
        ?string &$selectedOption = null,
        public ?\Closure $onSelect = null,
        ?string $selectId = null,
        ?FUISelectStyle $selectStyle = null,
    ) {
        $this->style = $selectStyle ?? FlyUI::$instance->theme->select;
        parent::__construct(clone $this->style->padding);

        $this->options = $options;
        $this->selectedOption = $selectedOption;
        $this->selectedOptionRef = &$selectedOption;

        // select ID, based on name if not provided
        $this->label = $name;
        $this->selectId = $selectId ?? 'select_' . $name;
    }

    /**
     * Applies the given select style
     */
    public function applyStyle(FUISelectStyle $style): self
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
     * Sets the select ID
     */
    public function setId(string $id): self
    {
        $this->selectId = $id;
        return $this;
    }

    /**
     * Hides the label for the select
     */
    public function hideLabel(): self
    {
        $this->label = null;
        return $this;
    }

    /**
     * Returns the estimated size of the select
     */
    public function getEstimatedSize(FUIRenderContext $ctx): Vec2
    {
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($this->style->fontSize);

        // find the widest option text to determine select width
        $maxWidth = 0.0;
        foreach ($this->options as $option) {
            $textWidth = $ctx->vg->textBounds(0, 0, $option);
            $maxWidth = max($maxWidth, $textWidth);
        }

        // the max width should also consider the selected text
        $selectedText = $this->selectedOption !== null && isset($this->options[$this->selectedOption])
            ? $this->options[$this->selectedOption]
            : $this->style->nothingSelectedText;

        $maxWidth = max($maxWidth, $ctx->vg->textBounds(0, 0, $selectedText));

        // add horizontal padding (left + right) and arrow space
        $totalWidth = $maxWidth + $this->style->padding->x + $this->style->padding->y + 30.0; // 30 for arrow

        // calculate select box height using vertical padding (top + bottom)
        $selectBoxHeight = $this->style->fontSize + $this->style->padding->z + $this->style->padding->w;

        // add label height if present
        $labelHeight = 0.0;
        if ($this->label !== null) {
            $label = new FUILabel($this->label);
            $labelHeight = $label->getLabelHeight();
        }

        $totalHeight = $labelHeight + $selectBoxHeight;

        return new Vec2($totalWidth, $totalHeight);
    }

    /**
     * Renders the select
     */
    public function render(FUIRenderContext $ctx): void
    {
        $style = $this->style;
        $labelHeight = 0;

        if ($this->label !== null) {
            $label = new FUILabel($this->label);
            $labelHeight = $label->getLabelHeight();
            $label->render($ctx);
        }

        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($style->fontSize);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        $estimatedSize = $this->getEstimatedSize($ctx);
        $selectWidth = $estimatedSize->x;

        // calculate the actual select box height (excluding label)
        $selectBoxHeight = $this->style->fontSize + $this->style->padding->z + $this->style->padding->w;

        // update container size
        $ctx->containerSize->x = $selectWidth;
        $ctx->containerSize->y = $estimatedSize->y;

        // select box coordinates
        $originX = $ctx->origin->x;
        $originY = $ctx->origin->y + $labelHeight;

        // check if mouse is inside the select box
        $selectPos = new Vec2($originX, $originY);
        $selectSize = new Vec2($selectWidth, $selectBoxHeight);
        $isHoveringSelect = $ctx->isHoveredAt($selectPos, $selectSize);

        // handle select box click to toggle dropdown
        $mousePressed = $ctx->input->isMouseButtonPressed(MouseButton::LEFT);
        $selectClicked = $ctx->triggeredOnce($this->selectId . '_toggle', $isHoveringSelect && $mousePressed);

        if ($selectClicked) {
            $this->setOpen($ctx, !$this->isOpen($ctx));
        }

        // close dropdown if clicked outside
        $isCurrentlyOpen = $this->isOpen($ctx);
        if ($mousePressed && !$isHoveringSelect && $isCurrentlyOpen) {
            // check if clicked on any dropdown option
            $clickedOnDropdown = false;
            $dropdownY = $originY + $selectBoxHeight;
            $optionCount = count($this->options);
            $dropdownHeight = min($optionCount * $style->optionHeight, $style->maxDropdownHeight);

            $dropdownPos = new Vec2($originX, $dropdownY);
            $dropdownSize = new Vec2($selectWidth, $dropdownHeight);
            $clickedOnDropdown = $ctx->isHoveredAt($dropdownPos, $dropdownSize);

            if (!$clickedOnDropdown) {
                $this->setOpen($ctx, false);
            }
        }

        // draw the select box background
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect($originX, $originY, $selectWidth, $selectBoxHeight, $style->cornerRadius);
        $ctx->vg->strokeWidth(1.0);

        if ($isHoveringSelect) {
            $ctx->vg->fillColor($style->hoverBackgroundColor);
        } else {
            $ctx->vg->fillColor($style->backgroundColor);
        }

        $ctx->vg->strokeColor($style->borderColor);
        $ctx->vg->stroke();
        $ctx->vg->fill();

        // draw the selected text with proper visual centering
        $hasSelectedOption = $this->selectedOption !== null && isset($this->options[$this->selectedOption]);
        $selectedText = $hasSelectedOption
            ? $this->options[$this->selectedOption]
            : $style->nothingSelectedText;

        // calculate vertical center for text
        $ascender = 0.0;
        $descender = 0.0;
        $lineHeight = 0.0;
        $ctx->vg->textMetrics($ascender, $descender, $lineHeight);

        $selectCenterY = $originY + $selectBoxHeight * 0.5;
        $selectCenterY += (-$descender) * 0.25; // adjust for visual centering

        $ctx->vg->fillColor($hasSelectedOption ? $style->textColor : $style->textPlaceholderColor);
        $ctx->vg->text(
            $originX + $style->padding->x,
            ceil($selectCenterY),
            $selectedText
        );

        // draw the dropdown arrow
        $arrowX = $originX + $selectWidth - 15.0;
        $arrowY = $originY + $selectBoxHeight * 0.5;
        $arrowSize = 5.0;

        $ctx->vg->beginPath();
        if ($this->isOpen($ctx)) {
            // up arrow
            $ctx->vg->moveTo($arrowX - $arrowSize, $arrowY + $arrowSize * 0.5);
            $ctx->vg->lineTo($arrowX, $arrowY - $arrowSize * 0.5);
            $ctx->vg->lineTo($arrowX + $arrowSize, $arrowY + $arrowSize * 0.5);
        } else {
            // down arrow
            $ctx->vg->moveTo($arrowX - $arrowSize, $arrowY - $arrowSize * 0.5);
            $ctx->vg->lineTo($arrowX, $arrowY + $arrowSize * 0.5);
            $ctx->vg->lineTo($arrowX + $arrowSize, $arrowY - $arrowSize * 0.5);
        }
        $ctx->vg->strokeColor($style->arrowColor);
        $ctx->vg->strokeWidth(1.0);
        $ctx->vg->stroke();

        // defer dropdown rendering to ensure it appears on top
        if ($this->isOpen($ctx)) {
            $dropdownX = $originX;
            $dropdownY = $originY + $selectBoxHeight;
            $dropdownWidth = $selectWidth;

            $ctx->deferRender(function (FUIRenderContext $ctx) use ($dropdownX, $dropdownY, $dropdownWidth) {
                $this->renderDropdown($ctx, $dropdownX, $dropdownY, $dropdownWidth);
            });
        }
    }

    /**
     * Renders the dropdown list
     */
    private function renderDropdown(FUIRenderContext $ctx, float $x, float $y, float $width): void
    {
        $style = $this->style;
        $optionCount = count($this->options);
        $dropdownHeight = min($optionCount * $style->optionHeight, $style->maxDropdownHeight);

        // draw dropdown background
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect($x, $y, $width, $dropdownHeight, $style->dropdownCornerRadius);
        $ctx->vg->fillColor($style->dropdownBackgroundColor);
        $ctx->vg->strokeColor($style->dropdownBorderColor);
        $ctx->vg->strokeWidth(1.0);
        $ctx->vg->fill();
        $ctx->vg->stroke();

        // render options
        $currentY = $y;
        $optionIndex = 0;

        $ctx->vg->fontSize($style->fontSize);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        foreach ($this->options as $key => $label) {
            if ($currentY + $style->optionHeight > $y + $dropdownHeight) {
                break; // TODO: implement scrolling (MARIO if you forget this again....)
            }

            // check if mouse is hovering this option
            $optionPos = new Vec2($x, $currentY);
            $optionSize = new Vec2($width, $style->optionHeight);
            $isHoveringOption = $ctx->isHoveredAt($optionPos, $optionSize);

            // handle option click
            $optionClicked = $ctx->triggeredOnce($this->selectId . '_option_' . $key, $isHoveringOption && $ctx->input->isMouseButtonPressed(MouseButton::LEFT));
            if ($optionClicked) {
                $this->selectedOption = $key;

                // update the external reference if it exists
                if (isset($this->selectedOptionRef)) {
                    $this->selectedOptionRef = $key;
                }

                $this->setOpen($ctx, false);

                // always trigger onSelect callback, even for reselecting the same option
                if ($this->onSelect) {
                    ($this->onSelect)($key);
                }
            }

            // draw option background
            $isSelected = $this->selectedOption === $key;

            if ($isSelected) {
                $ctx->vg->beginPath();
                $ctx->vg->rect($x, $currentY, $width, $style->optionHeight);
                $ctx->vg->fillColor($style->optionSelectedBackgroundColor);
                $ctx->vg->fill();
            } else if ($isHoveringOption) {
                $ctx->vg->beginPath();
                $ctx->vg->rect($x, $currentY, $width, $style->optionHeight);
                $ctx->vg->fillColor($style->optionHoverBackgroundColor);
                $ctx->vg->fill();
            }

            // draw option text with proper visual centering
            $textColor = $isSelected ? $style->optionSelectedTextColor : ($isHoveringOption ? $style->optionHoverTextColor : $style->optionTextColor);

            $ascender = 0.0;
            $descender = 0.0;
            $lineHeight = 0.0;
            $ctx->vg->textMetrics($ascender, $descender, $lineHeight);

            $optionCenterY = $currentY + $style->optionHeight * 0.5;
            $optionCenterY += (-$descender) * 0.25;

            $ctx->vg->fillColor($textColor);
            $ctx->vg->text(
                $x + $style->padding->x,
                ceil($optionCenterY),
                $label
            );

            $currentY += $style->optionHeight;
            $optionIndex++;
        }
    }
}
