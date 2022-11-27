<?php

require __DIR__ . '/vendor/autoload.php';

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Input;
use VISU\OS\Window;
use VISU\Runtime\GameLoop;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

glfwInit();

$gl = new GLState;
$window = new Window('Test Window', 640, 480);
$window->initailize($gl);
$dispatcher = new Dispatcher;
$input = new Input($window, $dispatcher);

$dispatcher->register('input.key', function (KeySignal $signal) {
    var_dump($signal);
});

class Game implements VISU\Runtime\GameLoopDelegate
{
    private GameLoop $loop;
    private PipelineResources $renderResources; 

    private int $tick = 0;

    public function __construct(
        private Window $window,
        private GLState $gl,
    )
    {
        $this->loop = new GameLoop($this);
        $this->renderResources = new PipelineResources($gl);
    }

    public function update(): void
    {
        $this->window->pollEvents();
    }

    public function render(float $delta): void
    {
        $windowRenderTarget = $this->window->getRenderTarget();

        $data = new PipelineContainer;

        $pipeline = new RenderPipeline($this->renderResources, $data, $windowRenderTarget);

        $pipeline->addPass(new ClearPass($data->get(BackbufferData::class)->target));
        



        // $pipeline->addPass(new ShadowMapPass($renderables));

        // $pipeline->addPass(new DebugDepthPass($data->get(ShadowMapData::class)->shadowMap));

        $pipeline->execute($this->tick++);

        $this->window->swapBuffers();
    }

    public function shouldStop(): bool
    {
        return $this->window->shouldClose();
    }

    public function start(): void
    {
        $this->loop->start();
    }
}

$game = new Game($window, $gl);
$game->start();


// while (!$window->shouldClose()) {
//     $window->pollEvents();
//     $window->swapBuffers();
// }