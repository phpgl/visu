<?php

namespace VISU\Testing;

use GL\Math\Vec2;
use VISU\OS\CursorMode;
use VISU\OS\InputInterface;
use VISU\Signal\DispatcherInterface;

/**
 * GLFW-free input implementation for use in headless / unit tests.
 *
 * Usage:
 *   $input = new FakeInput($dispatcher);
 *
 *   // Move the cursor
 *   $input->simulateCursorPos(320.0, 240.0);
 *
 *   // Press and hold a mouse button
 *   $input->simulateMouseButton(0, true);
 *
 *   // Press a key this frame
 *   $input->simulateKeyPress(Key::ESCAPE);
 *
 *   // Advance one frame (clears per-frame state)
 *   $input->endFrame();
 */
final class FakeInput implements InputInterface
{
    public const PRESS   = 1; // GLFW_PRESS
    public const RELEASE = 0; // GLFW_RELEASE
    public const REPEAT  = 2; // GLFW_REPEAT

    // ── Cursor ────────────────────────────────────────────────────────────
    // Stored as plain floats to avoid Vec2 property-access quirks in php-glfw
    // when objects are assigned/read across multiple PHPUnit frames.
    private float $cursorX     = 0.0;
    private float $cursorY     = 0.0;
    private float $lastCursorX = 0.0;
    private float $lastCursorY = 0.0;

    // ── Keys ─────────────────────────────────────────────────────────────
    /** @var array<int, int> key => PRESS|RELEASE|REPEAT */
    private array $keyStates = [];

    /** @var array<int, bool> keys pressed since last endFrame() */
    private array $keysDidPress = [];

    /** @var array<int, bool> keys released since last endFrame() */
    private array $keysDidRelease = [];

    /** @var array<int, bool> keys pressed this frame only */
    private array $keysDidPressFrame = [];

    /** @var array<int, bool> keys released this frame only */
    private array $keysDidReleaseFrame = [];

    // ── Mouse buttons ─────────────────────────────────────────────────────
    /** @var array<int, int> button => PRESS|RELEASE */
    private array $mouseButtonStates = [];

    /** @var array<int, bool> */
    private array $mouseButtonsDidPress = [];

    /** @var array<int, bool> */
    private array $mouseButtonsDidRelease = [];

    /** @var array<int, bool> */
    private array $mouseButtonsDidPressFrame = [];

    /** @var array<int, bool> */
    private array $mouseButtonsDidReleaseFrame = [];

    // ── Cursor mode ───────────────────────────────────────────────────────
    private CursorMode $cursorMode = CursorMode::NORMAL;

    // ── Input context ─────────────────────────────────────────────────────
    private ?string $inputContext = null;

    public function __construct(
        private readonly DispatcherInterface $dispatcher,
        private readonly int $windowWidth  = 1280,
        private readonly int $windowHeight = 720,
    ) {
    }

    // ── Simulation API ────────────────────────────────────────────────────

    /**
     * Move the cursor to the given window-space position and dispatch a CursorPosSignal.
     */
    public function simulateCursorPos(float $x, float $y): void
    {
        $this->lastCursorX = $this->cursorX;
        $this->lastCursorY = $this->cursorY;
        $this->cursorX     = $x;
        $this->cursorY     = $y;
    }

    /**
     * Press or release a mouse button and dispatch a MouseButtonSignal.
     *
     * @param int  $button 0 = left, 1 = right, 2 = middle
     * @param bool $press  true = press, false = release
     */
    public function simulateMouseButton(int $button, bool $press): void
    {
        $action = $press ? self::PRESS : self::RELEASE;
        $this->mouseButtonStates[$button] = $action;

        if ($press) {
            $this->mouseButtonsDidPress[$button]      = true;
            $this->mouseButtonsDidPressFrame[$button] = true;
        } else {
            $this->mouseButtonsDidRelease[$button]      = true;
            $this->mouseButtonsDidReleaseFrame[$button] = true;
        }
    }

    /**
     * Simulate a full click (press + release) on the current cursor position.
     *
     * @param int $button 0 = left, 1 = right, 2 = middle
     */
    public function simulateClick(int $button = 0): void
    {
        $this->simulateMouseButton($button, true);
        $this->simulateMouseButton($button, false);
    }

    /**
     * Register a key press for this frame.
     */
    public function simulateKeyPress(int $key): void
    {
        $this->keyStates[$key]         = self::PRESS;
        $this->keysDidPress[$key]      = true;
        $this->keysDidPressFrame[$key] = true;
    }

    /**
     * Register a key release for this frame.
     */
    public function simulateKeyRelease(int $key): void
    {
        $this->keyStates[$key]           = self::RELEASE;
        $this->keysDidRelease[$key]      = true;
        $this->keysDidReleaseFrame[$key] = true;
    }

