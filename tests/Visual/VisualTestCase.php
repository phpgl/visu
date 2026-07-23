<?php

namespace VISU\Tests\Visual;

use ClanCats\Container\Container;
use Closure;
use GL\Texture\Texture2D;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\Runtime\GameLoop;
use VISU\Testing\ImageCapture;
use VISU\Testing\ImageComparator;
use VISU\Tests\GLContextTestCase;

/**
 * Base class for visual regression tests.
 *
 * A visual test renders a scene to an offscreen buffer, captures it to a PNG and compares it
 * against a committed "expected" baseline image with a pixel-tolerance + dual-threshold metric.
 * Rendering reuses a shared headless QuickstartApp, which gives us the vector graphics context,
 * shader collection and FlyUI plumbing for free.
 *
 * Baselines live in tests/resources/visual/expected and are committed. On failure the actual
 * capture and an amplified grayscale diff are written to tests_env/var/visual (gitignored).
 *
 * Baselines are (re)generated when missing, or for every case when the VISU_VISUAL_UPDATE
 * environment variable is set ("bless" mode).
 *
 * @group glfwinit
 * @group visual
 */
abstract class VisualTestCase extends GLContextTestCase
{
    /**
     * The fixed offscreen resolution all visual tests render at (logical points).
     * Kept small and 4-byte aligned for deterministic, cheap captures. Note the actual captured
     * pixel resolution is this multiplied by the window content scale (e.g. 2x on retina displays).
     */
    protected const VISUAL_WIDTH = 240;
    protected const VISUAL_HEIGHT = 160;

    /**
     * The name of the offscreen color attachment produced by QuickstartApp::render.
     * (render target "quickstartTarget" + color attachment "quickstartColor")
     */
    private const CAPTURE_TEXTURE = 'quickstartTarget.attachment.color_quickstartColor';

    /**
     * The shared headless app used to render frames, booted lazily and reused across tests.
     */
    protected static ?QuickstartApp $app = null;

    public function setUp() : void
    {
        parent::setUp();
        $this->bootApp();
    }

    /**
     * Boots the shared headless QuickstartApp once
     */
    protected function bootApp() : void
    {
        if (self::$app !== null) {
            return;
        }

        // QuickstartApp resolves shaders from VISU_PATH_RESOURCES_SHADER, which the app bootstrap
        // normally defines but the test bootstrap (phpunit.php) does not. Point it at the real
        // framework shaders so the full render path is exercised.
        if (!defined('VISU_PATH_RESOURCES_SHADER')) {
            define('VISU_PATH_RESOURCES_SHADER', VISU_PATH_FRAMEWORK_RESOURCES_SHADER);
        }

        $options = new QuickstartOptions();
        $options->windowHeadless = true;
        $options->windowVsync = false;
        $options->windowTitle = 'VISU Visual Test';
        $options->windowWidth = static::VISUAL_WIDTH;
        $options->windowHeight = static::VISUAL_HEIGHT;

        $app = new QuickstartApp(new Container(), $options);
        $app->ready();

        // the quickstart debug overlay pass reads a "loop" service for its metrics; register one so
        // a single headless render can complete. the overlay draws to the backbuffer, never to the
        // offscreen target we capture, so it does not affect the compared image.
        $app->container->set('loop', new GameLoop($app, $options->gameLoopTickRate, $options->gameLoopMaxUpdatesPerFrame));

        self::$app = $app;
    }

    /**
     * Renders a single frame using the given draw callback and returns the captured image
     *
     * The callback receives the same arguments as a QuickstartApp draw callback and should issue
     * its draw calls (FlyUI / vector graphics / GL). FlyUI and vector graphics frames are already
     * managed by the app around this callback.
     *
     * @param Closure(QuickstartApp, \VISU\Graphics\Rendering\RenderContext, \VISU\Graphics\RenderTarget): void $draw
     */
    protected function renderFrame(Closure $draw) : Texture2D
    {
        $app = self::$app;
        if ($app === null) {
            throw new \RuntimeException('Visual test app has not been booted');
        }

        $app->options->draw = $draw;
        $app->render(0.0);

        $texture = $app->renderResources->findTextureByName(self::CAPTURE_TEXTURE);
        if ($texture === null) {
            throw new \RuntimeException('Could not find the offscreen capture texture "' . self::CAPTURE_TEXTURE . '"');
        }

        return ImageCapture::fromColorTexture($texture);
    }

    /**
     * Asserts that the given capture matches the committed baseline for $name
     *
     * When no baseline exists yet, or when running in bless mode (VISU_VISUAL_UPDATE), the baseline
     * is (re)generated and the test is marked skipped. Otherwise the capture is compared against the
     * baseline; on mismatch the actual + diff images are written to the artifacts directory and the
     * test fails with the divergence metrics.
     */
    protected function assertImageMatchesBaseline(Texture2D $actual, string $name, ?ImageComparator $comparator = null) : void
    {
        $comparator ??= new ImageComparator();

        $baselinePath = $this->baselineDir() . DIRECTORY_SEPARATOR . $name . '.png';
        $actualPath = $this->artifactDir() . DIRECTORY_SEPARATOR . $name . '.actual.png';
        $diffPath = $this->artifactDir() . DIRECTORY_SEPARATOR . $name . '.diff.png';

        // (re)generate the baseline when blessing or when it is missing
        if ($this->isBlessMode() || !is_file($baselinePath)) {
            $actual->writePNG($baselinePath);
            $this->markTestSkipped("baseline (re)generated for {$name}");
        }

        // always persist the actual first, then compare disk-to-disk so both images go through the
        // identical Texture2D::fromDisk flip handling (avoids glReadPixels vs PNG orientation drift)
        $actual->writePNG($actualPath);

        $expectedTexture = Texture2D::fromDisk($baselinePath);
        $actualTexture = Texture2D::fromDisk($actualPath);

        $comparison = $comparator->compare($expectedTexture, $actualTexture);

        if (!$comparison->passes($comparator->maxBadPixelRatio, $comparator->maxMeanError)) {
            $comparator->writeDiffImage($expectedTexture, $actualTexture, $diffPath);
            $this->fail(sprintf(
                "Visual mismatch for \"%s\": %s\n  expected: %s\n  actual:   %s\n  diff:     %s",
                $name, $comparison->summary(), $baselinePath, $actualPath, $diffPath
            ));
        }

        // clean up the actual artifact on success to keep the artifacts dir tidy
        @unlink($actualPath);

        $this->assertTrue(true);
    }

    /**
     * Returns true if baselines should be (re)generated instead of compared
     */
    protected function isBlessMode() : bool
    {
        $value = getenv('VISU_VISUAL_UPDATE');
        return $value !== false && $value !== '' && $value !== '0';
    }

    /**
     * Directory holding the committed baseline images
     */
    protected function baselineDir() : string
    {
        $dir = PATH_TEST_RESOURCES . DIRECTORY_SEPARATOR . 'visual' . DIRECTORY_SEPARATOR . 'expected';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Writable directory for failure artifacts (actual + diff images)
     */
    protected function artifactDir() : string
    {
        $dir = VISU_PATH_ROOT . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'visual';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }
}
