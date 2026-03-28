<?php

namespace VISU\Tests\Testing;

use GL\Math\Vec2;
use PHPUnit\Framework\TestCase;
use VISU\OS\CursorMode;
use VISU\OS\Key;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Testing\FakeInput;

/**
 * Unit tests for FakeInput — no GLFW window or GL context required.
 */
class FakeInputTest extends TestCase
{
    private FakeInput $input;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new Dispatcher();
        $this->input      = new FakeInput($this->dispatcher, 1280, 720);
    }

    // ── Cursor ───────────────────────────────────────────────────────────

    public function testInitialCursorPositionIsOrigin(): void
    {
        $pos = $this->input->getCursorPosition();
        $this->assertSame(0.0, $pos->x);
        $this->assertSame(0.0, $pos->y);
    }

    public function testSimulateCursorPosUpdatesCursorPosition(): void
    {
        $this->input->simulateCursorPos(320.0, 240.0);

        $pos = $this->input->getCursorPosition();
        $this->assertSame(320.0, $pos->x);
        $this->assertSame(240.0, $pos->y);
    }

    public function testGetLastCursorPositionReturnsPositionBeforeLastMove(): void
    {
        $this->input->simulateCursorPos(100.0, 50.0);
        $this->input->simulateCursorPos(200.0, 100.0);

        $this->assertSame(100.0, $this->input->getLastCursorPosition()->x);
        $this->assertSame(50.0,  $this->input->getLastCursorPosition()->y);
    }

    public function testNormalizedCursorPositionCenterIsZero(): void
    {
        $this->input->simulateCursorPos(640.0, 360.0); // exact center of 1280×720
        $norm = $this->input->getNormalizedCursorPosition();

        $this->assertEqualsWithDelta(0.0, $norm->x, 0.01);
        $this->assertEqualsWithDelta(0.0, $norm->y, 0.01);
    }

    // ── Mouse buttons ─────────────────────────────────────────────────────

    public function testMouseButtonInitiallyReleased(): void
    {
        $this->assertFalse($this->input->isMouseButtonPressed(MouseButton::LEFT));
        $this->assertTrue($this->input->isMouseButtonReleased(MouseButton::LEFT));
    }

    public function testSimulateMouseButtonPressRegistersState(): void
    {
        $this->input->simulateMouseButton(MouseButton::LEFT, true);

        $this->assertTrue($this->input->isMouseButtonPressed(MouseButton::LEFT));
        $this->assertFalse($this->input->isMouseButtonReleased(MouseButton::LEFT));
        $this->assertTrue($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT));
        $this->assertTrue($this->input->hasMouseButtonBeenPressedThisFrame(MouseButton::LEFT));
    }

    public function testSimulateMouseButtonReleaseRegistersState(): void
    {
        $this->input->simulateMouseButton(MouseButton::LEFT, true);
        $this->input->simulateMouseButton(MouseButton::LEFT, false);

        $this->assertFalse($this->input->isMouseButtonPressed(MouseButton::LEFT));
        $this->assertTrue($this->input->hasMouseButtonBeenReleased(MouseButton::LEFT));
        $this->assertTrue($this->input->hasMouseButtonBeenReleasedThisFrame(MouseButton::LEFT));
    }

    public function testEndFrameClearsPerFrameMouseState(): void
    {
        $this->input->simulateMouseButton(MouseButton::LEFT, true);
        $this->input->endFrame();

        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(MouseButton::LEFT));
        $this->assertFalse($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT));
        // Button is still held down (state persists across frames)
        $this->assertTrue($this->input->isMouseButtonPressed(MouseButton::LEFT));
    }

    public function testSimulateClickPressesAndReleasesButton(): void
    {
        $this->input->simulateClick(MouseButton::LEFT);

        $this->assertTrue($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT));
        $this->assertTrue($this->input->hasMouseButtonBeenReleased(MouseButton::LEFT));
        $this->assertFalse($this->input->isMouseButtonPressed(MouseButton::LEFT));
    }

    // ── Keys ─────────────────────────────────────────────────────────────

    public function testKeyInitiallyReleased(): void
    {
        $this->assertFalse($this->input->isKeyPressed(Key::ESCAPE));
        $this->assertTrue($this->input->isKeyReleased(Key::ESCAPE));
    }

    public function testSimulateKeyPressRegistersState(): void
    {
        $this->input->simulateKeyPress(Key::ESCAPE);

        $this->assertTrue($this->input->isKeyPressed(Key::ESCAPE));
        $this->assertTrue($this->input->hasKeyBeenPressed(Key::ESCAPE));
        $this->assertTrue($this->input->hasKeyBeenPressedThisFrame(Key::ESCAPE));
        $this->assertContains(Key::ESCAPE, $this->input->getKeyPresses());
        $this->assertContains(Key::ESCAPE, $this->input->getKeyPressesThisFrame());
    }

    public function testSimulateKeyReleaseRegistersState(): void
    {
        $this->input->simulateKeyPress(Key::ESCAPE);
        $this->input->simulateKeyRelease(Key::ESCAPE);

        $this->assertFalse($this->input->isKeyPressed(Key::ESCAPE));
        $this->assertTrue($this->input->hasKeyBeenReleased(Key::ESCAPE));
        $this->assertTrue($this->input->hasKeyBeenReleasedThisFrame(Key::ESCAPE));
    }

    public function testEndFrameClearsPerFrameKeyState(): void
    {
        $this->input->simulateKeyPress(Key::ESCAPE);
        $this->input->endFrame();

        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(Key::ESCAPE));
        $this->assertFalse($this->input->hasKeyBeenPressed(Key::ESCAPE));
        $this->assertEmpty($this->input->getKeyPressesThisFrame());
    }

    // ── Input context ─────────────────────────────────────────────────────

    public function testContextInitiallyUnclaimed(): void
    {
        $this->assertTrue($this->input->isContextUnclaimed());
        $this->assertNull($this->input->getCurrentContext());
    }

    public function testClaimAndReleaseContext(): void
    {
        $this->input->claimContext('ui');

        $this->assertFalse($this->input->isContextUnclaimed());
        $this->assertTrue($this->input->isClaimedContext('ui'));
        $this->assertSame('ui', $this->input->getCurrentContext());

        $this->input->releaseContext('ui');

        $this->assertTrue($this->input->isContextUnclaimed());
    }

    public function testReleaseWrongContextDoesNothing(): void
    {
        $this->input->claimContext('ui');
        $this->input->releaseContext('game'); // wrong context

        $this->assertFalse($this->input->isContextUnclaimed());
        $this->assertSame('ui', $this->input->getCurrentContext());
    }

    // ── Cursor mode ───────────────────────────────────────────────────────

    public function testCursorModeDefaultsToNormal(): void
    {
        $this->assertSame(CursorMode::NORMAL, $this->input->getCursorMode());
    }

    public function testSetCursorModeUpdatesMode(): void
    {
        $this->input->setCursorMode(CursorMode::HIDDEN);
        $this->assertSame(CursorMode::HIDDEN, $this->input->getCursorMode());
    }

}