    /**
     * Placeholder for character input simulation.
     * Full signal dispatch requires a Window object — use the real Input class
     * wired to a hidden GLFW window (VisualTestCase) for char/scroll signal tests.
     */
    public function simulateChar(int $codepoint): void
    {
        // no-op: CharSignal requires a Window reference (php-glfw limitation)
    }

    /**
     * Placeholder for scroll simulation.
     * Full signal dispatch requires a Window object — use VisualTestCase for scroll tests.
     */
    public function simulateScroll(float $xOffset, float $yOffset): void
    {
        // no-op: ScrollSignal requires a Window reference (php-glfw limitation)
    }

    /**
     * Advance one frame: clears per-frame state (pressed/released this frame).
     * Call this at the end of each simulated frame.
     */
    public function endFrame(): void
    {
        $this->keysDidPressFrame          = [];
        $this->keysDidReleaseFrame        = [];
        $this->keysDidPress               = [];
        $this->keysDidRelease             = [];
        $this->mouseButtonsDidPressFrame  = [];
        $this->mouseButtonsDidReleaseFrame = [];
        $this->mouseButtonsDidPress       = [];
        $this->mouseButtonsDidRelease     = [];
    }

    // ── InputInterface ────────────────────────────────────────────────────

    public function getKeyState(int $key): int
    {
        return $this->keyStates[$key] ?? self::RELEASE;
    }

    public function isKeyPressed(int $key): bool
    {
        return $this->getKeyState($key) === self::PRESS;
    }

    public function isKeyReleased(int $key): bool
    {
        return $this->getKeyState($key) === self::RELEASE;
    }

    public function isKeyRepeated(int $key): bool
    {
        return $this->getKeyState($key) === self::REPEAT;
    }

    public function getMouseButtonState(int $button): int
    {
        return $this->mouseButtonStates[$button] ?? self::RELEASE;
    }

    public function isMouseButtonPressed(int $button): bool
    {
        return $this->getMouseButtonState($button) === self::PRESS;
    }

    public function isMouseButtonReleased(int $button): bool
    {
        return $this->getMouseButtonState($button) === self::RELEASE;
    }

    public function hasMouseButtonBeenPressed(int $button): bool
    {
        return $this->mouseButtonsDidPress[$button] ?? false;
    }

    public function hasMouseButtonBeenReleased(int $button): bool
    {
        return $this->mouseButtonsDidRelease[$button] ?? false;
    }

    public function hasMouseButtonBeenPressedThisFrame(int $button): bool
    {
        return $this->mouseButtonsDidPressFrame[$button] ?? false;
    }

    public function hasMouseButtonBeenReleasedThisFrame(int $button): bool
    {
        return $this->mouseButtonsDidReleaseFrame[$button] ?? false;
    }

    public function hasKeyBeenPressed(int $key): bool
    {
        return $this->keysDidPress[$key] ?? false;
    }

    public function hasKeyBeenReleased(int $key): bool
    {
        return $this->keysDidRelease[$key] ?? false;
    }

    public function hasKeyBeenPressedThisFrame(int $key): bool
    {
        return $this->keysDidPressFrame[$key] ?? false;
    }

    public function hasKeyBeenReleasedThisFrame(int $key): bool
    {
        return $this->keysDidReleaseFrame[$key] ?? false;
    }

    public function getKeyPresses(): array
    {
        return array_keys($this->keysDidPress);
    }

    public function getKeyPressesThisFrame(): array
    {
        return array_keys($this->keysDidPressFrame);
    }

    public function getCursorPosition(): Vec2
    {
        return new Vec2($this->cursorX, $this->cursorY);
    }

    public function getNormalizedCursorPosition(): Vec2
    {
        return new Vec2(
            ($this->cursorX / max(1, $this->windowWidth))  * 2.0 - 1.0,
            ($this->cursorY / max(1, $this->windowHeight)) * 2.0 - 1.0,
        );
    }

    public function getLastCursorPosition(): Vec2
    {
        return new Vec2($this->lastCursorX, $this->lastCursorY);
    }

    public function setCursorPosition(Vec2 $position): void
    {
        $this->simulateCursorPos($position->x, $position->y);
    }

    public function setCursorMode(CursorMode $mode): void
    {
        $this->cursorMode = $mode;
    }

    public function getCursorMode(): CursorMode
    {
        return $this->cursorMode;
    }

    public function isContextUnclaimed(): bool
    {
        return $this->inputContext === null;
    }

    public function claimContext(string $context): void
    {
        $this->inputContext = $context;
    }

    public function releaseContext(string $context): void
    {
        if ($this->inputContext === $context) {
            $this->inputContext = null;
        }
    }

    public function getCurrentContext(): ?string
    {
        return $this->inputContext;
    }

    public function isClaimedContext(string $context): bool
    {
        return $this->inputContext === $context;
    }
}
