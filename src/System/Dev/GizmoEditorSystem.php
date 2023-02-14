<?php

namespace VISU\System\Dev;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use GL\Math\{GLM, Mat4, Quat, Vec2, Vec3};
use VISU\Component\Dev\GizmoComponent;
use VISU\D3D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\AABB;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\Camera;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\OS\Input;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Signal\SignalQueue;
use VISU\Signals\Input\MouseButtonSignal;

class GizmoEditorSystem implements SystemInterface
{
    /**
     * The Vertex array for the gizmo geometry
     */
    private BasicVertexArray $gizmoVA;

    /**
     * The shader program for the gizmo geometry
     */
    private ShaderProgram $gizmoShader;

    /**
     * Vertex array locations of the gizmo geometry
     * 
     * @var array<string, array<int, int>> offset, size of each object
     */
    private array $vaLocations = [];

    /**
     * AABBs of the gizmo geometry
     * 
     * @var array<string, AABB>
     */
    private array $gizmoAABB = [];

    /**
     * Last frame render target
     */
    private RenderTarget $lastFrameRenderTarget;


    private int $activeGizmoEntity = 0;

    /**
     * Mouse position queue
     */
    private SignalQueue $cursorPositionQueue;

    /**
     * The input contetx string for the gizmo
     */
    private const INPUT_CONTEXT = 'visu/dev/gizmo';

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
        private Dispatcher $dispatcher,
        private Input $input,
    )
    {
        $this->gizmoShader = $this->shaders->get('visu/dev/gizmo');

        $this->gizmoVA = new BasicVertexArray($this->gl, [
            3, // postion
            3 // normal
        ]);

        $vertexOffset = 0;
        $buffer = new FloatBuffer();

        $aabbs = []; 

        foreach([
            'pos' => 'arrow.obj',
            'scale' => 'scale.obj',
        ] as $name => $devFileName) 
        {
            $model = new ObjFileParser(VISU_PATH_FRAMEWORK_RESOURCES . '/model/dev/' . $devFileName);
            $vertices = $model->getVertices('pn');

            $vertexCount = $vertices->size() / 6;
            $this->vaLocations[$name] = [$vertexOffset, $vertexCount];

            foreach($vertices as $vertex) {
                $buffer->push($vertex);
            }

            $aabbs[$name] = new AABB(new Vec3(0, 0, 0), new Vec3(0, 0, 0));

            for($i = 0; $i < $vertexCount; $i+=6) {
                $pos = new Vec3($buffer[$i], $buffer[$i+1], $buffer[$i+2]);

                $aabbs[$name]->min->x = min($aabbs[$name]->min->x, $pos->x);
                $aabbs[$name]->min->y = min($aabbs[$name]->min->y, $pos->y);
                $aabbs[$name]->min->z = min($aabbs[$name]->min->z, $pos->z);

                $aabbs[$name]->max->x = max($aabbs[$name]->max->x, $pos->x);
                $aabbs[$name]->max->y = max($aabbs[$name]->max->y, $pos->y);
                $aabbs[$name]->max->z = max($aabbs[$name]->max->z, $pos->z);
            }

            $vertexOffset += $vertexCount;
        }

        $this->gizmoAABB = $aabbs;

        $this->gizmoVA->upload($buffer);
    }

    /**
     * Returns a FloatBuffer containing the vertices of the given dev geometry.
     */
    private function loadDevGeometry(string $path) : FloatBuffer
    {
        $model = new ObjFileParser($path);
        $vertices = $model->getVertices('pn');

        return $vertices;
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->registerComponent(GizmoComponent::class);

        $this->cursorPositionQueue = $this->dispatcher->createSignalQueue(Input::EVENT_CURSOR, 1);

        // register an mouse button event handler, so we know when to 
        // move things around
        $this->dispatcher->register(Input::EVENT_MOUSE_BUTTON, function(MouseButtonSignal $signal) use($entities)
        {
            if($signal->isLeftUp() && $this->input->isClaimedContext(self::INPUT_CONTEXT)) {
                $this->input->releaseContext(self::INPUT_CONTEXT);
                $signal->stopPropagation();
                return;
            }

            $camera = $entities->first(Camera::class);

            // create a ray from the camera to the cursor position
            $cursorPos = $this->input->getNormalizedCursorPosition();
            $ray = $camera->getSSRay($this->lastFrameRenderTarget, $cursorPos);

            $intersection = $this->gizmoAABB['pos']->intersectRay($ray);

            // if no AABB was hit, we don't need to do anything
            if ($intersection === null) {
                return;
            }

            if ($signal->isLeftDown() && $this->input->isContextUnclaimed()) {
                $this->input->claimContext(self::INPUT_CONTEXT);
                $signal->stopPropagation();
            }
        });
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
        if (!$this->input->isClaimedContext(self::INPUT_CONTEXT)) {
            return;
        }

        foreach($entities->view(GizmoComponent::class) as $entity => $gizmoComponent) 
        {
            // var_dump($gizmoComponent);
        }
    }
    
    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        $backbuffer = $context->data->get(BackbufferData::class);
        $this->lastFrameRenderTarget = $context->resources->getRenderTarget($backbuffer->target);   

        D3D::aabb(new Vec3, $this->gizmoAABB['pos']->min, $this->gizmoAABB['pos']->max, D3D::$colorCyan);

        $context->pipeline->addPass(new CallbackPass(
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data)
            {

            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) 
            {
                $cameraData = $data->get(CameraData::class);

                $this->gizmoShader->use();

                $this->gizmoShader->setUniformMat4('projection', false, $cameraData->projection);
                $this->gizmoShader->setUniformMat4('view', false, $cameraData->view);
                $this->gizmoShader->setUniformVec3('view_position', $cameraData->renderCamera->transform->position);

                $this->gizmoVA->bind();

                list($offset, $size) = $this->vaLocations['pos'];

                glDisable(GL_DEPTH_TEST);
                glDisable(GL_CULL_FACE);

                foreach([
                    [new Vec3(1, 0, 0), new Vec3(1.0, 0.071, 0.29)],
                    [new Vec3(0, 1, 0), new Vec3(0.0, 1.0, 0.0)],
                    [new Vec3(0, 0, 1), new Vec3(0.0, 0.0, 1.0)],
                ] as $tuple) 
                {
                    list($rotation, $color) = $tuple;

                    $model = new Mat4;
                    $model->rotate(GLM::radians(90), $rotation);

                    $this->gizmoShader->setUniformVec3('color', $color);
                    $this->gizmoShader->setUniformMat4('model', false, $model);
                    $this->gizmoVA->draw($offset, $size);
                }

            }
        ));


    }
}