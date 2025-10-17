<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGContext;
use VISU\OS\Input;

class FUIRenderContext
{
    /**
     * Absolute origin of the current view
     */
    public Vec2 $origin;

    /**
     * The size of the current view
     */
    public Vec2 $containerSize;

    /**
     * Current cursor position 
     */
    public Vec2 $mousePos;

    /**
     * Current font face
     */
    private string $fontFace = '';

    /**
     * Static data storage for persistent values across frames
     * 
     * @var array<string, mixed>
     */
    private static array $persistentData = [];

    /**
     * Returns true if the mouse is currently hovering over the current bounds
     */
    public function isHovered() : bool
    {
        return $this->mousePos->x >= $this->origin->x && $this->mousePos->x <= $this->origin->x + $this->containerSize->x
            && $this->mousePos->y >= $this->origin->y && $this->mousePos->y <= $this->origin->y + $this->containerSize->y;
    }

    /**
     * Returns true if the given Position and Size are currently hovered by the mouse
     */
    public function isHoveredAt(Vec2 $pos, Vec2 $size) : bool
    {
        return $this->mousePos->x >= $pos->x && $this->mousePos->x <= $pos->x + $size->x
            && $this->mousePos->y >= $pos->y && $this->mousePos->y <= $pos->y + $size->y;
    }

    /**
     * Returns true if the given AABB is currently hovered by the mouse
     */
    public function isHoveredAABB(float $x, float $y, float $width, float $height) : bool
    {
        return $this->mousePos->x >= $x && $this->mousePos->x <= $x + $width
            && $this->mousePos->y >= $y && $this->mousePos->y <= $y + $height;
    }
    
    /**
     * Returns boolean if an action + el id has been triggered once.
     * Basically will return true only once and false if the condition is met again until reset.
     * Reset is done by calling the function with the condition set to false.
     */
    public function triggeredOnce(string $id, bool $condition) : bool
    {
        static $fuiTriggeredStates = [];
        if (!isset($fuiTriggeredStates[$id])) {
            $fuiTriggeredStates[$id] = false;
        }

        if ($condition) {
            if (!$fuiTriggeredStates[$id]) {
                $fuiTriggeredStates[$id] = true;
                return true;
            }
        } else {
            $fuiTriggeredStates[$id] = false;
        }

        return false;
    }

    /**
     * Ensures the given font face is set
     */
    public function ensureFontFace(string $fontFace) : void
    {
        if ($this->fontFace !== $fontFace) {
            $this->vg->fontFace($fontFace);
            $this->fontFace = $fontFace;
        }
    }

    /**
     * Ensures the default "regular" font face is set
     */
    public function ensureRegularFontFace() : void
    {
        $this->ensureFontFace($this->theme->regularFont);
    }

    /**
     * Ensures the default "semi bold" font face is set
     */
    public function ensureSemiBoldFontFace() : void
    {
        $this->ensureFontFace($this->theme->semiBoldFont);
    }
    
    /**
     * Sets a static value (persistant data, over multiple frames)
     */
    public function setStaticValue(string $key, mixed $value) : void
    {
        self::$persistentData[$key] = $value;
    }

    /**
     * Gets a static value (persistant data, over multiple frames)
     */
    public function getStaticValue(string $key, mixed $default = null) : mixed
    {
        return self::$persistentData[$key] ?? $default;
    }

    /**
     * Clears a specific static value
     */
    public function clearStaticValue(string $key) : void
    {
        unset(self::$persistentData[$key]);
    }

    /**
     * Clears all static values
     */
    public function clearAllStaticValues() : void
    {
        self::$persistentData = [];
    }

    /**
     * Initializes the render context
     */
    public function __construct(
        public VGContext $vg,
        public Input $input,
        public FUITheme $theme
    )
    {
        $this->origin = new Vec2(0, 0);
        $this->containerSize = new Vec2(0, 0);
        $this->mousePos = $this->input->getCursorPosition();
    }
}