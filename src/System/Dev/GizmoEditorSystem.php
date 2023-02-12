<?php

namespace VISU\System\Dev;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use GL\Math\{GLM, Mat4, Quat, Vec2, Vec3};
use VISU\Component\Dev\GizmoComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

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
    private $vaLocations = [];

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
    )
    {
        $this->gizmoShader = $this->shaders->get('visu/dev/gizmo');

        $this->gizmoVA = new BasicVertexArray($this->gl, [
            3, // postion
            3 // normal
        ]);

        $vertexOffset = 0;
        $buffer = new FloatBuffer();

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

            $vertexOffset += $vertexCount;
        }

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
    }
    
    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
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
                ] as $tuple) {

                    list($rotation, $color) = $tuple;

                    $model = new Mat4;
                    $model->rotate(GLM::radians(90), $rotation);

                    $this->gizmoShader->setUniformVec3('color', $color);
                    $this->gizmoShader->setUniformMat4('model', false, $model);
                    $this->gizmoVA->draw($offset, $size);
                }

            }
        ));

        foreach($entities->view(GizmoComponent::class) as $entity => $gizmoComponent) 
        {
            // var_dump($gizmoComponent);
        }


    }
}