<?php

namespace VISU\System\Dev;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use GL\Math\{GLM, Mat4, Quat, Vec2, Vec3};
use VISU\Component\Dev\GizmoComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\AABB;
use VISU\Geo\Transform;
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
use VISU\Signal\Dispatcher;
use VISU\Signal\SignalQueue;
use VISU\Signals\Input\CursorPosSignal;
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

    /**
     * The active gizmo entity, entity currently beeing edited
     */
    private int $activeGizmoEntity = 0;

    /**
     * The currently translated axis
     * 
     * @var int 0 = none, 1 = x, 2 = y, 3 = z
     */
    private int $activeAxis = 0;

    /**
     * The inital world space position of the gizmo intersection
     */
    private ?Vec3 $gizmoIntersectionPos = null;

    /**
     * The inital world space translation of the gizmo entity
     * 
     * @var null|Vec3
     */
    private ?Vec3 $gizmoTranslationInitial = null;

    /**
     * The distance of the gizmo intersection to the camera when the gizmo was selected
     */
    private float $gizmoIntersectionDistance = 0;

    /**
     * Mouse position queue
     * 
     * @var SignalQueue<CursorPosSignal>
     */
    private SignalQueue $cursorPositionQueue;

    /**
     * The input contetx string for the gizmo
     */
    private const INPUT_CONTEXT = 'visu/dev/gizmo';

    /**
     * The mouse click event listener id
     */
    private ?int $mouseClickListenerId = null;

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
            3, // normal
            3 // color 
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

            $this->vaLocations[$name] = [$vertexOffset, $vertexCount * 3];

            // preprocess the indicator for each axis
            foreach([ 
                'x' => [new Vec3(0, 0, 1), new Vec3(1.0, 0.071, 0.29)],
                'y' => [new Vec3(0, 1, 0), new Vec3(0.0, 1.0, 0.0)],
                'z' => [new Vec3(1, 0, 0), new Vec3(0.0, 0.0, 1.0)],
            ] as $axis => $tuple) 
            {
                // rotate the vertex according to the axis
                list($rotation, $color) = $tuple;

                $model = new Mat4;
                $model->rotate(GLM::radians(90), $rotation);

                $aabbName = $name . ':' . $axis;
                $aabbs[$aabbName] = new AABB(new Vec3(0, 0, 0), new Vec3(0, 0, 0));

                for($i = 0; $i < $vertexCount * 6; $i+=6) 
                {    
                    $position = new Vec3(
                        $vertices[$i+0],
                        $vertices[$i+1],
                        $vertices[$i+2],
                    );

                    $position = $model * $position;

                    $buffer->pushVec3($position);

                    // buffer the normal as is as we don't use it right now
                    // and but i want to use it in the future..
                    $buffer->push($vertices[$i+3]);
                    $buffer->push($vertices[$i+4]);
                    $buffer->push($vertices[$i+5]);

                    // push the color
                    $buffer->pushVec3($color);

                    // update the bounding box
                    $aabbs[$aabbName]->min->x = min($aabbs[$aabbName]->min->x, $position->x);
                    $aabbs[$aabbName]->min->y = min($aabbs[$aabbName]->min->y, $position->y);
                    $aabbs[$aabbName]->min->z = min($aabbs[$aabbName]->min->z, $position->z);

                    $aabbs[$aabbName]->max->x = max($aabbs[$aabbName]->max->x, $position->x);
                    $aabbs[$aabbName]->max->y = max($aabbs[$aabbName]->max->y, $position->y);
                    $aabbs[$aabbName]->max->z = max($aabbs[$aabbName]->max->z, $position->z);

                    // update the vertex offset
                    $vertexOffset++;
                }
            }

            $this->gizmoAABB = $aabbs;
        }

        $this->gizmoVA->upload($buffer);
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
        $this->mouseClickListenerId = $this->dispatcher->register(Input::EVENT_MOUSE_BUTTON, function(MouseButtonSignal $signal) use($entities)
        {
            // is the context already claimed?
            if ($signal->isLeftUp() && $this->input->isClaimedContext(self::INPUT_CONTEXT)) {
                $this->input->releaseContext(self::INPUT_CONTEXT);
                $signal->stopPropagation();
                $this->activeGizmoEntity = 0;
                $this->activeAxis = 0;
                return;
            }

            // if not a mouse down event or the input context is already claimed, we don't need to do anything
            if (!($signal->isLeftDown() && $this->input->isContextUnclaimed())) {
                return;
            }

            $camera = $entities->first(Camera::class);

            // create a ray from the camera to the cursor position
            $cursorPos = $this->input->getNormalizedCursorPosition();
            $ray = $camera->getSSRay($this->lastFrameRenderTarget, $cursorPos);

            foreach($entities->view(GizmoComponent::class) as $entity => $gizmoComponent) 
            {
                // if we have a ray intersection with a gizmo AABB, we can start moving things around
                // this means we claim the input context and stop the propagation of the event
                if ($this->gizmoIntersectionPos = $gizmoComponent->aabbTranslateX->intersectRay($ray)) {
                    $this->input->claimContext(self::INPUT_CONTEXT);
                    $signal->stopPropagation();
                    $this->activeGizmoEntity = $entity;
                    $this->activeAxis = 1;
                    break;
                }
                elseif ($this->gizmoIntersectionPos = $gizmoComponent->aabbTranslateY->intersectRay($ray)) {
                    $this->input->claimContext(self::INPUT_CONTEXT);
                    $signal->stopPropagation();
                    $this->activeGizmoEntity = $entity;
                    $this->activeAxis = 2;
                    break;
                }
                elseif ($this->gizmoIntersectionPos = $gizmoComponent->aabbTranslateZ->intersectRay($ray)) {
                    $this->input->claimContext(self::INPUT_CONTEXT);
                    $signal->stopPropagation();
                    $this->activeGizmoEntity = $entity;
                    $this->activeAxis = 3;
                    break;
                }
            }

            // additionally store the distance between the camera 
            // and the interaction point, we need this information to properly apply
            // an offset from the cameras position
            if ($this->activeGizmoEntity) {
                $this->gizmoIntersectionDistance = $this->gizmoIntersectionPos->distanceTo($camera->transform->position);
                $this->gizmoTranslationInitial = $entities->get($this->activeGizmoEntity, Transform::class)->position->copy();
            } else {
                $this->gizmoIntersectionDistance = 0;
                $this->gizmoIntersectionPos = null;
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
        $this->dispatcher->unregister(Input::EVENT_MOUSE_BUTTON, $this->mouseClickListenerId);
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
        if (!$this->input->isClaimedContext(self::INPUT_CONTEXT)) {
            $this->cursorPositionQueue->flush(); // always clear the input queues
            return;
        }

        if (!$entities->valid($this->activeGizmoEntity)) {
            $this->cursorPositionQueue->flush();
            return;
        }

        if (!$entities->has($this->activeGizmoEntity, Transform::class)) {
            $this->cursorPositionQueue->flush();
            return;
        }

        // get the camera to create a ray from the current cursor position
        $camera = $entities->first(Camera::class);

        // create a ray from the camera to the cursor position
        $cursorPos = $this->input->getNormalizedCursorPosition();
        $ray = $camera->getSSRay($this->lastFrameRenderTarget, $cursorPos);

        // determine the world space position of our mouse ray using 
        // the distance from the camera to the gizmo when the interaction started
        // @todo it would probably make more sense to do an intersection test on an infinite
        // plain for the given axis intead. Because this way depening on the cameras position
        // to the gizmo the movement is limited by the inital distance.. :mario
        $rayPos = $ray->pointAt($this->gizmoIntersectionDistance);

        // get the transform of the active gizmo
        $transform = $entities->get($this->activeGizmoEntity, Transform::class);

        // translate x axis
        if ($this->activeAxis === 1) {
            $transform->position->x = $this->gizmoTranslationInitial->x + ($rayPos->x - $this->gizmoIntersectionPos->x);
        }

        // translate y axis
        elseif ($this->activeAxis === 2) {
            $transform->position->y = $this->gizmoTranslationInitial->y + ($rayPos->y - $this->gizmoIntersectionPos->y);
        }

        // translate z axis
        elseif ($this->activeAxis === 3) {
            $transform->position->z = $this->gizmoTranslationInitial->z + ($rayPos->z - $this->gizmoIntersectionPos->z);
        }

        $transform->markDirty();

        $this->cursorPositionQueue->flush(); // <- @todo remove the cursor queue as we use the relative distance
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

        // caluclate distance from the camera
        $cameraData = $context->data->get(CameraData::class);

        // update the AABBs for every gizmo
        // this is not really efficient, but the gizmo system is not meant to be used
        // in a normal game loop anyway
        foreach($entities->view(GizmoComponent::class) as $entity => $gizmoComponent) 
        {
            $transform = $entities->get($entity, Transform::class);

            // determine the distance from the camera to the gizmo
            $distance = $transform->position->distanceTo($cameraData->frameCamera->transform->position);

            // scale the distance by a predefined factor that i thought looked good
            $distance /= 96;
            $gizmoComponent->scale = $distance;

            // scale and translate the AABBs based on the distance to the camera
            $gizmoComponent->aabbTranslateX->min = $this->gizmoAABB['pos:x']->min * $distance + $transform->position;
            $gizmoComponent->aabbTranslateX->max = $this->gizmoAABB['pos:x']->max * $distance + $transform->position;
            $gizmoComponent->aabbTranslateY->min = $this->gizmoAABB['pos:y']->min * $distance + $transform->position;
            $gizmoComponent->aabbTranslateY->max = $this->gizmoAABB['pos:y']->max * $distance + $transform->position;
            $gizmoComponent->aabbTranslateZ->min = $this->gizmoAABB['pos:z']->min * $distance + $transform->position;
            $gizmoComponent->aabbTranslateZ->max = $this->gizmoAABB['pos:z']->max * $distance + $transform->position;

            // D3D::aabb(new Vec3, $gizmoComponent->aabbTranslateX->min, $gizmoComponent->aabbTranslateX->max, D3D::$colorRed);
            // D3D::aabb(new Vec3, $gizmoComponent->aabbTranslateY->min, $gizmoComponent->aabbTranslateY->max, D3D::$colorGreen);
            // D3D::aabb(new Vec3, $gizmoComponent->aabbTranslateZ->min, $gizmoComponent->aabbTranslateZ->max, D3D::$colorBlue);
        }


        $context->pipeline->addPass(new CallbackPass(
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data)
            {
                $pipeline->writes($pass, $data->get(BackbufferData::class)->target);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($entities)
            {
                $target = $resources->getRenderTarget($data->get(BackbufferData::class)->target);
                $target->preparePass();

                $cameraData = $data->get(CameraData::class);

                $this->gizmoShader->use();

                $this->gizmoShader->setUniformMat4('projection', false, $cameraData->projection);
                $this->gizmoShader->setUniformMat4('view', false, $cameraData->view);

                $this->gizmoVA->bind();

                list($offset, $size) = $this->vaLocations['pos'];

                glDisable(GL_DEPTH_TEST);
                glDisable(GL_CULL_FACE);

                foreach($entities->view(GizmoComponent::class) as $entity => $gizmoComponent) 
                {
                    $transform = $entities->get($entity, Transform::class);
                    
                    $model = new Mat4;
                    $model->translate($transform->position);
                    $model->scale(new Vec3($gizmoComponent->scale));

                    $this->gizmoShader->setUniformMat4('model', false, $model);
                    $this->gizmoVA->draw($offset, $size);
                }
            }
        ));
    }
}