<?php

namespace VISU\Tests\Visual;

use GL\VectorGraphics\VGColor;
use VISU\FlyUI\FlyUI;
use VISU\FlyUI\FUILayoutFlow;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\Quickstart\QuickstartApp;

/**
 * Example visual regression test.
 *
 * Renders a small FlyUI card with a label and a button to the offscreen target and compares it
 * against a committed baseline. The first run (or a run with VISU_VISUAL_UPDATE=1) generates the
 * baseline and skips; subsequent runs compare against it.
 *
 * Note: baselines are GPU/driver specific. If a machine renders with different antialiasing the
 * default tolerance may need raising via a custom ImageComparator.
 *
 * @group glfwinit
 * @group visual
 */
class FlyUIVisualTest extends VisualTestCase
{
    public function testFlyUICardRendersConsistently() : void
    {
        $image = $this->renderFrame(function (QuickstartApp $app, RenderContext $context, RenderTarget $target) : void {
            // center a fixed size card in the viewport so the layout is deterministic
            FlyUI::beginLayout()
                ->verticalFill()
                ->horizontalFill()
                ->flow(FUILayoutFlow::vertical)
                ->alignCenter();

            FlyUI::beginCardView()
                ->fixedWidth(180.0)
                ->paddingAll(14.0)
                ->spacing(8.0);

            FlyUI::text('Visual Test', new VGColor(0.1, 0.1, 0.12, 1.0));
            FlyUI::button('Click me', function () : void {
                // no-op, the button is only rendered
            });

            FlyUI::end(); // card
            FlyUI::end(); // centering layout
        });

        $this->assertImageMatchesBaseline($image, 'flyui_card');
    }
}
