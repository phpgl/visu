<?php

namespace VISU\Testing;

use GL\Buffer\UByteBuffer;
use GL\Math\Vec2;
use GL\VectorGraphics\VGContext;
use VISU\FlyUI\FlyUI;
use VISU\Graphics\GLState;
use VISU\OS\Input;
use VISU\OS\Window;
use VISU\OS\WindowHints;
use VISU\Signal\Dispatcher;

abstract class VisualTestCase extends \PHPUnit\Framework\TestCase
{
    protected static bool $glfwInitialized = false;
    protected static GLState $glstate;
    protected static ?Window $window = null;
    protected static ?VGContext $vgContext = null;
    protected static ?Input $input = null;
    protected static ?Dispatcher $dispatcher = null;

    protected int $viewportWidth = 800;
    protected int $viewportHeight = 600;

    /**
     * Directory where reference snapshots are stored.
     * Override in subclass or set via snapshotDirectory().
     */
    protected ?string $snapshotDir = null;

    /**
     * Default threshold for snapshot comparison (percentage).
     */
    protected float $snapshotThreshold = 0.1;

    public function setUp(): void
    {
        parent::setUp();

        if (!self::$glfwInitialized) {
            if (!glfwInit()) {
                throw new \RuntimeException('Could not initialize GLFW.');
            }
            self::$glfwInitialized = true;
            self::$glstate = new GLState();
        }

        if (self::$window === null) {
            $hints = new WindowHints();
            $hints->visible = false;

            self::$window = new Window('VRT Offscreen', $this->viewportWidth, $this->viewportHeight, $hints);
            self::$window->initailize(self::$glstate);

            self::$vgContext = new VGContext(VGContext::ANTIALIAS);
            self::$dispatcher = new Dispatcher();
            self::$input = new Input(self::$window, self::$dispatcher);

            FlyUI::initailize(self::$vgContext, self::$dispatcher, self::$input);
        }
    }

    /**
     * Render a frame by calling the given draw callback, then read back the framebuffer as PNG.
     *
     * @param callable $drawCallback Called between beginFrame/endFrame. Receives VGContext as argument.
     * @return string Raw PNG binary
     */
    protected function renderFrame(callable $drawCallback): string
    {
        $w = $this->viewportWidth;
        $h = $this->viewportHeight;

        glViewport(0, 0, $w, $h);
        glClearColor(0.0, 0.0, 0.0, 1.0);
        glClear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);

        $resolution = new Vec2((float)$w, (float)$h);

        self::$vgContext->beginFrame($w, $h, 1.0);
        FlyUI::beginFrame($resolution);

        $drawCallback(self::$vgContext);

        FlyUI::endFrame();
        self::$vgContext->endFrame();

        glFinish();

        return $this->readFramebufferAsPng($w, $h);
    }

    /**
     * Read the current framebuffer pixels and encode as PNG.
     */
    private function readFramebufferAsPng(int $w, int $h): string
    {
        $buffer = new UByteBuffer();
        glReadPixels(0, 0, $w, $h, GL_RGBA, GL_UNSIGNED_BYTE, $buffer);

        $img = imagecreatetruecolor($w, $h);
        if ($img === false) {
            throw new \RuntimeException('Failed to create image for framebuffer readback.');
        }

        // OpenGL reads bottom-to-top, so we flip vertically
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $srcIdx = (($h - 1 - $y) * $w + $x) * 4;
                $r = $buffer[$srcIdx];
                $g = $buffer[$srcIdx + 1];
                $b = $buffer[$srcIdx + 2];
                $color = imagecolorallocate($img, $r, $g, $b) ?: 0;
                imagesetpixel($img, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png ?: '';
    }

    /**
     * Assert that the rendered frame matches the stored reference snapshot.
     *
     * When the UPDATE_SNAPSHOTS environment variable is set, the reference is overwritten
     * instead of compared, allowing easy golden file updates.
     */
    protected function assertMatchesSnapshot(string $actualPng, string $snapshotName, ?float $threshold = null): SnapshotResult
    {
        $threshold ??= $this->snapshotThreshold;
        $dir = $this->resolveSnapshotDirectory();
        $snapshotsDir = $dir . DIRECTORY_SEPARATOR . 'Snapshots';
        $diffsDir = $dir . DIRECTORY_SEPARATOR . 'Diffs';

        $referencePath = $snapshotsDir . DIRECTORY_SEPARATOR . $snapshotName . '.png';
        $actualPath = $diffsDir . DIRECTORY_SEPARATOR . $snapshotName . '_actual.png';
        $diffPath = $diffsDir . DIRECTORY_SEPARATOR . $snapshotName . '_diff.png';

        // Update mode: overwrite reference and pass
        if (getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir($snapshotsDir)) {
                mkdir($snapshotsDir, 0755, true);
            }
            file_put_contents($referencePath, $actualPng);

            return new SnapshotResult(
                passed: true,
                diffPercent: 0.0,
                threshold: $threshold,
                snapshotName: $snapshotName,
                referencePath: $referencePath,
                actualPath: null,
                diffPath: null,
                isNew: true,
            );
        }

        // No reference yet — fail with instructions
        if (!file_exists($referencePath)) {
            if (!is_dir($snapshotsDir)) {
                mkdir($snapshotsDir, 0755, true);
            }
            file_put_contents($referencePath, $actualPng);

            $this->fail(
                "No reference snapshot found for '{$snapshotName}'. "
                . "A new reference has been created at: {$referencePath}. "
                . "Re-run the test to compare against it."
            );
        }

        $referencePng = file_get_contents($referencePath);
        if ($referencePng === false) {
            $this->fail("Failed to read reference snapshot: {$referencePath}");
        }
        $diffPercent = SnapshotComparator::compare($actualPng, $referencePng);
        $passed = $diffPercent <= $threshold;

        if (!$passed) {
            if (!is_dir($diffsDir)) {
                mkdir($diffsDir, 0755, true);
            }
            file_put_contents($actualPath, $actualPng);
            file_put_contents($diffPath, SnapshotComparator::generateDiffImage($actualPng, $referencePng));
        }

        $result = new SnapshotResult(
            passed: $passed,
            diffPercent: $diffPercent,
            threshold: $threshold,
            snapshotName: $snapshotName,
            referencePath: $referencePath,
            actualPath: $passed ? null : $actualPath,
            diffPath: $passed ? null : $diffPath,
            isNew: false,
        );

        $this->assertTrue(
            $passed,
            sprintf(
                "Snapshot '%s' differs by %.4f%% (threshold: %.4f%%). Diff saved to: %s",
                $snapshotName,
                $diffPercent,
                $threshold,
                $diffPath
            )
        );

        return $result;
    }

    /**
     * Resolve the snapshot directory. Defaults to a Snapshots/ folder next to the test file.
     */
    private function resolveSnapshotDirectory(): string
    {
        if ($this->snapshotDir !== null) {
            return $this->snapshotDir;
        }

        $reflection = new \ReflectionClass($this);
        $fileName = $reflection->getFileName();
        if ($fileName === false) {
            throw new \RuntimeException('Cannot resolve snapshot directory for internal class.');
        }
        return dirname($fileName);
    }
}
