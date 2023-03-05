<?php

namespace VISU\Runtime;

use GL\Math\{GLM, Mat4, Quat, Vec2, Vec3};
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\DebugOverlayText;
use VISU\Graphics\Rendering\Renderer\DebugOverlayTextRenderer;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\Logger;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\CharSignal;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Runtime\ConsoleCommandSignal;

class DebugConsole
{
    /**
     * The input context for the console
     */
    private const INPUT_CONTEXT = 'visu/dev/console';

    /**
     * The char signal listener function id
     */
    private ?int $charInputListenerId = null;

    /**
     * Debug font renderer
     */
    private DebugOverlayTextRenderer $debugTextRenderer;

    /**
     * Is the console currently enabled?
     */
    private $enabled = false;

    /**
     * The string that is currently beeing written to
     */
    private $inputLine = "";

    /**
     * The input line history
     */
    private array $inputLineHistory = [];

    /**
     * The current history index
     */
    private int $inputLineHistoryIndex = 0;

    /**
     * The input line history limit
     */
    private int $inputLineHistoryLimit = 100;

    /**
     * The maximum number of lines to be rendered
     */
    private int $inputLineHistoryRenderLimit = 10;

    /**
     * Quad we render as a background
     */
    private QuadVertexArray $quadVA;

    /**
     * The debug font shader program.
     */
    private ShaderProgram $shaderProgram;

    /**
     * The event that is dispatched when a console command is executed
     */
    public const EVENT_CONSOLE_COMMAND = 'console.command';

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private Input $input,
        private Dispatcher $dispatcher,
    )
    {
        // construct a text overlay renderer for our console
        $this->debugTextRenderer = new DebugOverlayTextRenderer(
            $gl,
            DebugOverlayTextRenderer::loadDebugFontAtlas(),
        );

        // create a new quad
        $this->quadVA = new QuadVertexArray($gl);

        // register keyboard event listener
        $this->dispatcher->register(Input::EVENT_KEY, [$this, 'handleKeyboardInputSignal'], -1000); // the console listener overturns most other systems

        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;

        uniform mat4 projection;
        uniform mat4 model;

        void main()
        {
            gl_Position = projection * model * vec4(a_position, 1.0f);
        }
        GLSL));
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        out vec4 fragment_color;

        void main()
        {
            fragment_color = vec4(vec3(0.0f), 0.5f);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Writes a line to the console
     * This will simply append it to the input line history
     * 
     * @param string $line
     */
    public function writeLine(string $line)
    {
        $this->inputLineHistory[] = $line;
        $this->inputLineHistoryIndex = count($this->inputLineHistory);
    }

    /**
     * Keyboard event handler
     */
    public function handleKeyboardInputSignal(KeySignal $signal)
    {
        // if the console is enabled we need to handle some special commands
        // like backspace and enter
        if ($this->enabled) 
        {
            if ($signal->key === Key::ENTER && $signal->action === Input::RELEASE) {
                // execute the command
                $commandSignal = new ConsoleCommandSignal($this->inputLine, $this);

                // reset the input line
                $this->inputLine = "";

                // add the command to the history
                $this->writeLine($commandSignal->commandString);

                // limit the history
                if (count($this->inputLineHistory) > $this->inputLineHistoryLimit) {
                    array_shift($this->inputLineHistory);
                }
                
                // dispatch the signal
                Logger::info("Executing command: " . $commandSignal->commandString);
                $this->dispatcher->dispatch(self::EVENT_CONSOLE_COMMAND, $commandSignal);
            }

            // handle history navigation with up and down
            if ($signal->key === Key::UP && $signal->action === Input::RELEASE) {
                $this->inputLineHistoryIndex = max(0, $this->inputLineHistoryIndex - 1);
                $this->inputLine = $this->inputLineHistory[$this->inputLineHistoryIndex] ?? "";
            }

            if ($signal->key === Key::DOWN && $signal->action === Input::RELEASE) {
                $this->inputLineHistoryIndex = min(count($this->inputLineHistory), $this->inputLineHistoryIndex + 1);
                $this->inputLine = $this->inputLineHistory[$this->inputLineHistoryIndex] ?? "";
            }

            if ($signal->key === Key::BACKSPACE && ($signal->action === Input::PRESS || $signal->action === Input::REPEAT)) {
                // remove the last character
                $this->inputLine = substr($this->inputLine, 0, -1);
            }
        }

        // ctrl + c = enable disable the console
        if ($signal->key === Key::C && $signal->isControlDown() && $signal->action === Input::PRESS) {
            $this->enabled = !$this->enabled;
            $signal->stopPropagation();

            // claim or unclaim context
            if ($this->enabled) {
                $this->input->claimContext(self::INPUT_CONTEXT);
                $this->charInputListenerId = $this->dispatcher->register(Input::EVENT_CHAR, [$this, 'handleKeyboardCharSignal'], -1000);
                
            } else {
                $this->input->releaseContext(self::INPUT_CONTEXT);
                $this->dispatcher->unregister(Input::EVENT_CHAR, $this->charInputListenerId);
            }

            Logger::info("Console enabled: " . var_export($this->enabled, true));
        }
    }

    /**
     * Char input handler
     */
    public function handleKeyboardCharSignal(CharSignal $signal)
    {
        $this->inputLine .= $signal->getString();
    }
    
    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function attachPass(RenderPipeline $pipeline, PipelineResources $resources, RenderTargetResource $rt) : void
    {
        if (!$this->enabled) return;

        $target = $resources->getRenderTarget($rt);

        $consoleHeight = 40;
        $y = $target->height() - $consoleHeight;

        $text = 'CONSOLE: ' . $this->inputLine;

        // append a cursor
        if (time() % 2 === 0) {
            $text .= '_';
        }

        $blockHeight = $this->debugTextRenderer->lineHeight * $target->contentScaleX * $this->inputLineHistoryRenderLimit;
        $blockHeight += $consoleHeight;

        // render a simple 
        $pipeline->addPass(new CallbackPass(
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($rt) {
                $pipeline->writes($pass, $rt);
            },
            function(PipelineContainer $data, PipelineResources $resources) use($rt, $blockHeight) 
            {
                $renderTarget = $resources->getRenderTarget($rt);
                $renderTarget->preparePass();
                $this->shaderProgram->use();

                $proj = new Mat4;
                $proj->ortho(0, $renderTarget->width(), 0, $renderTarget->height(), -1, 1);
                $this->shaderProgram->setUniformMat4('projection', false, $proj);

                $model = new Mat4;
                $model->translate(new Vec3(0, 0, 0));
                $model->scale(new Vec3($renderTarget->width(), $blockHeight, 1));
                $this->shaderProgram->setUniformMat4('model', false, $model);

                glDisable(GL_DEPTH_TEST);
                glDisable(GL_CULL_FACE);
                glEnable(GL_BLEND);
                glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
                glBlendEquation(GL_FUNC_ADD);
                $this->quadVA->draw();
            }
        ));

        // create the history block
        $historySlice = array_slice($this->inputLineHistory, -$this->inputLineHistoryRenderLimit);
        $history = implode("\n", $historySlice);
        $height = $this->debugTextRenderer->lineHeight * $target->contentScaleX * count($historySlice);

        $this->debugTextRenderer->attachPass($pipeline, $rt, [
            new DebugOverlayText($history, 10, $y - $height, new Vec3(1.0, 1.0, 1.0)),
            new DebugOverlayText($text, 10, $y, new Vec3(1.0, 0.494, 0.459))
        ]);
    }
}