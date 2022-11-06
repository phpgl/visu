<?php

require __DIR__ . '/vendor/autoload.php';

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\OS\Input;
use VISU\OS\Window;
use VISU\Runtime\GameLoop;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

glfwInit();

$window = new Window('Test Window', 640, 480);
$window->initailize(new GLState);
$dispatcher = new Dispatcher;
$input = new Input($window, $dispatcher);

$dispatcher->register('input.key', function (KeySignal $signal) {
    var_dump($signal);
});

class Game implements VISU\Runtime\GameLoopDelegate
{
    private GameLoop $loop;
    private PipelineResources $renderResources; 

    public function __construct(
        private Window $window
    )
    {
        $this->loop = new GameLoop($this);
    }

    public function update(): void
    {
        $this->window->pollEvents();
    }

    public function render(float $delta): void
    {
        glClearColor(0.0, 0.0, 0.0, 1.0);
        glClear(GL_COLOR_BUFFER_BIT);

        $windowRenderTarget = $this->window->getRenderTarget();

        $data = new PipelineContainer;

        $pipeline = new RenderPipeline($data, $this->renderResources);
        $pipeline->addPass(new ClearPass($windowRenderTarget));
        $pipeline->addPass(new ShadowMapPass($renderables));

        $pipeline->addPass(new DebugDepthPass($data->get(ShadowMapData::class)->shadowMap));

        $pipeline->execute($windowRenderTarget);

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

$game = new Game($window);
$game->start();


// while (!$window->shouldClose()) {
//     $window->pollEvents();
//     $window->swapBuffers();
// }