<?php

namespace VISU\Quickstart\Render;

use ClanCats\Container\Container;

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\{PipelineResources, RenderPipeline};
use VISU\Graphics\Rendering\Renderer\DebugOverlayText;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Renderer\DebugOverlayTextRenderer;
use VISU\OS\{Input, Key};
use VISU\Runtime\GameLoop;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

class QuickstartDebugMetricsOverlay
{
    /**
     * Rows to be rendered on the next frame
     * 
     * @var array<string
     */
    static private $globalRows = [];

    /**
     * Adds a string to the global quickstart debug overlay, This allows you 
     * to quickly visualize debug information. Only intended for quick debugging.
     */
    static public function debugString(string $row) : void
    {
        self::$globalRows[] = $row;
    }

    /**
     * An array of string to be renderd on the next frame
     */
    private array $rows = [];

    /**
     * Should the debug overlay be rendered?
     */
    public bool $enabled = true;

    private DebugOverlayTextRenderer $overlayTextRenderer;

    /**
     * Constructor
     * 
     * As this is a debugging utility, we will use the container directly
     */
    public function __construct(
        private Container $container
    ) {
        $this->overlayTextRenderer = new DebugOverlayTextRenderer(
            $container->getTyped(GLState::class, 'gl'),
            DebugOverlayTextRenderer::loadDebugFontAtlas(),
        );

        // listen to keyboard events to toggle debug overlay
        $container->getTyped(Dispatcher::class, 'dispatcher')->register('input.key', function(KeySignal $keySignal) {
            if ($keySignal->key == Key::F1 && $keySignal->action == Input::PRESS) {
                $this->enabled = !$this->enabled;
            }
        });
    }

    private function formatNStoHuman(int $ns) : string
    {
        if ($ns < 1000) {
            return sprintf("%.2f ns", $ns);
        }
        elseif ($ns < 1000000) {
            return sprintf("%.2f µs", $ns / 1000);
        }
        elseif ($ns < 1000000000) {
            return sprintf("%.2f ms", $ns / 1000000);
        }
        else {
            return sprintf("%.2f s", $ns / 1000000000);
        }
    }

    /**
     * Generates the basic game loop metrics string 
     * 
     * Example: FPS: 60 | TC: 0.016 | UT: 0.000000 | FT: 0.000000 | delta: 0.0160
     * 
     * Explanation:
     *   FPS: Frames per second
     *   TC:  Tick count, the amount of ticks that have been executed per frame
     *   UT:  Update time, the time it took to execute the update loop
     *   FT:  Frame time, the time it took to execute the draw loop
     */
    private function gameLoopMetrics(float $deltaTime) : string
    {
        $gameLoop = $this->container->getTyped(GameLoop::class, 'loop');

        $row =  str_pad("FPS: " . round($gameLoop->getAverageFps()), 8);
        $row .= str_pad(" | TC: " . sprintf("%.2f", $gameLoop->getAverageTickCount()), 10);
        $row .= str_pad(" | UT: " . $gameLoop->getAverageTickTimeFormatted(), 18);
        $row .= str_pad(" | FT: " . $gameLoop->getAverageFrameTimeFormatted(), 16);
        $row .= " | delta: " . sprintf("%.4f", $deltaTime);
    
        return $row;
    }

    /**
     * Attaches a render pass to the given pipeline that renders the debug overlay
     */
    public function attachPass(RenderPipeline $pipeline, PipelineResources $resources, RenderTargetResource $rt, float $compensation)
    {
        // we sync the profile enabled state with the debug overlay
        // $this->container->resolveProfiler()->enabled = $this->enabled;
        
        // if the debug overlay is disabled we do not render anything
        // but we still have to clear the row data in case other parts of the 
        // application continue to fill it.
        if (!$this->enabled) {
            $this->rows = [];
            self::$globalRows = [];
            return;
        }

        // get the actual rendering target
        $target = $resources->getRenderTarget($rt);

        // Add current FPS plus the average tick count and the compensation
        $this->rows[] = $this->gameLoopMetrics($compensation);

        // add global rows
        $this->rows = array_merge($this->rows, self::$globalRows);
        
        // we render to the backbuffer
        $this->overlayTextRenderer->attachPass($pipeline, $rt, [
            new DebugOverlayText(implode("\n", $this->rows), 10, 10)
        ]);

        // $profilerLines =  $this->gameProfilerMetrics();
        // $y = $rt->height - (count($profilerLines) * $this->debugTextRenderer->lineHeight * $target->contentScaleX);
        // $y -= 25;
        // $this->debugTextRenderer->attachPass($pipeline, $rt, [
        //     new DebugOverlayText(implode("\n", $profilerLines), 10, $y, new Vec3(0.726, 0.865, 1.0)),
        // ]);


        // clear the rows for the next frame
        $this->rows = [];
        self::$globalRows = [];
    }

    /**
     * Because we are holding a reference to the container, we specify what to return on debug info
     * To prevent irrelevant information from being dumped.
     */
    public function __debugInfo()
    {
        return [
            'enabled' => $this->enabled,
            'rows' => $this->rows,
        ];
    }
}