<?php

namespace VISU\Graphics\Rendering\Renderer;

use GL\Buffer\FloatBuffer;
use GL\Math\Vec2;
use GL\Math\Vec3;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class Debug3DRenderer
{   
    /**
     * GL Vertex buffer object id
     */
    private int $VBO = 0; 

    /**
     * GL Vertex array object id
     */
    private int $VAO = 0;

    /**
     * The debug 3D shader program.
     */
    private ShaderProgram $shaderProgram;

    /**
     * Vertex data holder, this is regenerated every frame.
     */
    private FloatBuffer $vertices;

    /**
     * Static instance for easy access to the debugger.
     * This class should really not end up being used in PROD mode. So in most cases
     * it is used to quickly debug something, which is why it needs to be easily accessible.
     */
    private static ?Debug3DRenderer $instance = null;

    /**
     * Returns a global instance of the debug 3D renderer.
     * throws an exception if the renderer has not been initialized yet.
     */
    public static function getGlobalInstance() : Debug3DRenderer
    {
        if (self::$instance === null) {
            throw new \Exception('Debug3DRenderer has not been initialized yet, call Debug3DRenderer::setGlobalInstance() first.');
        }

        return self::$instance;
    }

    /**
     * Sets the global instance of the debug 3D renderer.
     * 
     * @param Debug3DRenderer $instance The instance to set.
     */
    public static function setGlobalInstance(Debug3DRenderer $instance) : void
    {
        self::$instance = $instance;
        
        // initialize color helper constants here
        // this is a bit hacky..
        self::$colorRed = new Vec3(1, 0, 0);
        self::$colorGreen = new Vec3(0, 1, 0);
        self::$colorBlue = new Vec3(0, 0, 1);
        self::$colorYellow = new Vec3(1, 1, 0);
        self::$colorMagenta = new Vec3(1, 0, 1);
        self::$colorCyan = new Vec3(0, 1, 1);
        self::$colorWhite = new Vec3(1, 1, 1);
        self::$colorBlack = new Vec3(0, 0, 0);
    }

    /**
     * Color helper "constants"
     */
    public static Vec3 $colorRed;
    public static Vec3 $colorGreen;
    public static Vec3 $colorBlue;
    public static Vec3 $colorYellow;
    public static Vec3 $colorMagenta;
    public static Vec3 $colorCyan;
    public static Vec3 $colorWhite;
    public static Vec3 $colorBlack;

    /**
     * Adds a ray to the debug 3D render queue
     * 
     * @param Vec3 $origin The origin of the ray
     * @param Vec3 $direction The direction of the ray
     * @param float $length The length of the ray
     * @param Vec3 $color The color of the ray
     */
    public static function ray(Vec3 $origin, Vec3 $direction, Vec3 $color, float $length = 50) : void
    {
        static::getGlobalInstance()->addRay($origin, $direction, $length, $color);
    }

    /**
     * Draws a cross at the given position
     * 
     * @param Vec3 $origin The origin of the cross
     * @param Vec3 $color The color of the cross
     * @param float $length The length of the cross
     */
    public static function cross(Vec3 $origin, Vec3 $color, float $length = 10) : void
    {
        static::getGlobalInstance()->addCross($origin, $color, $length);
    }

    /**
     * Draws an axis aligned bounding box
     * 
     * @param Vec3 $origin The origin of the box
     * @param Vec3 $min The minimum point of the box
     * @param Vec3 $max The maximum point of the box
     * @param Vec3 $color The color of the box
     */
    public static function aabb(Vec3 $origin, Vec3 $min, Vec3 $max, Vec3 $color) : void
    {
        static::getGlobalInstance()->addAABB($origin, $min, $max, $color);
    }

    /**
     * Draws a 2D axis aligned bounding box
     * 
     * @param Vec2 $origin The origin of the box
     * @param Vec2 $min The minimum point of the box
     * @param Vec2 $max The maximum point of the box
     * @param Vec3 $color The color of the box
     */
    public static function aabb2D(Vec2 $origin, Vec2 $min, Vec2 $max, Vec3 $color) : void
    {
        static::getGlobalInstance()->addAABB2D($origin, $min, $max, $color);
    }

    /**
     * Draws a 2 control point bezier curve
     * 
     * @param Vec3 $origin The origin of the curve
     * @param Vec3 $p0 The first control point
     * @param Vec3 $destination The destination of the curve
     * @param Vec3 $color The color of the curve
     * @param int $segments The number of segments to draw
     */
    public static function bezier(Vec3 $origin, Vec3 $p0, Vec3 $destination, Vec3 $color, int $segments = 10) : void
    {
        static::getGlobalInstance()->addBezierCurve($origin, $p0, $destination, $color, $segments);
    }

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        private GLState $glstate,
    )
    {
        // create vertices buffer
        $this->vertices = new FloatBuffer();

        // build the vertex array and buffer objects
        $this->createVAO();

        // create the shader program
        $this->shaderProgram = new ShaderProgram($glstate);

        // our shader is used to render primitive lines 
        // nothing fancy
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 1) in vec3 a_color;

        out vec3 v_color;

        uniform mat4 projection;
        uniform mat4 view;

        void main()
        {
            gl_Position = projection * view * vec4(a_position, 1.0f);
            v_color = a_color;
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        out vec4 fragment_color;

        in vec3 v_color;

        void main()
        {
            fragment_color = vec4(v_color, 1.0f);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Creates the vertex array and buffer objects
     */
    private function createVAO() : void
    {
        glGenVertexArrays(1, $this->VAO);
        glGenBuffers(1, $this->VBO);

        $this->glstate->bindVertexArray($this->VAO);
        $this->glstate->bindVertexArrayBuffer($this->VBO);

        // vertex attributes for the text
        // position
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(0, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, 0);

        // color
        glEnableVertexAttribArray(1);
        glVertexAttribPointer(1, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, GL_SIZEOF_FLOAT * 3);
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget
    ) : void
    {
        $pipeline->addPass(new CallbackPass(
            'D3D',
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use ($renderTarget) {
                $pipeline->writes($pass, $renderTarget);
            },
            function(PipelineContainer $data, PipelineResources $resources) use ($renderTarget) 
            {
                $renderTarget = $resources->activateRenderTarget($renderTarget);
                $cameraData = $data->get(CameraData::class);

                // activate the shader program
                $this->shaderProgram->use();
                $this->shaderProgram->setUniformMat4('projection', false, $cameraData->projection);
                $this->shaderProgram->setUniformMat4('view', false, $cameraData->view);

                // bind the vertex array and buffer
                $this->glstate->bindVertexArray($this->VAO);
                $this->glstate->bindVertexArrayBuffer($this->VBO);

                // fill the buffer with the vertices
                glBufferData(GL_ARRAY_BUFFER, $this->vertices, GL_DYNAMIC_DRAW);

                // pipeline settings 
                glDisable(GL_DEPTH_TEST);
                glDisable(GL_BLEND);

                // draw the lines
                glDrawArrays(GL_LINES, 0, $this->vertices->size() / 6);

                $this->vertices->clear();
            },
        ));
    }

    /**
     * Adds a ray to the debug 3D render queue
     * 
     * @param Vec3 $origin The origin of the ray
     * @param Vec3 $direction The direction of the ray
     * @param float $length The length of the ray
     * @param Vec3 $color The color of the ray
     */
    public function addRay(
        Vec3 $origin, 
        Vec3 $direction, 
        float $length, 
        Vec3 $color
    ) : void
    {
        $this->vertices->pushArray([
            $origin->x, $origin->y, $origin->z,  // vertex 1 position
            $color->x, $color->y, $color->z, // vertex 1 color
            $origin->x + $direction->x * $length, $origin->y + $direction->y * $length, $origin->z + $direction->z * $length, // vertex 2 position
            $color->x, $color->y, $color->z, // vertex 2 color
        ]);
    }

    /**
     * Draws a line from the given origin to the given destination
     *
     * @param Vec3 $origin
     * @param Vec3 $destination
     * @param Vec3 $color
     * @return void
     */
    public function addLine(Vec3 $origin, Vec3 $destination, Vec3 $color) : void
    {
        $this->vertices->pushArray([
            $origin->x, $origin->y, $origin->z,  // vertex 1 position
            $color->x, $color->y, $color->z, // vertex 1 color
            $destination->x, $destination->y, $destination->z, // vertex 2 position
            $color->x, $color->y, $color->z, // vertex 2 color
        ]);
    }

    /**
     * Draws a cross at the given position
     * 
     * @param Vec3 $origin The origin of the cross
     * @param Vec3 $color The color of the cross
     * @param float $length The length of the cross
     */
    public function addCross(Vec3 $origin, Vec3 $color, float $length = 10) : void
    {
        $this->addLine($origin - new Vec3($length * 0.5, 0, 0), $origin + new Vec3($length * 0.5, 0, 0), $color);
        $this->addLine($origin - new Vec3(0, $length * 0.5, 0), $origin + new Vec3(0, $length * 0.5, 0), $color);
        $this->addLine($origin - new Vec3(0, 0, $length * 0.5), $origin + new Vec3(0, 0, $length * 0.5), $color);
    }

    /**
     * Draws an axis aligned bounding box
     * 
     * @param Vec3 $origin The origin of the box
     * @param Vec3 $min The minimum point of the box
     * @param Vec3 $max The maximum point of the box
     * @param Vec3 $color The color of the box
     */
    public function addAABB(Vec3 $origin, Vec3 $min, Vec3 $max, Vec3 $color) : void
    {
        $this->addLine($origin + $min, $origin + new Vec3($max->x, $min->y, $min->z), $color);
        $this->addLine($origin + $min, $origin + new Vec3($min->x, $max->y, $min->z), $color);
        $this->addLine($origin + $min, $origin + new Vec3($min->x, $min->y, $max->z), $color);
        $this->addLine($origin + $max, $origin + new Vec3($min->x, $max->y, $max->z), $color);
        $this->addLine($origin + $max, $origin + new Vec3($max->x, $min->y, $max->z), $color);
        $this->addLine($origin + $max, $origin + new Vec3($max->x, $max->y, $min->z), $color);
        $this->addLine($origin + new Vec3($min->x, $max->y, $min->z), $origin + new Vec3($max->x, $max->y, $min->z), $color);
        $this->addLine($origin + new Vec3($min->x, $max->y, $min->z), $origin + new Vec3($min->x, $max->y, $max->z), $color);
        $this->addLine($origin + new Vec3($min->x, $min->y, $max->z), $origin + new Vec3($max->x, $min->y, $max->z), $color);
        $this->addLine($origin + new Vec3($min->x, $min->y, $max->z), $origin + new Vec3($min->x, $max->y, $max->z), $color);
        $this->addLine($origin + new Vec3($max->x, $min->y, $min->z), $origin + new Vec3($max->x, $max->y, $min->z), $color);
        $this->addLine($origin + new Vec3($max->x, $min->y, $min->z), $origin + new Vec3($max->x, $min->y, $max->z), $color);
    }

    /**
     * Draws a 2D axis aligned bounding box
     * 
     * @param Vec2 $origin The origin of the box
     * @param Vec2 $min The minimum point of the box
     * @param Vec2 $max The maximum point of the box
     * @param Vec3 $color The color of the box
     */
    public function addAABB2D(Vec2 $origin, Vec2 $min, Vec2 $max, Vec3 $color) : void
    {
        $this->addLine(new Vec3($origin->x + $min->x, $origin->y + $min->y, 0), new Vec3($origin->x + $max->x, $origin->y + $min->y, 0), $color);
        $this->addLine(new Vec3($origin->x + $min->x, $origin->y + $min->y, 0), new Vec3($origin->x + $min->x, $origin->y + $max->y, 0), $color);
        $this->addLine(new Vec3($origin->x + $max->x, $origin->y + $max->y, 0), new Vec3($origin->x + $min->x, $origin->y + $max->y, 0), $color);
        $this->addLine(new Vec3($origin->x + $max->x, $origin->y + $max->y, 0), new Vec3($origin->x + $max->x, $origin->y + $min->y, 0), $color);
    }

    /**
     * Draws a bezier curve
     * 
     * @param Vec3 $origin The origin of the curve
     * @param Vec3 $p0 The first control point
     * @param Vec3 $destination The destination of the curve
     * @param Vec3 $color The color of the curve
     * @param int $segments The number of segments to draw
     */
    public function addBezierCurve(
        Vec3 $origin, 
        Vec3 $p0, 
        Vec3 $destination, 
        Vec3 $color, 
        int $segments = 10
    ) : void
    {
        $t = 0;
        $step = 1 / $segments;
        $last = $origin->copy();
        for ($i = 0; $i < $segments; $i++) {
            $t += $step;
            $current = $origin * (1 - $t) * (1 - $t) + $p0 * 2 * (1 - $t) * $t + $destination * $t * $t;
            $this->addLine($last, $current, $color);
            $last = $current->copy();
        }

        $this->addLine($last, $destination, static::$colorGreen);
        $this->addCross($p0, static::$colorMagenta);
    }
}
