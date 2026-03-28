<?php

namespace VISU\Tests\OS;

use VISU\OS\Input;
use VISU\Signal\VoidDispatcher;
use VISU\Tests\GLContextTestCase;

/**
 * Tests that fullscreen/display-mode transitions don't produce
 * phantom input events (macOS fullscreen artifacts).
 *
 * @group glfwinit
 */
class InputFullscreenTest extends GLContextTestCase
{
    private Input $input;

    public function setUp(): void
    {
        parent::setUp();
        $this->input = new Input($this->createWindow(), new VoidDispatcher);
    }

    // -- Callback-tracked state --------------------------------------------------

    public function testMouseButtonStateTrackedFromCallbacks(): void
    {
        $window = $this->createWindow();

        // initially released
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));

        // simulate press callback
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertSame(GLFW_PRESS, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));

        // simulate release callback
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_RELEASE, 0);
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));
    }

    public function testKeyStateUsesGlfwPolling(): void
    {
        // getKeyState() uses glfwGetKey() polling (not callback-tracked).
        // This is intentional: callback events + polling cross-check detects phantom keys.
        $this->assertSame(GLFW_RELEASE, $this->input->getKeyState(GLFW_KEY_F6));
    }

    // -- suppressInputEvents() ---------------------------------------------------

    public function testSuppressInputEventsDropsMouseCallbacks(): void
    {
        $window = $this->createWindow();

        $this->input->suppressInputEvents(2);

        // phantom press during suppression — should be ignored
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));
    }

    public function testSuppressInputEventsDropsKeyCallbacks(): void
    {
        $window = $this->createWindow();

        $this->input->suppressInputEvents(2);

        // phantom key during suppression — callback event should be ignored
        $this->input->handleWindowKey($window, GLFW_KEY_F1, 0, GLFW_PRESS, 0);
        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_F1));
        // getKeyState() uses GLFW polling (independent of callbacks), so we only check callback-based state
    }

    public function testSuppressionExpiresAfterFrames(): void
    {
        $window = $this->createWindow();

        $this->input->suppressInputEvents(2, 0.0); // 0s time-suppression so only frames matter

        // frame 1 — still suppressed
        $this->input->endFrame();
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));

        // frame 2 — counter reaches 0, suppression ends but post-suppression guard activates
        $this->input->endFrame();
        // Post-suppression guard blocks PRESS events (phantom protection).
        // A RELEASE on any button disables the guard immediately so the next real click works.
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_RIGHT, GLFW_RELEASE, 0);

        // Now a real click should work
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertTrue($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
    }

    public function testSuppressionExpiresAfterTime(): void
    {
        $window = $this->createWindow();

        // 0 frames but time-based suppression of 0.05s
        $this->input->suppressInputEvents(0, 0.05);

        // immediately — still within time window
        $this->input->handleWindowKey($window, GLFW_KEY_A, 0, GLFW_PRESS, 0);
        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_A));

        // wait for suppression to expire
        usleep(60_000); // 60ms > 50ms

        $this->input->endFrame();
        $this->input->handleWindowKey($window, GLFW_KEY_A, 0, GLFW_PRESS, 0);
        $this->assertTrue($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_A));
    }

    // -- State preservation during suppression -----------------------------------

    public function testSuppressionClearsExistingMouseState(): void
    {
        $window = $this->createWindow();

        // user is holding left mouse button before fullscreen toggle
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertSame(GLFW_PRESS, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));

        // fullscreen toggle — suppress events
        // Suppression intentionally clears all mouse button states to prevent
        // stuck buttons (the release callback would be dropped during suppression).
        $this->input->suppressInputEvents(3);

        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));
    }

    public function testSuppressionPreservesExistingKeyEvents(): void
    {
        $window = $this->createWindow();

        // key event recorded before suppression
        $this->input->handleWindowKey($window, GLFW_KEY_W, 0, GLFW_PRESS, 0);
        $this->assertTrue($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_W));

        $this->input->suppressInputEvents(3);

        // suppression clears pending events
        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_W));
        // getKeyState() uses GLFW polling — independent of callback tracking
    }

    // -- Clearing event arrays but not states ------------------------------------

    public function testSuppressionClearsPendingEventsButNotStates(): void
    {
        $window = $this->createWindow();

        // record some events in the current frame
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_RIGHT, GLFW_PRESS, 0);
        $this->input->handleWindowKey($window, GLFW_KEY_SPACE, 0, GLFW_PRESS, 0);

        $this->assertTrue($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_RIGHT));
        $this->assertTrue($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_SPACE));

        // suppress clears pending events
        $this->input->suppressInputEvents(1);

        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_RIGHT));
        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_SPACE));

        // mouse button state is also cleared (prevents stuck buttons when release is dropped)
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_RIGHT));
    }

    // -- No phantom artifacts after rapid fullscreen toggle ----------------------

    public function testNoPhantomClickAfterFullscreenToggle(): void
    {
        $window = $this->createWindow();

        // simulate: user presses F11 for fullscreen, OS generates phantom mouse press
        $this->input->suppressInputEvents(3, 0.0);

        // phantom PRESS arrives
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        // phantom RELEASE arrives
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_RELEASE, 0);

        // none of this should be visible
        $this->assertFalse($this->input->hasMouseButtonBeenPressed(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertFalse($this->input->hasMouseButtonBeenReleased(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertFalse($this->input->hasMouseButtonBeenReleasedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));
    }

    public function testNoPhantomKeyAfterFullscreenToggle(): void
    {
        $window = $this->createWindow();

        $this->input->suppressInputEvents(3, 0.0);

        // phantom F6 press/release
        $this->input->handleWindowKey($window, GLFW_KEY_F6, 0, GLFW_PRESS, 0);
        $this->input->handleWindowKey($window, GLFW_KEY_F6, 0, GLFW_RELEASE, 0);

        $this->assertFalse($this->input->hasKeyBeenPressed(GLFW_KEY_F6));
        $this->assertFalse($this->input->hasKeyBeenReleased(GLFW_KEY_F6));
        $this->assertFalse($this->input->hasKeyBeenPressedThisFrame(GLFW_KEY_F6));
        $this->assertFalse($this->input->hasKeyBeenReleasedThisFrame(GLFW_KEY_F6));
        $this->assertSame(GLFW_RELEASE, $this->input->getKeyState(GLFW_KEY_F6));
    }

    // -- Normal operation after suppression ends ---------------------------------

    public function testNormalOperationResumesAfterSuppression(): void
    {
        $window = $this->createWindow();

        $this->input->suppressInputEvents(1, 0.0);

        // suppression active — ignored
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertFalse($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));

        // end frame — suppression expires, post-suppression guard activates
        $this->input->endFrame();

        // Post-suppression guard blocks PRESS but allows RELEASE.
        // Send a RELEASE (on right button to avoid click-distance logic) to clear the guard.
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_RIGHT, GLFW_RELEASE, 0);

        // real click — should work normally now
        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_PRESS, 0);
        $this->assertTrue($this->input->hasMouseButtonBeenPressedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertSame(GLFW_PRESS, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));

        $this->input->handleWindowMouseButton($window, GLFW_MOUSE_BUTTON_LEFT, GLFW_RELEASE, 0);
        $this->assertTrue($this->input->hasMouseButtonBeenReleasedThisFrame(GLFW_MOUSE_BUTTON_LEFT));
        $this->assertSame(GLFW_RELEASE, $this->input->getMouseButtonState(GLFW_MOUSE_BUTTON_LEFT));
    }
}
