<?php

namespace VISU\OS;

use GL\Math\Vec2;

interface InputInterface
{
    /**
     * Get the state for a given key (PRESS, RELEASE, REPEAT).
     */
    public function getKeyState(int $key): int;

    /**
     * Returns true if the given key is currently pressed.
     */
    public function isKeyPressed(int $key): bool;

    /**
     * Returns true if the given key is currently released.
     */
    public function isKeyReleased(int $key): bool;

    /**
     * Returns true if the given key is being repeated.
     */
    public function isKeyRepeated(int $key): bool;

    /**
     * Get the state for a given mouse button (PRESS, RELEASE).
     */
    public function getMouseButtonState(int $button): int;

    /**
     * Returns true if the given mouse button is currently pressed.
     */
    public function isMouseButtonPressed(int $button): bool;

    /**
     * Returns true if the given mouse button is currently released.
     */
    public function isMouseButtonReleased(int $button): bool;

    /**
     * Returns true if the given mouse button was pressed since the last poll.
     */
    public function hasMouseButtonBeenPressed(int $button): bool;

    /**
     * Returns true if the given mouse button was released since the last poll.
     */
    public function hasMouseButtonBeenReleased(int $button): bool;

    /**
     * Returns true if the given mouse button was pressed this frame.
     */
    public function hasMouseButtonBeenPressedThisFrame(int $button): bool;

    /**
     * Returns true if the given mouse button was released this frame.
     */
    public function hasMouseButtonBeenReleasedThisFrame(int $button): bool;

    /**
     * Returns true if the given key was pressed since the last poll.
     */
    public function hasKeyBeenPressed(int $key): bool;

    /**
     * Returns true if the given key was released since the last poll.
     */
    public function hasKeyBeenReleased(int $key): bool;

    /**
     * Returns true if the given key was pressed this frame.
     */
    public function hasKeyBeenPressedThisFrame(int $key): bool;

    /**
     * Returns true if the given key was released this frame.
     */
    public function hasKeyBeenReleasedThisFrame(int $key): bool;

    /**
     * Returns key codes of all keys pressed since last poll.
     *
     * @return array<int>
     */
    public function getKeyPresses(): array;

    /**
     * Returns key codes of all keys pressed this frame.
     *
     * @return array<int>
     */
    public function getKeyPressesThisFrame(): array;

    /**
     * Get the current cursor position.
     */
    public function getCursorPosition(): Vec2;

    /**
     * Returns the normalized cursor position (-1.0 to 1.0).
     */
    public function getNormalizedCursorPosition(): Vec2;

    /**
     * Get the last received cursor position.
     */
    public function getLastCursorPosition(): Vec2;

    /**
     * Set the cursor position.
     */
    public function setCursorPosition(Vec2 $position): void;

    /**
     * Set the cursor mode (NORMAL, HIDDEN, DISABLED).
     */
    public function setCursorMode(CursorMode $mode): void;

    /**
     * Get the current cursor mode.
     */
    public function getCursorMode(): CursorMode;

    /**
     * Is the input context currently unclaimed?
     */
    public function isContextUnclaimed(): bool;

    /**
     * Claim the input context.
     */
    public function claimContext(string $context): void;

    /**
     * Release the input context.
     */
    public function releaseContext(string $context): void;

    /**
     * Get the current input context.
     */
    public function getCurrentContext(): ?string;

    /**
     * Returns true if the given input context is currently claimed.
     */
    public function isClaimedContext(string $context): bool;
}
